<?php

namespace App\Middleware;

use App\Includes\JwtHelper;
use App\Includes\ResponseHandler;
use App\Middleware\MiddlewareInterface;

class JwtAuthMiddleware implements MiddlewareInterface
{
    private $protectedPaths;

    public function __construct(array $protectedPaths = [])
    {
        $this->protectedPaths = $protectedPaths;
    }

    public function process(array $request, callable $next)
    {
        $path = isset($request['path']) ? $request['path'] : '/';
        $method = isset($request['method']) ? strtoupper($request['method']) : (isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET');

        // Check if the path and method require authentication
        foreach ($this->protectedPaths as $protected) {
            if (is_array($protected)) {
                $protectedPath = $protected['path'] ?? '';
                $protectedMethod = strtoupper($protected['method'] ?? 'GET');
            } else {
                $protectedPath = $protected;
                $protectedMethod = null;
            }
            // Match path (prefix or full regex when pattern contains metacharacters) and method
            if (
                $this->pathMatches($path, $protectedPath) &&
                ($protectedMethod === null || $protectedMethod === $method)
            ) {
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

    /**
     * Paths like /api/v1/books/([0-9a-f]{24})/download are regex; plain paths use prefix match.
     */
    private function pathMatches(string $path, string $protectedPath): bool
    {
        if ($protectedPath === '') {
            return false;
        }
        if (strpbrk($protectedPath, '[]()^$?*+{}|') !== false) {
            return @preg_match('#^' . $protectedPath . '$#', $path) === 1;
        }

        return strpos($path, $protectedPath) === 0;
    }
}
