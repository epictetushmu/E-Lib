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

        
        //Update path to remove /E-Lib/app/public
        $path = str_replace('/E-Lib/app/public', '', $path);
        
        if (strpos($path, '/api') === 0) {
            $this->apiRouter->handleRequest($method, $path);
        } else {
            $this->pageRouter->handleRequest($path);
        }
    }

}

