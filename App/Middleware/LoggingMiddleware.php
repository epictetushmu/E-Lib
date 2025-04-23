<?php
// filepath: /Users/hub/Documents/Personal/GenCode/E-Lib/App/Middleware/LoggingMiddleware.php
namespace App\Middleware;

class LoggingMiddleware implements MiddlewareInterface {
    private $logFile;
    
    /**
     * Create logging middleware
     *
     * @param string $logFile Path to log file
     */
    public function __construct($logFile = null) {
        $this->logFile = $logFile ?: __DIR__ . '/../../storage/logs/requests.log';
    }
    
    /**
     * Process the request and log details
     */
    public function process(array $request, callable $next) {
        $start = microtime(true);
        $method = $request['method'];
        $path = $request['path'];
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        // Format log entry
        $logEntry = sprintf(
            "[%s] %s %s - IP: %s - UA: %s",
            date('Y-m-d H:i:s'),
            $method,
            $path,
            $ip,
            $userAgent
        );
        
        // Write to log file
        error_log($logEntry . PHP_EOL, 3, $this->logFile);
        
        // Call the next middleware
        $result = $next($request);
        
        // Log request duration
        $duration = microtime(true) - $start;
        error_log(sprintf("Request completed in %.4f seconds", $duration) . PHP_EOL, 3, $this->logFile);
        
        return $result;
    }
}