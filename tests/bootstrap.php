<?php

// Test bootstrap file
require_once __DIR__ . '/../vendor/autoload.php';

// Set up environment for testing
putenv('APP_ENV=testing');
putenv('DB_CONNECTION=json');

// Create test directory structure if needed
$testStorageDir = __DIR__ . '/../storage/tests';
if (!is_dir($testStorageDir)) {
    mkdir($testStorageDir, 0755, true);
}

// Define constants that might be needed for tests
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__));
}