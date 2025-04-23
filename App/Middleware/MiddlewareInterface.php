<?php
// filepath: /Users/hub/Documents/Personal/GenCode/E-Lib/App/Middleware/MiddlewareInterface.php
namespace App\Middleware;

interface MiddlewareInterface {
    /**
     * Process the request and either pass to the next middleware or return a response
     *
     * @param array $request Request data including path, method, etc.
     * @param callable $next Next middleware to call
     * @return mixed Response or pass to next middleware
     */
    public function process(array $request, callable $next);
}