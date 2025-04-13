<?php
// filepath: /home/makis/Documents/GenUni/Coding/Web/E-Lib/public/index.php
// Instead of requiring the autoloader, use manual includes
//with composer dump-autoload 
// require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../App/Router/PageRouter.php';

$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
} else {
    // Fallback to manual includes
    require_once __DIR__ . '/../App/Router/PageRouter.php';
    require_once __DIR__ . '/../App/Router/ApiRouter.php';
    require_once __DIR__ . '/../App/Router/BaseRouter.php';
    require_once __DIR__ . '/../App/includes/MongoDb.php';
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED);

use App\Router\BaseRouter;
$baseUrl = ''; // Set your base URL here
$router = new BaseRouter($baseUrl); 
$router->handleRequest();
