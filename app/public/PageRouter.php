<?php
// Include controllers if needed
require_once('../controllers/PageController.php');

class PageRouter {
    private $routes = [];

    public function __construct() {
        $this->defineRoutes();
    }

    private function defineRoutes() {
        $this->routes = [
            ['path' => '/', 'handler' => [new PageController(), 'home']],
            ['path' => '/about', 'handler' => [new PageController(), 'about']],
            ['path' => '/contact', 'handler' => [new PageController(), 'contact']],
            ['path' => '/login', 'handler' => [new PageController(), 'login']],
            ['path' => '/register', 'handler' => [new PageController(), 'register']],
        ];
    }

    public function handleRoute($path) {
        foreach ($this->routes as $route) {
            if ($route['path'] === $path) {
                call_user_func($route['handler']);
                return;
            }
        }
        
        // Handle 404 Not Found
        http_response_code(404);
        include('../views/404.php'); // Load a custom 404 page
    }
}

$router = new PageRouter();
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$router->handleRoute($path);
