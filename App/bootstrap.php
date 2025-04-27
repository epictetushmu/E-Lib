<?php
/**
 * Application Bootstrap
 * 
 * This file initializes output buffering to prevent "headers already sent" errors
 * and configures basic application settings.
 */

// Start output buffering to prevent "headers already sent" errors
ob_start();

// Set error handling for production environment
$isProduction = (getenv('APP_ENV') === 'production');

// Set appropriate error reporting based on environment
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../storage/logs/php_errors.log');

// Only show errors in development
if (!$isProduction) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
}

// Set timezone if needed
date_default_timezone_set('UTC');

// Ensure storage directories exist and are writable
$logDir = __DIR__ . '/../storage/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

// Additional application initialization
$jwtKey = getenv('JWT_SECRET_KEY');
if (!$jwtKey) {
    // Only use this hardcoded key in development
    define('JWT_SECRET_KEY', 'your-secret-key-for-development-only');
    if ($isProduction) {
        error_log('WARNING: JWT_SECRET_KEY environment variable is not set in production!');
    }
} else {
    define('JWT_SECRET_KEY', $jwtKey);
}

// Initialize error handling to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log(sprintf(
            "FATAL ERROR: %s in %s on line %d",
            $error['message'],
            $error['file'],
            $error['line']
        ));
    }
});