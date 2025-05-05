<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use App\Includes\JwtHelper;
use App\Middleware\JwtAuthMiddleware;
use App\Middleware\MiddlewareManager;

class ApiAuthenticationTest extends TestCase
{
    private $jwtSecret;
    
    protected function setUp(): void
    {
        // Store original JWT secret
        $this->jwtSecret = defined('JWT_SECRET_KEY') ? JWT_SECRET_KEY : null;
        
        // Define a test JWT secret if not already defined
        if (!defined('JWT_SECRET_KEY')) {
            define('JWT_SECRET_KEY', 'test_secret_key_for_jwt_authentication');
        }
        
        // Set up test environment
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/v1/books';
    }
    
    protected function tearDown(): void
    {
        // Clean up the global environment after each test
        unset($_SERVER['HTTP_AUTHORIZATION']);
    }
    
    /**
     * Test JWT token generation
     */
    public function testJwtTokenGeneration()
    {
        $payload = [
            'user_id' => '12345',
            'email' => 'test@example.com',
            'role' => 'user'
        ];
        
        // Generate token
        $token = JwtHelper::generateToken($payload);
        
        // Assert token is not empty and is a string
        $this->assertNotEmpty($token);
        $this->assertIsString($token);
        
        // Decode token for verification
        $decoded = JwtHelper::validateToken($token);
        
        // Convert stdClass to array for easier assertions
        $decodedArray = json_decode(json_encode($decoded), true);
        
        // Assert payload values are preserved
        $this->assertEquals($payload['user_id'], $decodedArray['user_id']);
        $this->assertEquals($payload['email'], $decodedArray['email']);
        $this->assertEquals($payload['role'], $decodedArray['role']);
        
        // Assert token contains standard JWT claims
        $this->assertArrayHasKey('iat', $decodedArray);
        $this->assertArrayHasKey('exp', $decodedArray);
        
        // Using assertObjectHasProperty instead of deprecated assertObjectHasAttribute
        $this->assertObjectHasProperty('user_id', $decoded);
        $this->assertObjectHasProperty('email', $decoded);
        $this->assertObjectHasProperty('role', $decoded);
        $this->assertObjectHasProperty('iat', $decoded);
        $this->assertObjectHasProperty('exp', $decoded);
    }
    
    /**
     * Test JWT token validation
     */
    public function testJwtTokenValidation()
    {
        $payload = [
            'user_id' => '12345',
            'email' => 'test@example.com'
        ];
        
        // Generate a valid token
        $token = JwtHelper::generateToken($payload);
        
        // Set up auth header with the token
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        
        // Create mock for getallheaders function
        function getallheaders() {
            return [
                'Authorization' => $_SERVER['HTTP_AUTHORIZATION'] ?? ''
            ];
        }
        
        // Create protected routes
        $protectedPaths = ['/api/v1/books'];
        $jwtMiddleware = new JwtAuthMiddleware($protectedPaths);
        
        // Create a request object
        $request = [
            'path' => '/api/v1/books',
            'method' => 'GET'
        ];
        
        // Test the middleware with a valid token
        $result = $jwtMiddleware->process($request, function($request) {
            // The middleware should add user info to the request
            $this->assertArrayHasKey('user', $request);
            $this->assertEquals('12345', $request['user']['user_id']);
            $this->assertEquals('test@example.com', $request['user']['email']);
            return $request;
        });
        
        // Create a middleware for testing an invalid token
        $token = 'invalid-token';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        
        // Capture output to prevent the error response from being displayed
        ob_start();
        $jwtMiddleware->process($request, function($request) {
            // This should not be reached with an invalid token
            $this->fail('Middleware should not process the request with an invalid token');
            return $request;
        });
        ob_end_clean();
        
        // Test missing authorization header
        unset($_SERVER['HTTP_AUTHORIZATION']);
        ob_start();
        $jwtMiddleware->process($request, function($request) {
            // This should not be reached with a missing auth header
            $this->fail('Middleware should not process the request with missing auth header');
            return $request;
        });
        ob_end_clean();
    }
}