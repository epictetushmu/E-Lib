<?php 
namespace App\Router;

use App\Controllers\PageController;
use App\Includes\ResponseHandler;

require_once(__DIR__ . '/../../vendor/autoload.php'); 
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Dotenv\Dotenv;

class PageRouter { 
    private $routes = [];
    private $secretKey;

    public function __construct() {
        // Load environment variables
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();

        // Set the secret key from the environment variable
        $this->secretKey = $_ENV['SECRET_KEY'];

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
            ['path' => '/book/(\d+)', 'handler' => [new PageController(), 'viewBook']],
            ['path' => '/add-book', 'handler' => [new PageController(), 'addBookForm']],
            ['path' => '/book/(\d+)', 'handler' => [new PageController(), 'updateBook']],
            ['path' => '/search_results', 'handler' => [new PageController(), 'searchBooks']],
            ['path' => '/error', 'handler' => [new PageController(), 'error']]
        ];            
    }

    public function handleRequest($path) {
        foreach ($this->routes as $route) {
            if (preg_match('#^' . $route['path'] . '$#', $path, $matches)) {
                call_user_func_array($route['handler'], $matches);
                return;
            }
        }
    
        ResponseHandler::respond(404, "Page not found");
        include __DIR__ . '/../views/404.php'; // Load custom 404 page
    }    

    private function setSecurityHeaders() {
        // Security headers
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; font-src 'self' https://cdnjs.cloudflare.com; img-src 'self' data: https://cdn.jsdelivr.net https://cdnjs.cloudflare.com;");

        // API-specific headers
        header('Access-Control-Allow-Origin: *'); 
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization'); 
        header('Content-Type: application/json');
    }
}