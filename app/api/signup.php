<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/autoloader.php';

use App\Controllers\UserController;

header('Content-Type: application/json');

// Allow POST requests only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Method not allowed'
    ]);
    exit;
}

// Create controller and process signup
$userController = new UserController();
$userController->signup();
