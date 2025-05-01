<?php
// filepath: /Users/hub/Documents/Personal/GenCode/E-Lib/App/Middleware/AuthMiddleware.php
namespace App\Middleware;

use App\Includes\SessionManager;
use App\Includes\ResponseHandler;

class AuthMiddleware implements MiddlewareInterface {
    private $protectedPaths = [];
    
    /**
     * Create auth middleware with protected paths
     *
     * @param array $protectedPaths Paths requiring authentication
     */
    public function __construct(array $protectedPaths = []) {
        $this->protectedPaths = $protectedPaths;
    }
    
    /**
     * Process the request and check authentication
     */
    public function process(array $request, callable $next) {
        $path = $request['path'];
        
        // Check if the path requires authentication
        foreach ($this->protectedPaths as $protectedPath) {
            if (strpos($path, $protectedPath) === 0) {
                // Initialize session if needed
                SessionManager::initialize();
                
                // Check if user is authenticated
                if (!SessionManager::getCurrentUserId()) {
                    if ($request['isApi']) {
                        // API requests return JSON error
                        ResponseHandler::respond(false, 'Unauthorized access', 401);
                        return;
                    } else {
                        // Web requests redirect to home with parameter to show login popup
                        header('Location: /?showLogin=1&redirect=' . urlencode($path));
                        exit;
                    }
                }
            }
        }
        
        // Call the next middleware
        return $next($request);
    }
}