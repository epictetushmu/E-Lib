<?php
namespace App\Controllers;

use App\Services\UserService; 
use App\Includes\ResponseHandler;

class UserController {
    private $userService;

    public function __construct() {
        $this->userService = new UserService();
    }

    public function handleLogin() {
        $email = $_POST['email'];
        $password = $_POST['password'];

        $user = $this->userService->getUserByEmail($email);
        if ($user && $user['password'] === $password) {
            session_start();
            $_SESSION['user'] = $user;
            ResponseHandler::redirect('/');    
        } else {
            ResponseHandler::respond(false, 'Invalid credentials', 401);
        }
    }

    public function handleLogout() {
        // Logout logic here...
        ResponseHandler::respond(true, 'Logout successful');
    }
}
