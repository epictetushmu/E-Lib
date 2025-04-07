<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/autoloader.php';

use App\Controllers\BookController;

header('Content-Type: application/json');

// Allow POST requests only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Method not allowed'
    ]);
    exit;
}

// Start session to check for user authentication
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Authentication required'
    ]);
    exit;
}

// Create controller and process borrowing
$bookController = new BookController();
$bookController->borrowBook();
