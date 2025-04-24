<?php
/**
 * E-Lib System Check Script
 * 
 * This script performs various checks to ensure the E-Lib application
 * is properly configured and all dependencies are available.
 * 
 * Usage:
 *   php check-system.php [options]
 * 
 * Options:
 *   --verbose  Show detailed information for each check
 *   --fix      Attempt to fix some common issues automatically
 */

// Change to application root directory
chdir(__DIR__);

// Define color codes for terminal output
define('GREEN', "\033[0;32m");
define('RED', "\033[0;31m");
define('YELLOW', "\033[1;33m");
define('RESET', "\033[0m");

// Parse command line options
$options = getopt('', ['verbose', 'fix']);
$verbose = isset($options['verbose']);
$fix = isset($options['fix']);

echo YELLOW . "E-Lib System Check\n" . RESET;
echo "===================\n\n";

// Track overall status
$allPassed = true;
$results = [
    'passed' => [],
    'warnings' => [],
    'failed' => []
];

// Check PHP version
$phpVersion = phpversion();
$requiredPhpVersion = '8.0.0';
if (version_compare($phpVersion, $requiredPhpVersion, '>=')) {
    $results['passed'][] = "PHP version: $phpVersion";
} else {
    $results['failed'][] = "PHP version: $phpVersion (required: $requiredPhpVersion or higher)";
    $allPassed = false;
}

// Check required PHP extensions
$requiredExtensions = ['mongodb', 'openssl', 'json', 'mbstring', 'fileinfo', 'curl'];
foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        $version = phpversion($ext) ? phpversion($ext) : 'enabled';
        $results['passed'][] = "Extension: $ext ($version)";
    } else {
        $results['failed'][] = "Extension: $ext (missing)";
        $allPassed = false;
        
        if ($fix && $ext == 'openssl') {
            echo YELLOW . "Attempting to enable openssl extension...\n" . RESET;
            if (PHP_OS === 'Linux') {
                echo "Run the following command to install openssl:\n";
                echo "sudo apt-get install php-openssl\n";
            } elseif (PHP_OS === 'Darwin') {
                echo "Run the following command to install openssl:\n";
                echo "brew install php\n";
            } elseif (PHP_OS === 'WINNT') {
                echo "Edit your php.ini file to uncomment the openssl extension\n";
            }
        }
    }
}

// Check Composer dependencies
if (file_exists('vendor/autoload.php')) {
    $results['passed'][] = "Composer dependencies installed";
    
    // Load autoloader
    require_once 'vendor/autoload.php';
    
    // Check composer.json vs composer.lock
    if (file_exists('composer.json') && file_exists('composer.lock')) {
        $composerJson = json_decode(file_get_contents('composer.json'), true);
        $composerLock = json_decode(file_get_contents('composer.lock'), true);
        
        if (isset($composerJson['require']['mongodb/mongodb'])) {
            $requiredVersion = $composerJson['require']['mongodb/mongodb'];
            
            $installedVersion = null;
            foreach ($composerLock['packages'] as $package) {
                if ($package['name'] === 'mongodb/mongodb') {
                    $installedVersion = $package['version'];
                    break;
                }
            }
            
            if ($installedVersion) {
                $results['passed'][] = "MongoDB package: version $installedVersion";
            } else {
                $results['warnings'][] = "MongoDB package: not found in composer.lock";
            }
        }
    }
} else {
    $results['failed'][] = "Composer dependencies not installed";
    $allPassed = false;
    
    if ($fix) {
        echo YELLOW . "Attempting to install dependencies...\n" . RESET;
        echo "Run the following command to install dependencies:\n";
        echo "composer install\n";
    }
}

// load environment variables 
if (file_exists('.env')) {
    try {
        App\Includes\Environment::load();
        $results['passed'][] = ".env file loaded successfully";
    } catch (Exception $e) {
        $results['failed'][] = ".env file: " . $e->getMessage();
        $allPassed = false;
    }
} else {
    $results['failed'][] = ".env file not found";
    $allPassed = false;
}

