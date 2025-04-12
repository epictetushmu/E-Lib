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

