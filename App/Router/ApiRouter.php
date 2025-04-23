<?php
namespace App\Router;
// Include all necessary controllers
use App\Controllers\BookController;
use App\Controllers\UserController;
use App\Includes\ResponseHandler;

class ApiRouter {
    private $routes = [];

    public function __construct() {
        $this->defineRequests();
    }

    private function defineRequests() {
        $this->routes = [
            ['method' => 'GET', 'path' => '/api/v1/featured-books', 'handler' => [new BookController(), 'featuredBooks']],
            ['method' => 'GET', 'path' => '/api/v1/books', 'handler' => [new BookController(), 'listBooks']],
            ['method' => 'GET', 'path' => '/api/v1/books/([0-9a-f]{24})', 'handler' => [new BookController(), 'viewBook']],
            ['method' => 'POST', 'path' => '/api/v1/books', 'handler' => [new BookController(), 'addBook']],
            ['method' => 'PUT', 'path' => '/api/v1/books/([0-9a-f]{24})', 'handler' => [new BookController(), 'updateBook']],
            ['method' => 'GET', 'path' => '/api/v1/featured', 'handler' => [new BookController(), 'featuredBooks']],
            ['method' => 'GET', 'path' => '/api/v1/search/(\w+)', 'handler' => [new BookController(), 'searchBooks']],
            ['method' => 'POST', 'path' => '/api/v1/login', 'handler' => [new UserController(), 'handleLogin']],
            ['method' => 'POST', 'path' => '/api/v1/signup', 'handler' => [new UserController(), 'handleSignup']],
            ['method' => 'GET', 'path' => '/api/v1/user', 'handler' => [new UserController(), 'getUser']],
            ['method' => 'GET', 'path' => '/api/v1/logout', 'handler' => [new UserController(), 'handleLogout']],
        ];
    }

    public function handleRequest($method, $path) {
        // Debugging output
        // echo "Method: $method, Path: $path";

        foreach ($this->routes as $route) {
            if ($route['method'] === $method && preg_match("#^{$route['path']}$#", $path, $matches)) {
                array_shift($matches); // Remove the full match from the matches array
                call_user_func_array($route['handler'], $matches);
                return;
            }
        }
        ResponseHandler::respond(false, 'Not Found', 404);
    }
}
