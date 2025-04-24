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
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_POST)) {
            // Try to read from input stream (for JSON requests)
            $inputJSON = file_get_contents('php://input');
            error_log('Raw input: ' . $inputJSON);
            $input = json_decode($inputJSON, true);
            
            if ($input) {
                $email = $input['email'] ?? null; 
                $password = $input['password'] ?? null;
                $redirectUrl = $input['redirect'] ?? null; 
            } else {
                ResponseHandler::respond(false, 'No data received', 400);
                return;
            }
        } else {
            $email = $_POST['email'] ?? null;
            $password = $_POST['password'] ?? null;
            $redirectUrl = $_POST['redirect'] ?? null;
        }

        $user = $this->userService->getUserByEmail($email);
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user'] = $user;
            $_SESSION['user_id'] = $user['_id'];
            error_log('Login successful for: ' . $email);
            if($redirectUrl) {
                header('Location: ' . $redirectUrl);
                exit();
            }
            ResponseHandler::respond(true, 'Login successful', 200);  
        } else {
            error_log('Login failed for: ' . $email);
            ResponseHandler::respond(false, 'Invalid credentials', 401);
        }
    }

    public function handleLogout() {
        // Logout logic here...
        if (!isset($_SESSION['user_id'])) {
            ResponseHandler::respond(false, 'No user logged in', 401);
            return;
        }
        $_SESSION = [];
        session_destroy();
        ResponseHandler::respond(true, 'Logout successful');
       
    }

    public function handleSignup() {
        
        if (empty($_POST)) {
            // Try to read from input stream (for JSON requests)
            $inputJSON = file_get_contents('php://input');
            error_log('Raw input: ' . $inputJSON);
            $input = json_decode($inputJSON, true);
            
            if ($input) {
                $userName = $input['username'] ?? null;
                $email = $input['email'] ?? null; 
                $password = $input['password'] ?? null;
            } else {
                ResponseHandler::respond(false, 'No data received', 400);
                return;
            }
        } else {
            // Get from POST (for form submissions)
            $userName = $_POST['username'] ?? null;
            $email = $_POST['email'] ?? null;
            $password = $_POST['password'] ?? null;
        }
        
        // Continue with your validation
        if (empty($userName) || empty($email) || empty($password)) {
            ResponseHandler::respond(false, 'All fields are required', 400);
            return;
        }
        
        $existingUser = $this->userService->getUserByEmail($email);
        if ($existingUser) {
            ResponseHandler::respond(false, 'Email already exists', 400);
            return;
        }
        // Validate the input data
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            ResponseHandler::respond(false, 'Invalid email format', 400);
            return;
        }
        if ($this->userService->registerUser($userName, $email, $password)) {
            ResponseHandler::respond(true, 'User created successfully', 200);
        } else {
            ResponseHandler::respond(false, 'User creation failed', 400);
        }
    }

    public function getUser($id) {
        $user = $this->userService->getUserById($id);
        if ($user) {
            ResponseHandler::respond(true, $user, 200);
        } else {
            ResponseHandler::respond(false, 'User not found', 404);
        }
        
    }
}
