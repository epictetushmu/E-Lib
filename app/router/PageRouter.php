<?php 
// require_once('../views/PageRouter.php');
require_once(__DIR__ . '/../includes/ResponseHandler.php');
require_once(__DIR__ . '/../controllers/PageController.php');
require_once(__DIR__ . '/../../vendor/autoload.php'); 
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class PageRouter { 
    private $routes = [];
    private $secretKey = 'your-secret-key'; // Check what this is about

    public function __construct() {
        $this->defineRoutes();
        $this->setSecurityHeaders();
    }

    private function defineRoutes() { 
        $this->routes = [
            ['path' => '/index', 'handler' => [new PageController(), 'home']],
            ['path' => '/', 'handler' => [new PageController(), 'home']],
            ['path' => '/login', 'handler' => [new PageController(), 'loginForm']],
            ['path' => '/signup', 'handler' => [new PageController(), 'signupForm']],
            ['path' => '/book', 'handler' => [new PageController(), 'listBooks']],
            ['path' => '/book/(\d+)', 'handler' => [new PageController(), 'viewBooks']],
            ['path' => '/add-book', 'handler' => [new PageController(), 'addBookForm']],
            ['path' => '/book/(\d+)', 'handler' => [new PageController(), 'updateBook']],
            ['path' => '/search/(\w+)', 'handler' => [new PageController(), 'searchBooks']],
            ['path' => '/error', 'handler' => [new PageController(), 'error']]
        ];            
    }

    public function handleRequest($path) {
        foreach ($this->routes as $route) {
            if (preg_match('#^' . $route['path'] . '$#', $path, $matches)) {
                echo $route['path'];
                call_user_func($route['handler']);
                return;
            }
        }
    
        ResponseHandler::respond(404, "Page not found");
        include __DIR__ . '/../views/404.php'; // Load custom 404 page
    }    

    private function setSecurityHeaders() {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        header('Content-Security-Policy: default-src \'self\'');
    }
}