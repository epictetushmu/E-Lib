<?php
/**
 * Application Bootstrap
 * 
 * This file initializes output buffering to prevent "headers already sent" errors
 * and configures basic application settings.
 */

// Start output buffering to prevent "headers already sent" errors
ob_start();

// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0); // Disable displaying errors directly to the client

// Set timezone if needed
// date_default_timezone_set('UTC');

// Additional application initialization can go here

define('JWT_SECRET_KEY', 'your-secret-key'); // Replace with a secure key

// Include Composer autoloader
// require_once __DIR__ . '../vendor/autoload.php';