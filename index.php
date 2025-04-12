<?php
require_once 'vendor/autoload.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED);

use App\Router\Router;
$baseUrl = '/localhost:5500'; // Set your base URL here
$router = new Router($baseUrl); 
$router->handleRequest();
