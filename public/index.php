<?php
require_once('../router/Router.php');

$router = new Router(); 
$router->handleRequest();
include('./index.php')