<?php

namespace App\Router;

// Include all necessary controllers
use App\Controllers\AnnotationController;
use App\Controllers\BookController;
use App\Controllers\UserController;
use App\Includes\ResponseHandler;

class ApiRouter
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $routes = [];

    public function __construct()
    {
        $this->defineRequests();
    }

    private function defineRequests(): void
    {
        $this->routes = [
            ['method' => 'GET', 'path' => '/api/v1/books', 'handler' => [new BookController(), 'getAllBooks']],
            ['method' => 'POST', 'path' => '/api/v1/books', 'handler' => [new BookController(), 'addBook']],
            ['method' => 'GET', 'path' => '/api/v1/books/list', 'handler' => [new BookController(), 'listBooks']],
            ['method' => 'GET', 'path' => '/api/v1/books/featured', 'handler' => [new BookController(), 'featuredBooks']],
            ['method' => 'GET', 'path' => '/api/v1/books/categories', 'handler' => [new BookController(), 'getCategories']],
            ['method' => 'POST', 'path' => '/api/v1/books/upload', 'handler' => [new BookController(), 'uploadBooks']],
            ['method' => 'GET', 'path' => '/api/v1/books/([0-9a-f]{24})', 'handler' => [new BookController(), 'viewBook']],
            ['method' => 'PUT', 'path' => '/api/v1/books/([0-9a-f]{24})', 'handler' => [new BookController(), 'updateBook']],
            ['method' => 'DELETE', 'path' => '/api/v1/books/([0-9a-f]{24})', 'handler' => [new BookController(), 'deleteBook']],

            // New API routes for secure PDF viewing
            ['method' => 'GET', 'path' => '/api/v1/books/([0-9a-f]{24})/file', 'handler' => [new BookController(), 'streamBookFile']],
            [
                'method' => 'GET',
                'path' => '/api/v1/books/([0-9a-f]{24})/thumbnail',
                'handler' => [new BookController(), 'streamBookThumbnail'],
            ],

            ['method' => 'GET', 'path' => '/api/v1/search/([^/]+)', 'handler' => [new BookController(), 'searchBooks']],
            ['method' => 'GET', 'path' => '/api/v1/content-search', 'handler' => [new BookController(), 'contentSearch']],
            ['method' => 'GET', 'path' => '/api/v1/features', 'handler' => [new BookController(), 'features']],
            ['method' => 'GET', 'path' => '/api/v1/catalog', 'handler' => [new BookController(), 'catalog']],

            ['method' => 'POST', 'path' => '/api/v1/reviews', 'handler' => [new BookController(), 'saveReview']],
            ['method' => 'GET', 'path' => '/api/v1/reviews/([0-9a-f]{24})', 'handler' => [new BookController(), 'getReviews']],

            // The signed-in user's private highlights and notes
            [
                'method' => 'GET',
                'path' => '/api/v1/books/([0-9a-f]{24})/annotations',
                'handler' => [new AnnotationController(), 'listForBook'],
            ],
            [
                'method' => 'POST',
                'path' => '/api/v1/books/([0-9a-f]{24})/annotations',
                'handler' => [new AnnotationController(), 'create'],
            ],
            ['method' => 'PUT', 'path' => '/api/v1/annotations/([0-9a-f]{24})', 'handler' => [new AnnotationController(), 'update']],
            ['method' => 'DELETE', 'path' => '/api/v1/annotations/([0-9a-f]{24})', 'handler' => [new AnnotationController(), 'delete']],

            ['method' => 'GET', 'path' => '/api/v1/books/([0-9a-f]{24})/download', 'handler' => [new BookController(), 'downloadBook']],

            ['method' => 'POST', 'path' => '/api/v1/signup', 'handler' => [new UserController(), 'handleSignup']],
            ['method' => 'POST', 'path' => '/api/v1/login', 'handler' => [new UserController(), 'handleLogin']],
            ['method' => 'GET', 'path' => '/api/v1/logout', 'handler' => [new UserController(), 'handleLogout']],

            ['method' => 'GET', 'path' => '/api/v1/user/profile', 'handler' => [new UserController(), 'getProfile']],
            ['method' => 'GET', 'path' => '/api/v1/user', 'handler' => [new UserController(), 'getUser']],
            ['method' => 'POST', 'path' => '/api/v1/save-book', 'handler' => [new UserController(), 'saveBook']],
            ['method' => 'POST', 'path' => '/api/v1/remove-book', 'handler' => [new UserController(), 'removeBook']],
            ['method' => 'GET', 'path' => '/api/v1/saved-books', 'handler' => [new UserController(), 'getSavedBooks']],
            ['method' => 'GET', 'path' => '/api/v1/downloaded-books', 'handler' => [new UserController(), 'getDownloadedBooks']],
            ['method' => 'GET', 'path' => '/api/v1/admin/metadata-lookup', 'handler' => [new BookController(), 'metadataLookup']],
            ['method' => 'GET', 'path' => '/api/v1/admin/logs', 'handler' => [new UserController(), 'viewLogs']],
            ['method' => 'POST', 'path' => '/api/v1/update-profile', 'handler' => [new UserController(), 'updateProfile']],
            ['method' => 'POST', 'path' => '/api/v1/change-password', 'handler' => [new UserController(), 'changePassword']],
            ['method' => 'POST', 'path' => '/api/v1/support', 'handler' => [new UserController(), 'support']]
        ];
    }

    public function handleRequest(string $method, string $path): void
    {

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
