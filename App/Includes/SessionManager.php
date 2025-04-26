<?php
// File: App/Includes/SessionManager.php
namespace App\Includes;

class SessionManager {
    public static function initialize() {
        // Start the session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
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