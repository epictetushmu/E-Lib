<?php

namespace App\Middleware;

use App\Includes\JwtHelper;
use App\Includes\ResponseHandler;
use App\Middleware\MiddlewareInterface;

class JwtAuthMiddleware implements MiddlewareInterface {
    public function process(array $request, callable $next) {
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

        return $next($request);
    }
}