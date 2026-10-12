<?php

namespace App\Includes;

/**
 * Class ResponseHandler
 * Handles API responses and redirects
 */
class ResponseHandler
{
    /**
     * Send a formatted API response
     *
     * @param int $statusCode HTTP status code
     * @param mixed $data Data to return or error message
     * @param bool $status Success or failure status
     * @return array<string, mixed>|void Array for internal use or sends JSON response
     */
    public static function respond($status, $data, $statusCode = null)
    {
        $response = [];

        if ($status) {
            $response = [
                'status' => 'success',
                'data' => $data
            ];

            $statusCode = $statusCode ?? 200;
        } else {
            $response = [
                'status' => 'error',
                'message' => $data
            ];

            $statusCode = $statusCode ?? 400;
        }

        // Set appropriate HTTP status code if headers haven't been sent yet
        if (!headers_sent()) {
            http_response_code($statusCode);
        }

        // Check if this is an API call that needs JSON response
        if (self::isApiRequest()) {
            if (!headers_sent()) {
                http_response_code($statusCode);
                header('Content-Type: application/json');
            }
            echo json_encode($response);
            exit();
        }

        return $response;
    }

    /**
     * Check if the current request is an API request
     *
     * @return bool
     */
    private static function isApiRequest()
    {
        // Check for AJAX request
        if (
            !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
        ) {
            return true;
        }

        // Check for API endpoints in URL or Accept header
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';

        return (
            strpos($requestUri, '/api/v1/') !== false ||
            strpos($acceptHeader, 'application/json') !== false
        );
    }
}
