<?php
namespace App\Includes;

/**
 * Class ResponseHandler
 * Handles API responses and redirects
 */
class ResponseHandler {
    /**
     * Send a formatted API response
     *
     * @param int $statusCode HTTP status code
     * @param mixed $data Data to return or error message
     * @param bool $status Success or failure status
     * @return array|void Array for internal use or sends JSON response
     */
    public static function respond($status, $data, $statusCode = null) {
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
        
        // Set appropriate HTTP status code
        http_response_code($statusCode);
        
        // Check if this is an API call that needs JSON response
        if (self::isApiRequest()) {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        }
        
        return $response;
    }

    /**
     * Redirect to another URL
     *
     * @param string $url The URL to redirect to
     * @param int $statusCode HTTP status code for redirect
     * @return void
     */
    public static function redirect($url, $statusCode = 303) {
        if (!filter_var($url, FILTER_VALIDATE_URL) && !str_starts_with($url, '/')) {
            throw new \InvalidArgumentException("Invalid URL for redirection");
        }
        
        header('Location: ' . $url, true, $statusCode);
        exit();
    }
    
    /**
     * Check if the current request is an API request
     *
     * @return bool
     */
    private static function isApiRequest() {
        // Check for AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
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
    
    /**
     * Render a view with the given data
     *
     * @param string $view Path to the view file
     * @param array $data Data to pass to the view
     * @param int $statusCode HTTP status code
     * @return void
     */
    public static function renderView($view, $data = [], $statusCode = 200) {
        // Set HTTP status code
        http_response_code($statusCode);
        
        // Set content type for HTML
        header('Content-Type: text/html; charset=UTF-8');
        
        // Extract data to make variables available to the view
        if (!empty($data)) {
            extract($data);
        }
        
        // Ensure the view file exists
        $viewPath = realpath($view);
        if (!$viewPath || !file_exists($viewPath)) {
            throw new \RuntimeException("View file not found: $view");
        }
        
        // Include the view file
        ob_start();
        include $viewPath;
        $content = ob_get_clean();
        
        echo $content;
        exit();
    }
}