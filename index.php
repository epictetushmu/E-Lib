<?php
require_once 'vendor/autoload.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED);

use App\Router\Router;
$baseUrl = '/E-Lib';
$router = new Router($baseUrl); 
$router->handleRequest();
