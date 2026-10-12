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

// Try to include the autoloader first
$autoloadPath = $projectRoot . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
} else {
    die("Composer autoloader not found. Please run 'composer install'");
}

// Load .env before any code reads MONGO_URI, JWT keys, etc.
App\Includes\Environment::load($projectRoot . '/.env');

// MongoDB is required; no JSON fallback
try {
    App\Repository\DatabaseRepository::getInstance();
} catch (Throwable $e) {
    error_log('Database initialization error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(503);
    $detail = '';
    $appDebug = strtolower((string) App\Includes\Environment::get('APP_DEBUG', ''));
    $debug = in_array($appDebug, ['true', '1', 'yes'], true)
        || (string) App\Includes\Environment::get('APP_ENV', '') === 'development';
    if ($debug) {
        $detail = ' ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    die(
        'Database error: MongoDB is required but could not be reached. ' .
        'Verify MONGO_URI placeholders (<db_password> or ${MONGO_PASSWORD}) and MONGO_PASSWORD in .env, Atlas IP allowlist, and DNS. ' .
        'Run: php scripts/mongo-ping.php. See storage/logs/php_errors.log.' . $detail
    );
}

use App\Includes\SessionManager;
use App\Router\BaseRouter;
use App\Middleware\LoggingMiddleware;
use App\Middleware\JwtAuthMiddleware;

$baseUrl = ''; // Set your base URL here

SessionManager::initialize();
// Create router with database
$router = new BaseRouter($baseUrl);

// Add middleware
$router->addMiddleware(new LoggingMiddleware());

// Page routes are all handled client-side by the SPA now (PageRouter just serves the shell);
// only API endpoints need server-side auth, via JwtAuthMiddleware below.
$router->addMiddleware(new JwtAuthMiddleware([
    // Protect user-specific and admin API endpoints
    ['path' => '/api/v1/user', 'method' => 'GET'],
    ['path' => '/api/v1/remove-book', 'method' => 'POST'],
    ['path' => '/api/v1/save-book', 'method' => 'POST'],
    ['path' => '/api/v1/saved-books', 'method' => 'GET'],
    ['path' => '/api/v1/downloaded-books', 'method' => 'GET'],
    ['path' => '/api/v1/reviews', 'method' => 'POST'],
    ['path' => '/api/v1/reviews', 'method' => 'DELETE'],
    ['path' => '/api/v1/replies', 'method' => 'PUT'],
    ['path' => '/api/v1/replies', 'method' => 'DELETE'],
    ['path' => '/api/v1/books/([0-9a-f]{24})/download', 'method' => 'GET'],
    ['path' => '/api/v1/books/([0-9a-f]{24})/borrow', 'method' => 'POST'],
    ['path' => '/api/v1/books/([0-9a-f]{24})/return', 'method' => 'POST'],
    // Private highlights and notes
    ['path' => '/api/v1/books/([0-9a-f]{24})/annotations', 'method' => 'GET'],
    ['path' => '/api/v1/books/([0-9a-f]{24})/annotations', 'method' => 'POST'],
    ['path' => '/api/v1/annotations', 'method' => 'PUT'],
    ['path' => '/api/v1/annotations', 'method' => 'DELETE'],
    // Friends (prefix match covers /api/v1/friends/{id}/accept and /library)
    ['path' => '/api/v1/friends', 'method' => 'GET'],
    ['path' => '/api/v1/friends', 'method' => 'POST'],
    ['path' => '/api/v1/friends', 'method' => 'DELETE'],
    ['path' => '/api/v1/admin/logs', 'method' => 'GET'],
    ['path' => '/api/v1/admin/loans', 'method' => 'GET'],
    ['path' => '/api/v1/admin/loans', 'method' => 'POST'],
    ['path' => '/api/v1/user/notifications/read', 'method' => 'POST'],
    ['path' => '/api/v1/admin/metadata-lookup', 'method' => 'GET'],
    ['path' => '/api/v1/update-profile', 'method' => 'POST'],
    ['path' => '/api/v1/change-password', 'method' => 'POST'],
    // Book management endpoints (moved from session-based AuthMiddleware for SPA/JWT auth;
    // prefix match also covers /api/v1/books/upload and /api/v1/books/{id})
    ['path' => '/api/v1/books', 'method' => 'POST'],
    ['path' => '/api/v1/books', 'method' => 'PUT'],
    ['path' => '/api/v1/books', 'method' => 'DELETE'],

    // ...add more as needed
]));

// Handle the request
$router->handleRequest();
