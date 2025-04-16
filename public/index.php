<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED);

// filepath: /home/makis/Documents/GenUni/Coding/Web/E-Lib/public/index.php
// Instead of requiring the autoloader, use manual includes
//with composer dump-autoload 
// require_once __DIR__ . '/../vendor/autoload.php';
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

// Always load these files directly regardless of autoloader
$baseRouterPath = __DIR__ . '/../App/Router/BaseRouter.php';
if (file_exists($baseRouterPath)) {
    require_once $baseRouterPath;
} else {
    die("Critical error: BaseRouter.php not found at: $baseRouterPath");
}

require_once __DIR__ . '/../App/Router/PageRouter.php';
require_once __DIR__ . '/../App/Router/ApiRouter.php';
require_once __DIR__ . '/../App/Includes/DatabaseInterface.php';
require_once __DIR__ . '/../App/Database/JsonDatabase.php';
require_once __DIR__ . '/../App/Database/MongoDatabase.php';
require_once __DIR__ . '/../App/Includes/Environment.php';
require_once __DIR__ . '/../App/Includes/JsonDbInteraction.php';
require_once __DIR__ . '/../App/Integration/DatabaseConnectionFactory.php';
require_once __DIR__ . '/../App/Integration/Database/MongoConnectionFactory.php';

// Load environment variables before any other code runs
App\Includes\Environment::load();

// Add the new integration folder to the manual includes
require_once __DIR__ . '/../App/Integration/Database/MongoConnectionFactory.php';

// Verify the class exists
if (!class_exists('App\Router\BaseRouter')) {
    die("Critical error: App\\Router\\BaseRouter class not found despite loading file");
}

use App\Router\BaseRouter;
use App\Integration\Database\MongoConnectionFactory;

$baseUrl = ''; // Set your base URL here

// Create database connection with built-in fallback
try {
    $db = MongoConnectionFactory::create('mongo', [
        'fallback' => true,  // Enable automatic fallback to JsonDatabase
    ]);
} catch (\Exception $e) {
    die("Critical error: Unable to establish any database connection: " . $e->getMessage());
}

// Create router with database
$router = new BaseRouter($baseUrl, $db);

$router->handleRequest();
