<?php 
require_once('../views/PageRouter.php');
require_once('../includes/ResponseHandler.php');
require_once('../vendor/autoload.php'); 
use Firebase\JWT\JWT;
use Firebase\JWT\Key;


class PageRouter{ 

    private $routes = [];

    private $secretKey = 'your-secret-key';     //Check what this is about


    public function __construct(){
        $this->defineRoutes();
        $this->setSecurityHeaders();

    }

    private function defineRoutes(){ 
        $this->routes = [
            ['path' =>'/', 'handler' => [new PageRouter(), 'home']],
            ['path' => '/login', 'handler' => [new PageRouter(), 'loginForm']],
            ['path'=> '/signup', 'handler' => [new PageRouter(), 'signupForm']],
            ['path' => '/book', 'handler' => [new PageRouter(), 'listBooks']],
            ['path' => '/book/(\d+)', 'handler' => [new PageRouter(), 'viewBooks']],
            ['path' => '/add-book', 'handler' => [new PageRouter(), 'addBookForm']],
            ['path' => '/book/(\d+)', 'handler' => [new PageRouter(), 'updateBook']],
            ['path' => '/search/(\w+)', 'handler' => [new PageRouter(), 'searchBooks']],
        ];            
    }

    public function handleRequest($path) {
        foreach ($this->routes as $route) {
            if ($route['path'] === $path) {
                call_user_func($route['handler']);
                return;
            }
        }
        
        ResponseHandler::respond($path,  'Page not found');
        include('../views/404.php'); // Load a custom 404 page
    }

    private function setSecurityHeaders() {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        header('Content-Security-Policy: default-src \'self\'');
    }
    
}