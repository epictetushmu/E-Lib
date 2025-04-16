<?php
namespace App\Router;

use App\Router\ApiRouter;
use App\Router\PageRouter;


class BaseRouter {
    private $apiRouter;
    private $pageRouter;
    private $baseUrl;

    /**
     * Constructor
     * 
     * @param string $baseUrl Base URL for the application
     * @param mixed $database Database instance
     */
    public function __construct($baseUrl = '')
    {
        $this->baseUrl = $baseUrl;
        $this->apiRouter = new ApiRouter();
        $this->pageRouter = new PageRouter();
    }

    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        if (strpos($path, $this->baseUrl) === 0) {
            $path = substr($path, strlen($this->baseUrl));
        }
        
        if (strpos($path, '/api') === 0) {
            $this->apiRouter->handleRequest($method, $path);
        } else {
            $this->pageRouter->handleRequest($path);
        }
    }
}

