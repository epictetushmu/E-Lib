<?php
namespace App\Router;

use App\Router\ApiRouter;
use App\Router\PageRouter;


class Router {
    private $apiRouter;
    private $pageRouter;
    private $baseUrl;

    public function __construct($baseUrl) {

        $this->baseUrl = $baseUrl;
        $this->pageRouter = new PageRouter();
        $this->apiRouter = new ApiRouter();
    }

    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        
        $path = parse_url($uri, PHP_URL_PATH);
        
        if (!empty($this->baseUrl) && strpos($path, $this->baseUrl) === 0) {
            $path = substr($path, strlen($this->baseUrl));
        }
        
        if (strpos($path, '/api') === 0) {
            $this->apiRouter->handleRequest($method, $path);
        } else {
            $this->pageRouter->handleRequest($path);
        }
    }
}

