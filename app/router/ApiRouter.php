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
            ['method' => 'GET', 'path' => '/api/featured-books', 'handler' => [new BookController(), 'getFeaturedBooks']],
            ['method' => 'GET', 'path' => '/api/books', 'handler' => [new BookController(), 'getAllBooks']],
            ['method' => 'GET', 'path' => '/api/books/(\d+)', 'handler' => [new BookController(), 'getBookDetails']],
            ['method' => 'POST', 'path' => '/api/books', 'handler' => [new BookController(), 'addBook']],
            ['method' => 'PUT', 'path' => '/api/books/(\d+)', 'handler' => [new BookController(), 'updateBook']],
            ['method' => 'GET', 'path' => '/api/search', 'handler' => [new BookController(), 'searchBooks']],
            ['method' => 'POST', 'path' => '/api/login', 'handler' => [new UserController(), 'login']],
            ['method' => 'POST', 'path' => '/api/signup', 'handler' => [new UserController(), 'signup']],
            ['method' => 'GET', 'path' => '/api/logout', 'handler' => [new UserController(), 'logout']],
            ['method' => 'POST', 'path' => '/api/borrow', 'handler' => [new BookController(), 'borrowBook']],
            ['method' => 'POST', 'path' => '/api/save-book', 'handler' => [new BookController(), 'saveToList']],
            ['method' => 'POST', 'path' => '/api/review', 'handler' => [new BookController(), 'addReview']],
            ['method' => 'POST', 'path' => '/api/add_book', 'handler' => [new BookController(), 'addBook']],
        ];
    }

    public function handleRequest($method, $path) {
        // Strip /api/v1 prefix if present to maintain compatibility with older paths
        if (strpos($path, '/api/v1') === 0) {
            $path = str_replace('/api/v1', '/api', $path);
        }

        foreach ($this->routes as $route) {
            if ($route['method'] === $method && preg_match("#^{$route['path']}$#", $path, $matches)) {
                array_shift($matches); // Remove the full match from the matches array
                call_user_func_array($route['handler'], $matches);
                return;
            }
        }
        
        // Set appropriate headers for API responses
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'API endpoint not found']);
    }
}
