<?php
namespace E_Lib\Router;
// Include all necessary controllers
use E_Lib\Controllers\BookController;
use E_Lib\Controllers\UserController;
use E_Lib\Includes\ResponseHandler;

class ApiRouter {
    private $routes = [];

    public function __construct() {
        $this->defineRequests();
    }

    private function defineRequests() {
        $this->routes = [
            ['method' => 'GET', 'path' => '/api/v1/featured-books', 'handler' => [new BookController(), 'featuredBooks']],
            ['method' => 'GET', 'path' => '/api/v1/books', 'handler' => [new BookController(), 'listBooks']],
            ['method' => 'GET', 'path' => '/api/v1/books/(\d+)', 'handler' => [new BookController(), 'viewBook']],
            ['method' => 'POST', 'path' => '/api/v1/books', 'handler' => [new BookController(), 'addBook']],
            ['method' => 'PUT', 'path' => '/api/v1/books/(\d+)', 'handler' => [new BookController(), 'updateBook']],
            ['method' => 'GET', 'path' => '/api/v1/featured', 'handler' => [new BookController(), 'featuredBooks']],
            ['method' => 'GET', 'path' => '/api/v1/search/(\w+)', 'handler' => [new BookController(), 'searchBooks']],
            ['method' => 'POST', 'path' => '/api/v1/login', 'handler' => [new UserController(), 'handleLogin']],
            ['method' => 'GET', 'path' => '/api/v1/logout', 'handler' => [new UserController(), 'handleLogout']],
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
