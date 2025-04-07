<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'e_library');
define('DB_USER', 'root');
define('DB_PASS', '');

// Site settings
define('SITE_URL', 'http://localhost/E-Lib');
define('SITE_NAME', 'Epictetus Library');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone
date_default_timezone_set('UTC');
