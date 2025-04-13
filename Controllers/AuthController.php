<?php
namespace E-Lib\Controllers;
require_once('../vendor/autoload.php'); // Include Composer autoload

use Firebase\JWT\JWT;

class AuthController {
    private $secretKey = 'your-secret-key'; // Replace with your actual secret key

    public function authenticate() {
        $input = json_decode(file_get_contents('php://input'), true);
        $username = $input['username'] ?? '';
        $password = $input['password'] ?? '';

        // Validate username and password (this is just an example, use a real validation method)
        if ($username === 'user' && $password === 'password') {
            $payload = [
                'iss' => 'your-domain.com',
                'aud' => 'your-domain.com',
                'iat' => time(),
                'exp' => time() + 3600, // Token expires in 1 hour
                'data' => [
                    'username' => $username
                ]
            ];
            $jwt = JWT::encode($payload, $this->secretKey, 'HS256');
            echo json_encode(['token' => $jwt]);
        } else {
            http_response_code(401);
            echo json_encode(['message' => 'Invalid credentials']);
        }
    }
}