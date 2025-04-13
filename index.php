<?php
require_once 'vendor/autoload.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use App\Router\BaseRouter;
$baseUrl = '/E-Lib';
$router = new BaseRouter($baseUrl); 
$router->handleRequest();
