<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED);

// filepath: /home/makis/Documents/GenUni/Coding/Web/E_Lib/public/index.php
// Instead of requiring the autoloader, use manual includes
//with composer dump-autoload 
// require_once __DIR__ . '/../vendor/autoload.php';
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

// Always load these files directly regardless of autoloader
$baseRouterPath = __DIR__ . '/../Router/BaseRouter.php';
if (file_exists($baseRouterPath)) {
    require_once $baseRouterPath;
} else {
    die("Critical error: BaseRouter.php not found at: $baseRouterPath");
}

require_once __DIR__ . '/../Router/PageRouter.php';
require_once __DIR__ . '/../Router/ApiRouter.php';
require_once __DIR__ . '/../Includes/MongoDb.php';

// Verify the class exists
if (!class_exists('Router\BaseRouter')) {
    die("Critical error: Router\\BaseRouter class not found despite loading file");
}

use Router\BaseRouter;
$baseUrl = ''; // Set your base URL here
$router = new BaseRouter($baseUrl); 
$router->handleRequest();
