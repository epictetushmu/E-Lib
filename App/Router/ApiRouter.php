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
            ['method' => 'GET', 'path' => '/api/v1/books', 'handler' => [new BookController(), 'getAllBooks']],
            ['method' => 'POST', 'path' => '/api/v1/books', 'handler' => [new BookController(), 'addBook']],
            ['method' => 'GET', 'path' => '/api/v1/books/list', 'handler' => [new BookController(), 'listBooks']],
            ['method' => 'GET', 'path' => '/api/v1/books/featured', 'handler' => [new BookController(), 'featuredBooks']],
            ['method' => 'POST', 'path' => '/api/v1/books/mass-upload', 'handler' => [new BookController(), 'massUploadBooks']],
            ['method' => 'GET', 'path' => '/api/v1/books/([0-9a-f]{24})', 'handler' => [new BookController(), 'viewBook']],
            ['method' => 'PUT', 'path' => '/api/v1/books/([0-9a-f]{24})', 'handler' => [new BookController(), 'updateBook']],
            ['method' => 'DELETE', 'path' => '/api/v1/books/([0-9a-f]{24})', 'handler' => [new BookController(), 'deleteBook']],
            
            // New API routes for secure PDF viewing
            ['method' => 'GET', 'path' => '/api/v1/books/([0-9a-f]{24})/file', 'handler' => [new BookController(), 'streamBookFile']],
            
            ['method' => 'GET', 'path' => '/api/v1/search/(\w+)', 'handler' => [new BookController(), 'searchBooks']],
            
            ['method' => 'POST', 'path' => '/api/v1/reviews', 'handler' => [new BookController(), 'addReview']],
            ['method' => 'GET', 'path' => '/api/v1/reviews/([0-9a-f]{24})', 'handler' => [new BookController(), 'getReviews']],
            
            ['method' => 'GET', 'path' => '/api/v1/books/([0-9a-f]{24})/download', 'handler' => [new BookController(), 'downloadBook']],
            
            ['method' => 'POST', 'path' => '/api/v1/signup', 'handler' => [new UserController(), 'handleSignup']],
            ['method' => 'POST', 'path' => '/api/v1/login', 'handler' => [new UserController(), 'handleLogin']],
            ['method' => 'GET', 'path' => '/api/v1/logout', 'handler' => [new UserController(), 'handleLogout']],
            
            ['method' => 'GET', 'path' => '/api/v1/user', 'handler' => [new UserController(), 'getUser']],
            ['method' => 'POST', 'path' => '/api/v1/save-book', 'handler' => [new UserController(), 'saveBook']],
            ['method' => 'POST', 'path' => '/api/v1/remove-book', 'handler' => [new UserController(), 'removeBook']],
            ['method' => 'GET', 'path' => '/api/v1/saved-books', 'handler' => [new UserController(), 'getSavedBooks']],
            ['method' => 'GET', 'path' => '/api/v1/admin/logs', 'handler' => [new UserController(), 'viewLogs']],
            ['method' => 'POST', 'path' => '/api/v1/update-profile', 'handler' => [new UserController(), 'updateProfile']],
            ['method' => 'POST', 'path' => '/api/v1/support', 'handler' => [new UserController(), 'support']]
        ];
    }

    public function handleRequest($method, $path) {
        
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
