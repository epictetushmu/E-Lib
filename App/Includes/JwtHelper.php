<?php

namespace App\Includes;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\BeforeValidException;
use UnexpectedValueException;
use DomainException;
use InvalidArgumentException;

class JwtHelper
{
    /**
     * Resolve the JWT signing secret from the environment at call time (not at process
     * start), so a value loaded from .env after bootstrap.php ran is actually honored.
     * Falls back to a hardcoded dev-only key outside production; fails closed in
     * production rather than silently signing/verifying tokens with a known secret.
     */
    private static function getSecretKey(): string
    {
        $key = Environment::get('JWT_SECRET_KEY');
        if ($key) {
            return (string) $key;
        }

        if (Environment::get('APP_ENV') === 'production') {
            throw new \RuntimeException('JWT_SECRET_KEY is not configured; refusing to sign/verify tokens in production');
        }

        return 'your-secret-key-for-development-only';
    }

    /**
     * Generate a JWT token with the provided payload
     *
     * @param array<string, mixed> $payload Data to include in the token
     * @return string The generated JWT token
     */
    public static function generateToken($payload)
    {
        $key = self::getSecretKey();
        $payload['iat'] = time(); // Issued at
        $payload['exp'] = time() + 3600; // Expiration time (1 hour)
        return JWT::encode($payload, $key, 'HS256');
    }

    /**
     * Validate a JWT token and return the decoded payload
     *
     * @param string $token The JWT token to validate
     * @return object|null The decoded payload or null if the token is invalid
     */
    public static function validateToken($token)
    {
        try {
            $key = self::getSecretKey();
            $decoded = JWT::decode($token, new Key($key, 'HS256'));
            return $decoded;
        } catch (ExpiredException $e) {
            // Token has expired
            error_log('JWT token expired: ' . $e->getMessage());
            return null;
        } catch (SignatureInvalidException $e) {
            // Invalid signature
            error_log('JWT signature invalid: ' . $e->getMessage());
            return null;
        } catch (BeforeValidException $e) {
            // Token not yet valid (used before nbf or iat claims)
            error_log('JWT token not yet valid: ' . $e->getMessage());
            return null;
        } catch (UnexpectedValueException | DomainException | InvalidArgumentException | \Exception $e) {
            // Other validation errors
            error_log('JWT validation error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get specific error information when token validation fails
     * Useful for debugging
     *
     * @param string $token The JWT token to validate
     * @return array<string, mixed> with success status and error message if failed
     */
    public static function getTokenValidationError($token)
    {
        try {
            $key = self::getSecretKey();
            $decoded = JWT::decode($token, new Key($key, 'HS256'));
            return [
                'success' => true,
                'data' => $decoded
            ];
        } catch (ExpiredException $e) {
            return [
                'success' => false,
                'error' => 'Token has expired',
                'message' => $e->getMessage()
            ];
        } catch (SignatureInvalidException $e) {
            return [
                'success' => false,
                'error' => 'Invalid signature',
                'message' => $e->getMessage()
            ];
        } catch (BeforeValidException $e) {
            return [
                'success' => false,
                'error' => 'Token not yet valid',
                'message' => $e->getMessage()
            ];
        } catch (UnexpectedValueException | DomainException | InvalidArgumentException | \Exception $e) {
            return [
                'success' => false,
                'error' => 'Token validation error',
                'message' => $e->getMessage()
            ];
        }
    }
}
