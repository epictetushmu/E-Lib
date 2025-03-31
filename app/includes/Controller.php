<?php

namespace App\Includes;

class Controller {
    protected function render($view, $data = []) {
        extract($data);
        require_once("../views/{$view}.php");
    }

    protected function redirect($url) {
        header("Location: {$url}");
        exit();
    }
}
