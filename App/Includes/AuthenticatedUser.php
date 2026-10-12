<?php

namespace App\Includes;

/**
 * Resolves the current user id from PHP session or Authorization Bearer JWT.
 */
class AuthenticatedUser
{
    public static function id(): ?string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!empty($_SESSION['user_id'])) {
            return (string) $_SESSION['user_id'];
        }
        $headers = function_exists('getallheaders') ? (getallheaders() ?: []) : [];
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }
        $token = trim(substr($authHeader, 7));
        if ($token === '') {
            return null;
        }
        $decoded = JwtHelper::validateToken($token);
        if ($decoded && isset($decoded->user_id)) {
            return (string) $decoded->user_id;
        }

        return null;
    }

    /**
     * Check if the current user (session or Bearer JWT) has admin privileges.
     */
    public static function isAdmin(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!empty($_SESSION['user_id']) && !empty($_SESSION['isAdmin'])) {
            return true;
        }
        $headers = function_exists('getallheaders') ? (getallheaders() ?: []) : [];
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return false;
        }
        $token = trim(substr($authHeader, 7));
        if ($token === '') {
            return false;
        }
        $decoded = JwtHelper::validateToken($token);
        return $decoded !== null && !empty($decoded->isAdmin);
    }
}
