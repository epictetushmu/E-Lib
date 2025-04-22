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

    public function handleSignup() {
        // Debug the incoming data
        error_log('POST data: ' . json_encode($_POST));
        error_log('Request method: ' . $_SERVER['REQUEST_METHOD']);
        error_log('Content-Type: ' . $_SERVER['CONTENT_TYPE'] ?? 'Not set');
        
        // Only continue if data exists
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
        echo "User Name: $userName, Email: $email, Password: $password"; // Debugging output
        if ($this->userService->registerUser($userName, $email, $password)) {
            ResponseHandler::respond(true, 'User created successfully');
        } else {
            ResponseHandler::respond(false, 'User creation failed', 400);
        }
    }
}
