<?php
namespace App\Router;

use App\Router\ApiRouter;
use App\Router\PageRouter;
use App\Middleware\MiddlewareManager;

class BaseRouter {
    private $apiRouter;
    private $pageRouter;
    private $baseUrl;
    private $middlewareManager;

    /**
     * Constructor
     * 
     * @param string $baseUrl Base URL for the application
     */
    public function __construct($baseUrl = '')
    {
        $this->baseUrl = $baseUrl;
        $this->apiRouter = new ApiRouter();
        $this->pageRouter = new PageRouter();
        $this->middlewareManager = new MiddlewareManager();
    }

    /**
     * Add middleware to the stack
     *
     * @param \App\Middleware\MiddlewareInterface $middleware
     * @return $this
     */
    public function addMiddleware($middleware) {
        $this->middlewareManager->add($middleware);
        return $this;
    }

    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        if (strpos($path, $this->baseUrl) === 0) {
            $path = substr($path, strlen($this->baseUrl));
        }
        
        // Create request array for middleware
        $request = [
            'method' => $method,
            'path' => $path,
            'isApi' => strpos($path, '/api') === 0
        ];
        
        // Run middleware stack with appropriate router as final handler
        $this->middlewareManager->run($request, function($request) {
            if ($request['isApi']) {
                $this->apiRouter->handleRequest($request['method'], $request['path']);
            } else {
                $this->pageRouter->handleRequest($request['path']);
            }
        });
    }
}

