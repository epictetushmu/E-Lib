<?php
require_once(__DIR__ . '/ApiRouter.php');
require_once(__DIR__ . '/PageRouter.php');

class Router {
    private $apiRouter;
    private $pageRouter;

    public function __construct() {
        $this->apiRouter = new ApiRouter();
        $this->pageRouter = new PageRouter();
    }

    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // Debugging output
        echo "Original Path: $path";

        // Update path to remove /E-Lib if it exists
        $path = str_replace('/E-Lib', '', $path);

        // Debugging output
        echo "Updated Path: $path";

        if (strpos($path, '/api') === 0) {
            $this->apiRouter->handleRequest($method, $path);
        } else {
            $this->pageRouter->handleRequest($path);
        }
    }
}