// Check file structure
$requiredDirs = ['App', 'public', 'vendor'];
$requiredFiles = [
    'App/Views/Partials/Header.php',
    'App/Views/Partials/Footer.php',
    'App/Views/Components/AddBook.php'
];

foreach ($requiredDirs as $dir) {
    if (is_dir($dir)) {
        $results['passed'][] = "Directory: $dir";
    } else {
        $results['failed'][] = "Directory: $dir (missing)";
        $allPassed = false;
    }
}

foreach ($requiredFiles as $file) {
    if (file_exists($file)) {
        $results['passed'][] = "File: $file";
    } else {
        $results['failed'][] = "File: $file (missing)";
        $allPassed = false;
    }
}

// Check database connection
try {
    $dbConnectionSuccess = false;
    
    // Check if our MongoConnectionFactory class exists
    if (class_exists('App\Integration\Database\MongoConnectionFactory')) {
        echo YELLOW . "Testing MongoDB connection...\n" . RESET;
        
        try {
            $factory = new App\Integration\Database\MongoConnectionFactory();
            $db = $factory->create('mongo', ['fallback' => false]);
            $results['passed'][] = "MongoDB connection: successful";
            $dbConnectionSuccess = true;
        } catch (Exception $e) {
            $results['failed'][] = "MongoDB connection: " . $e->getMessage();
            $allPassed = false;
            
            // Try with fallback
            try {
                $db = $factory->create('mongo', ['fallback' => true]);
                $results['warnings'][] = "MongoDB fallback: using JSON database";
            } catch (Exception $e2) {
                $results['failed'][] = "MongoDB fallback: " . $e2->getMessage();
            }
        }
    } else {
        $results['warnings'][] = "MongoDB connection: MongoConnectionFactory class not found";
    }
} catch (Exception $e) {
    $results['warnings'][] = "MongoDB connection check failed: " . $e->getMessage();
}

// Check if views can be included
$viewsBasePath = __DIR__ . '/App/Views/';
$includeSuccess = true;

// Test that we can include a component without errors
ob_start();
try {
    // Simulate component environment
    $activePage = 'test';
    include $viewsBasePath . 'Partials/Header.php';
    $results['passed'][] = "Component inclusion: Header.php";
} catch (Exception $e) {
    $includeSuccess = false;
    $results['failed'][] = "Component inclusion: Header.php - " . $e->getMessage();
} finally {
    ob_end_clean();
}

// Display results
echo "\n" . YELLOW . "Test Results:\n" . RESET;

// Display passed tests
if (!empty($results['passed'])) {
    echo GREEN . "✓ Passed:\n" . RESET;
    foreach ($results['passed'] as $message) {
        echo "  - " . $message . "\n";
    }
    echo "\n";
}

// Display warnings
if (!empty($results['warnings'])) {
    echo YELLOW . "⚠ Warnings:\n" . RESET;
    foreach ($results['warnings'] as $message) {
        echo "  - " . $message . "\n";
    }
    echo "\n";
}

// Display failed tests
if (!empty($results['failed'])) {
    echo RED . "✗ Failed:\n" . RESET;
    foreach ($results['failed'] as $message) {
        echo "  - " . $message . "\n";
    }
    echo "\n";
}

// Overall status
if ($allPassed) {
    echo GREEN . "✓ All required checks passed! Your system is ready to run E-Lib.\n" . RESET;
} else {
    echo RED . "✗ Some checks failed. Please fix the issues listed above.\n" . RESET;
}

// Provide next steps
echo YELLOW . "\nNext steps:\n" . RESET;
if ($allPassed) {
    echo "1. Start your application\n";
    echo "2. Visit http://localhost:8080 (or your configured domain)\n";
} else {
    echo "1. Fix the issues listed above\n";
    echo "2. Run this script again to verify your fixes\n";
    echo "3. Once all checks pass, start your application\n";
}

// Exit with status code based on check results
exit($allPassed ? 0 : 1);
