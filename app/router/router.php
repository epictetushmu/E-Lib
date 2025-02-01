<?php
require_once('../routers/ApiRouter.php');
require_once('../routers/PageRouter.php');

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

        if ($this->isApiRequest($path)) {
            $this->apiRouter->handleRequest($method, $path);
        } else {
            $this->pageRouter->handleRequest($path);
        }
    }

    private function isApiRequest($path) {
        // Define API prefixes (adjust if necessary)
        $apiPrefixes = ['/api', '/book', '/search', '/login', '/logout', '/add-book'];

        foreach ($apiPrefixes as $prefix) {
            if (strpos($path, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }
}

