<?php
// Include all necessary controllers
require_once('../controllers/BookController.php');
require_once('../controllers/UserController.php');

// Define the router function
function handleRoute() {
    $uri = $_SERVER['REQUEST_URI']; // Get the current URL
    $method = $_SERVER['REQUEST_METHOD']; // Get the HTTP request method (GET or POST)

    // Define routes
    $routes = [
        '/' => ['GET', 'BookController', 'listBooks'],
        '/book/(\d+)' => ['GET', 'BookController', 'viewBook'],
        '/add-book' => ['GET', 'BookController', 'addBookForm'],
        '/add-book' => ['POST', 'BookController', 'addBook'],
        '/login' => ['GET', 'UserController', 'showLoginForm'],
        '/login' => ['POST', 'UserController', 'handleLogin'],
        '/logout' => ['GET', 'UserController', 'handleLogout'],
    ];



    // Loop through the routes and match the URI
    foreach ($routes as $route => $handler) {
        if (preg_match("~^$route$~", $uri, $matches) && $method == $handler[0]) {
            $controller = $handler[1];
            $action = $handler[2];
            $controllerInstance = new $controller;
            call_user_func_array([$controllerInstance, $action], array_slice($matches, 1));
            return;
        }
    }

    // If no route is matched, show a 404 page
    echo '404 - Page not found';
}
