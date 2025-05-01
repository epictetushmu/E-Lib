<?php
// File: App/Includes/SessionManager.php
namespace App\Includes;

class SessionManager {
    public static function initialize() {
        // Start the session if not already started and if headers haven't been sent
        if (session_status() === PHP_SESSION_NONE) {
            if (!headers_sent()) {
                // Configure session to be more resilient
                ini_set('session.use_only_cookies', 1);
                ini_set('session.use_strict_mode', 1);
                
                // Start the session
                session_start();
            } else {
                // Log warning that session couldn't be started
                error_log('Warning: Could not start session - headers already sent');
            }
        }
        
        // Set global variables
        global $isLoggedIn;
        $isLoggedIn = isset($_SESSION['user_id']);
        
        return $isLoggedIn;
    }
    
    public static function getCurrentUser() {
        return $_SESSION['user'] ?? null;
    }
    
    public static function getCurrentUserId() {
        return $_SESSION['user_id'] ?? null;
    }

    public static function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
}