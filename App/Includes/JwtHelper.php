<?php

namespace App\Includes;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtHelper {
    public static function generateToken($payload) {
        $key = JWT_SECRET_KEY;
        $payload['iat'] = time(); // Issued at
        $payload['exp'] = time() + 3600; // Expiration time (1 hour)
        return JWT::encode($payload, $key, 'HS256');
    }

    public static function validateToken($token) {
        try {
            $key = JWT_SECRET_KEY;
            return JWT::decode($token, new Key($key, 'HS256'));
        } catch (\Exception $e) {
            return null; // Invalid token
        }
    }
}

