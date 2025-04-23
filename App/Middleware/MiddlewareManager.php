<?php
// filepath: /Users/hub/Documents/Personal/GenCode/E-Lib/App/Middleware/MiddlewareManager.php
namespace App\Middleware;

class MiddlewareManager {
    private $middlewares = [];
    
    /**
     * Add middleware to the stack
     *
     * @param MiddlewareInterface $middleware
     * @return $this
     */
    public function add(MiddlewareInterface $middleware) {
        $this->middlewares[] = $middleware;
        return $this;
    }
    
    /**
     * Process the request through all middleware
     *
     * @param array $request
     * @param callable $coreHandler Final handler to execute if all middleware pass
     * @return mixed
     */
    public function run(array $request, callable $coreHandler) {
        $runner = $this->createRunner($coreHandler);
        return $runner($request);
    }
    
    /**
     * Create the middleware runner
     *
     * @param callable $coreHandler
     * @return callable
     */
    private function createRunner(callable $coreHandler) {
        $runner = $coreHandler;
        
        // Build the middleware stack from the inside out
        foreach (array_reverse($this->middlewares) as $middleware) {
            $runner = function(array $request) use ($middleware, $runner) {
                return $middleware->process($request, $runner);
            };
        }
        
        return $runner;
    }
}