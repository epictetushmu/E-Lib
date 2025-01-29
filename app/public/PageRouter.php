<?php
// Include controllers if needed
require_once('../controllers/PageController.php');
require_once('../controllers/AuthController.php');
require_once('../vendor/autoload.php'); 
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class PageRouter {
    private $routes = [];
    private $secretKey = 'your-secret-key';     //Check what this is about
    public function __construct() {
        $this->defineRoutes();
        $this->setSecurityHeaders();
    }

    private function defineRoutes() {
        $this->routes = [
            ['method' => 'GET', 'path' => '/', 'handler' => [new PageController(), 'home']],
            ['method' => 'GET', 'path' => '/about', 'handler' => [new PageController(), 'about']],
            ['method' => 'GET', 'path' => '/contact', 'handler' => [new PageController(), 'contact']],
            ['method' => 'GET', 'path' => '/login', 'handler' => [new PageController(), 'login']],
            ['method' => 'GET', 'path' => '/register', 'handler' => [new PageController(), 'register']],
        ];
    }

    private function setSecurityHeaders() {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        header('Content-Security-Policy: default-src \'self\'');
    }

    private function validateJWT($token) {
        try {
            $decoded = JWT::decode($token, new Key($this->secretKey, 'HS256'));
            return (array) $decoded;
        } catch (Exception $e) {
            return null;
        }
    }

    public function handleRoute($method, $path) {
        foreach ($this->routes as $route) {
            if ($route['method'] === $method && preg_match("#^{$route['path']}$#", $path, $matches)) {
                array_shift($matches); // Remove the full match from the matches array
                try {
                    if ($route['path'] !== '/auth') {
                        $headers = getallheaders();
                        if (!isset($headers['Authorization'])) {
                            throw new Exception('Authorization header not found');
                        }
                        $token = str_replace('Bearer ', '', $headers['Authorization']);
                        $userData = $this->validateJWT($token);
                        if (!$userData) {
                            throw new Exception('Invalid token');
                        }
                    }
                    call_user_func_array($route['handler'], $matches);
                } catch (Exception $e) {
                    // Handle the exception and return a 500 Internal Server Error
                    http_response_code(500);
                    echo "500 Internal Server Error: " . $e->getMessage();
                }
                return;
            }
        }
        // Handle 404 Not Found
        http_response_code(404);
        echo "404 Not Found";
    }
}

$router = new PageRouter();
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$router->handleRoute($method, $path);
