<?php
// Include all necessary controllers
require_once(__DIR__ . '/../controllers/BookController.php');
require_once(__DIR__ . '/../controllers/UserController.php');
require_once(__DIR__ . '/../includes/ResponseHandler.php');

class ApiRouter {
    private $routes = [];

    public function __construct() {
        $this->defineRequests();
    }

    private function defineRequests() {
        $this->routes = [
            ['method' => 'GET', 'path' => '/api/featured-books', 'handler' => [new BookController(), 'featuredBooks']],
            ['method' => 'GET', 'path' => '/api/book', 'handler' => [new BookController(), 'listBooks']],
            ['method' => 'GET', 'path' => '/api/book/(\d+)', 'handler' => [new BookController(), 'viewBook']],
            ['method' => 'POST', 'path' => '/api/add-book', 'handler' => [new BookController(), 'addBook']],
            ['method' => 'PUT', 'path' => '/api/book/(\d+)', 'handler' => [new BookController(), 'updateBook']],
            ['method' => 'GET', 'path' => '/api/search/(\w+)', 'handler' => [new BookController(), 'searchBooks']],
            ['method' => 'POST', 'path' => '/api/login', 'handler' => [new UserController(), 'handleLogin']],
            ['method' => 'GET', 'path' => '/api/logout', 'handler' => [new UserController(), 'handleLogout']],
            ['method' => 'GET', 'path' => '/api/featured', 'handler' => [new BookController(), 'featuredBooks']]
        ];
    }

    public function handleRequest($method, $path) {
        // Debugging output
        echo "Method: $method, Path: $path";

        foreach ($this->routes as $route) {
            if ($route['method'] === $method && preg_match("#^{$route['path']}$#", $path, $matches)) {
                array_shift($matches); // Remove the full match from the matches array
                call_user_func_array($route['handler'], $matches);
                return;
            }
        }
        ResponseHandler::respond(404, 'Not Found');
    }
}
