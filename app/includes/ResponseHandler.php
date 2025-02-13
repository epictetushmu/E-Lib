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

    public static function redirect($url , $statusCode = 303) {
        header('Location: ' . $url, true, $statusCode);
        exit();
    }
}