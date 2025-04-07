<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/autoloader.php';

use App\Controllers\BookController;

header('Content-Type: application/json');

// Allow GET requests only
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Method not allowed'
    ]);
    exit;
}

// Create controller and get featured books
$bookController = new BookController();
$bookController->getFeaturedBooks();
