<?php 
namespace App\Router;

use App\Controllers\PageController;
use App\Includes\Environment;
use App\Includes\ResponseHandler;
use App\Services\CasService;

// require_once(__DIR__ . '/../../vendor/autoload.php'); 
// use Firebase\JWT\JWT;
// use Firebase\JWT\Key;

class PageRouter { 
    private $routes = [];
    private $casService;

    public function __construct() {
        $this->defineRoutes();
        $this->casService = new CasService();
        // Remove the setSecurityHeaders call from constructor - will call it at the right time
    }

    private function defineRoutes() { 
        $this->routes = [
            ['path' => '/index', 'handler' => [new PageController(), 'home']],
            ['path' => '/', 'handler' => [new PageController(), 'home']],
            ['path' => '/view-books', 'handler' => [new PageController(), 'viewBooks']],
            ['path' => '/profile', 'handler' => [new PageController(), 'profile']],
            ['path' => '/read/([0-9a-f]{24})', 'handler' => [new PageController(), 'readBook']],
            ['path' => '/book/([0-9a-f]{24})', 'handler' => [new PageController(), 'viewBook']],
            ['path' => '/add-book', 'handler' => [new PageController(), 'addBookForm']],
            ['path' => '/search_results', 'handler' => [new PageController(), 'searchBooks']],
            ['path' => '/error', 'handler' => [new PageController(), 'error']],
            ['path' => '/dashboard', 'handler' => [new PageController(), 'dashboard']],
            ['path' => '/signup', 'handler' => [new PageController(), 'home', ['showSignup' => true]]],
            ['path' => '/admin/logs', 'handler' => [new PageController(), 'viewLogs']],
            ['path' => '/docs', 'handler' => [new PageController(), 'docs']],
        ];            
    }

    public function handleRequest($path) {
        // Set security headers at the beginning of request handling
        // but only if no output has been sent yet
        if (!headers_sent()) {
            $this->setSecurityHeaders();
        }
        
        $pathOnly = parse_url($path, PHP_URL_PATH);
        
        // CAS login was previously handled on the /login path
        // Now we'll handle it differently
        if (strpos($pathOnly, '/cas-login') === 0) {
            $ticket = $_GET['ticket'] ?? null;
            // Use Environment to get the application URL
            $serviceUrl = Environment::get('APP_URL', 'http://localhost:8080') . '/cas-login';

            if ($ticket && $this->casService->authenticate($ticket, $serviceUrl)) {
                // Redirect to home with a success parameter for the UI to handle
                if (!headers_sent()) {
                    header('Location: /?login=success');
                    exit;
                }
            } else {
                // Redirect to home with error parameter
                if (!headers_sent()) {
                    header('Location: /?login=failed');
                    exit;
                }
            }
            return;
        }

       
    foreach ($this->routes as $route) {
        if (preg_match('#^' . $route['path'] . '$#', $pathOnly, $matches)) {
            call_user_func_array($route['handler'], $matches);
            return;
        }
    }
        
        $pageController = new PageController();
        $pageController->error();    
    }    

    private function setSecurityHeaders() {
        // Security headers
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        
        // Updated CSP for icons, Bootstrap, SweetAlert2, etc.
        header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; font-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; img-src 'self' data: https://cdn.jsdelivr.net https://cdnjs.cloudflare.com;");
    
        // Remove content-type JSON header since this is for HTML pages
        // Only set CORS headers
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        
        // Don't set Content-Type header here as it should be different for HTML vs JSON responses
    }
    
}