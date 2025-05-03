<?php

namespace App\Middleware;

use App\Includes\JwtHelper;
use App\Includes\ResponseHandler;
use App\Middleware\MiddlewareInterface;

class JwtAuthMiddleware implements MiddlewareInterface {
    private $protectedPaths;

    public function __construct(array $protectedPaths = []) {
        $this->protectedPaths = $protectedPaths;
    }

    public function process(array $request, callable $next) {
        $path = isset($request['path']) ? $request['path'] : '/';

        // Check if the path requires authentication
        foreach ($this->protectedPaths as $protectedPath) {
            if (strpos($path, $protectedPath) === 0) {
                // Validate the JWT token
                $headers = getallheaders();
                $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : null;

                if (!$authHeader || strpos($authHeader, 'Bearer ') !== 0) {
                    ResponseHandler::respond(false, 'Unauthorized access', 401);
                    exit();
                }

                $token = str_replace('Bearer ', '', $authHeader);
                $decoded = JwtHelper::validateToken($token);

                if (!$decoded) {
                    // For debugging, get detailed error information
                    $tokenError = JwtHelper::getTokenValidationError($token);
                    $errorMessage = 'Invalid or expired token';
                    
                    // Log the specific error for debugging
                    if (isset($tokenError['error'])) {
                        $errorMessage = isset($tokenError['message']) ? $tokenError['message'] : 'No details';
                        error_log("JWT Validation Error: {$tokenError['error']} - {$errorMessage}");
                    }
                    
                    ResponseHandler::respond(false, $errorMessage, 401);
                    exit();
                }

                // Add user info to the request for further processing
                // Convert stdClass to array for more consistent use in the application
                $request['user'] = json_decode(json_encode($decoded), true);
                break;
            }
        }

        return $next($request);
    }
}