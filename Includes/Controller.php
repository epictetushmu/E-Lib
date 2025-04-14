<?php

namespace  Includes;

class Controller {
    protected $viewData = [];
    
    public function render($view = 'home', $data = []) {
        $this->viewData = $data;
        
        // Use absolute path with __DIR__ to locate the views directory
        $viewPath = __DIR__ . '/../views/' . $view . '.php';
        
        // Check if the view file exists
        if (!file_exists($viewPath)) {
            die("View not found: $viewPath");
        }
        
        // Extract view data to make variables available in the view
        extract($this->viewData);
        
        // Include the view file
        require_once $viewPath;
    }

    protected function redirect($url) {
        header("Location: {$url}");
        exit();
    }
}
