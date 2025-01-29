<?php

class ResponseHandler {
    public static function respond($status, $data) {
        if ($status) {
            return [
                'status' => 'success',
                'data' => $data
            ];
        } else {
            return [
                'status' => 'error',
                'message' => $data
            ];
        }
    }

    public static function redirect($status) {
    if ($status<400) {
        header("Location: success_page.php");
        exit();
    } else {
        header("Location: error_page.php");
        exit();
    }
    }
}