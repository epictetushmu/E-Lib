<?php
// Include all necessary controllers
require_once('../controllers/BookController.php');
require_once('../controllers/UserController.php');

class Router {
    private $routes = [];

    public function __construct() {
        $this->defineRoutes();
    }

    private function defineRoutes() {
        $this->routes = [
            ['method' => 'GET', 'path' => '/book', 'handler' => [new BookController(), 'listBooks']],
            ['method' => 'GET', 'path' => '/book/(\d+)', 'handler' => [new BookController(), 'viewBook']],
            ['method' => 'GET', 'path' => '/add-book', 'handler' => [new BookController(), 'addBookForm']],
            ['method' => 'POST', 'path' => '/add-book', 'handler' => [new BookController(), 'addBook']],
            ['method' => 'PUT', 'path' => '/book/(\d+)', 'handler' => [new BookController(), 'updateBook']],
            ['method' => 'GET', 'path' => '/search/(\w+)', 'handler' => [new BookController(), 'searchBooks']],
            // ['method' => 'GET', 'path' => '/login', 'handler' => [new UserController(), 'showLoginForm']],
            ['method' => 'POST', 'path' => '/login', 'handler' => [new UserController(), 'handleLogin']],
            ['method' => 'GET', 'path' => '/logout', 'handler' => [new UserController(), 'handleLogout']],
        ];
    }

    public function handleRoute($method, $path) {
        foreach ($this->routes as $route) {
            if ($route['method'] === $method && preg_match("#^{$route['path']}$#", $path, $matches)) {
                array_shift($matches); // Remove the full match from the matches array
                call_user_func_array($route['handler'], $matches);
                return;
            }
        }
        // Handle 404 Not Found
        http_response_code(404);
        echo "404 Not Found";
    }
}

$router = new Router();
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$router->handleRoute($method, $path);
