<?php
ini_set('error_log', dirname(__DIR__) . '/storage/logs/php_errors.log');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED);

// Determine the correct base directory for includes
$projectRoot = realpath(__DIR__ . '/..');
if (!$projectRoot) {
    die("Critical error: Unable to determine project root directory");
}

// Function to safely include a file with proper error handling
function safeRequire($path) {
    if (file_exists($path)) {
        require_once $path;
        return true;
    }
    return false;
}

// Check if we're in GitHub Actions environment
$isGithubActions = getenv('GITHUB_ACTIONS') === 'true';

// Try to include the autoloader first
$autoloadPath = $projectRoot . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

// Define all the required paths
$requiredFiles = [
    '/App/Router/BaseRouter.php',
    '/App/Router/PageRouter.php',
    '/App/Router/ApiRouter.php',
    '/App/Database/DatabaseInterface.php',
    '/App/Database/JsonDatabase.php',
    '/App/Database/MongoDatabase.php',
    '/App/Includes/Environment.php',
    '/App/Integration/Database/JsonDbInteraction.php',
    '/App/Integration/Database/MongoConnectionFactory.php'
];

// Try to include all required files
$missingFiles = [];
foreach ($requiredFiles as $file) {
    $fullPath = $projectRoot . $file;
    if (!safeRequire($fullPath)) {
        $missingFiles[] = $fullPath;
    }
}

// If there are missing files, display error and exit
if (!empty($missingFiles)) {
    echo "<h1>Critical Error: Missing Required Files</h1>";
    echo "<p>The following files could not be found:</p><ul>";
    foreach ($missingFiles as $file) {
        echo "<li>$file</li>";
    }
    echo "</ul>";
    
    echo "<h2>Debug Information</h2>";
    echo "<p>Project Root: $projectRoot</p>";
    echo "<p>Current Directory: " . getcwd() . "</p>";
    echo "<p>Is GitHub Actions: " . ($isGithubActions ? 'Yes' : 'No') . "</p>";
    
    if ($isGithubActions) {
        echo "<h2>GitHub Actions Environment</h2>";
        echo "<p>Directory Listing:</p><pre>";
        // List directories to debug
        echo shell_exec("ls -la $projectRoot");
        echo shell_exec("ls -la $projectRoot/App");
        echo "</pre>";
    }
    
    die();
}

// Load environment variables before any other code runs
App\Includes\Environment::load();

// Add the new integration folder to the manual includes
safeRequire($projectRoot . '/App/Integration/Database/MongoConnectionFactory.php');

// Verify the class exists
if (!class_exists('App\Router\BaseRouter')) {
    die("Critical error: App\\Router\\BaseRouter class not found despite loading file");
}

use App\Includes\SessionManager;
use App\Router\BaseRouter;
use App\Integration\Database\MongoConnectionFactory;
use App\Middleware\AuthMiddleware;
use App\Middleware\LoggingMiddleware;
use App\Middleware\JwtAuthMiddleware;

$baseUrl = ''; // Set your base URL here

// Create database connection with built-in fallback
try {
    $db = MongoConnectionFactory::create('mongo', [
        'fallback' => true,  // Enable automatic fallback to JsonDatabase
    ]);
} catch (\Exception $e) {
    die("Critical error: Unable to establish any database connection: " . $e->getMessage());
}

SessionManager::initialize();
// Create router with database
$router = new BaseRouter($baseUrl);

// require_once __DIR__ . '/../App/bootstrap.php';

// Add middleware
$router->addMiddleware(new LoggingMiddleware());
$router->addMiddleware(new AuthMiddleware([
    '/profile',
    '/add-book',
    '/read/',
    '/api/v1/books' // Only POST requests actually need auth, but we keep it simple here
]));

$router->addMiddleware(new JwtAuthMiddleware([
    '/api/v1/books',
    '/api/v1/reviews',
    '/api/v1/download',
    '/api/v1/user',
]));

// Handle the request
$router->handleRequest();
