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
     * Send a file as a response
     *
     * @param string $filePath Path to the file
     * @param string $contentType MIME type of the file
     * @param bool $download Whether to force download or show inline
     * @param string|null $filename Custom filename for download
     * @return void
     */
    public static function respondWithFile($filePath, $contentType, $download = false, $filename = null) {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            self::respond(false, 'File not found or not readable', 404);
            return;
        }

        // Clear any output buffers that might be active
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Set the appropriate headers
        if (!headers_sent()) {
            // Content type header
            header("Content-Type: $contentType");
            
            // Get file size
            $fileSize = filesize($filePath);
            header("Content-Length: $fileSize");
            
            // Set filename for download or reference
            $filename = $filename ?? basename($filePath);
            $disposition = $download ? 'attachment' : 'inline';
            header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
            
            // Cache control headers
            header('Cache-Control: private, max-age=300, must-revalidate');
            header('Pragma: public');
            header('Accept-Ranges: bytes');
        }

        // Output the file content
        readfile($filePath);
        exit;
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
        
        // Only set headers if possible
        if (!headers_sent()) {
            header('Location: ' . $url, true, $statusCode);
            exit();
        } else {
            // Fallback to JavaScript redirect if headers already sent
            echo '<script>window.location.href = "' . htmlspecialchars($url) . '";</script>';
            echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url) . '"></noscript>';
            echo '<p>If you are not redirected, <a href="' . htmlspecialchars($url) . '">click here</a>.</p>';
            exit();
        }
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
        // Set HTTP status code and content type if headers haven't been sent yet
        if (!headers_sent()) {
            http_response_code($statusCode);
            // Set content type for HTML
            header('Content-Type: text/html; charset=UTF-8');
        }
        
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