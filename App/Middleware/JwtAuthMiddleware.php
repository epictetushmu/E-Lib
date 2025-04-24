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
        $path = $request['path'] ?? '/';

        // Check if the path requires authentication
        foreach ($this->protectedPaths as $protectedPath) {
            if (strpos($path, $protectedPath) === 0) {
                // Validate the JWT token
                $headers = getallheaders();
                $authHeader = $headers['Authorization'] ?? null;

                if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
                    ResponseHandler::respond(false, 'Unauthorized access', 401);
                    return;
                }

                $token = str_replace('Bearer ', '', $authHeader);
                $decoded = JwtHelper::validateToken($token);

                if (!$decoded) {
                    ResponseHandler::respond(false, 'Invalid or expired token', 401);
                    return;
                }

                // Add user info to the request for further processing
                $request['user'] = (array) $decoded;
                break;
            }
        }

        return $next($request);
    }
}