<?php
namespace App\Controllers;

use App\Services\UserService; 
use App\Services\BookService;
use App\Includes\ResponseHandler;
use App\Includes\JwtHelper;

class UserController {
    private $userService;
    private $bookService;
    
    public function __construct() {
        $this->userService = new UserService();
        $this->bookService = new BookService();
    }

    public function handleLogin() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_POST)) {
            $inputJSON = file_get_contents('php://input');
            $input = json_decode($inputJSON, true);
            $email = $input['email'] ?? null;
            $password = $input['password'] ?? null;
        } else {
            $email = $_POST['email'] ?? null;
            $password = $_POST['password'] ?? null;
        }

        $user = $this->userService->getUserByEmail($email);
        if ($user && password_verify($password, $user['password'])) {
            $payload = [
                'user_id' => $user['_id'],
                'email' => $user['email']
            ];
            $token = JwtHelper::generateToken($payload);

            $_SESSION['user_id'] = $user['_id'];
            $_SESSION['token'] = $token;

            ResponseHandler::respond(true, [
                'token' => $token, 
                'user' => [
                    'id' => $user['_id'],
                    'email' => $user['email'],
                    'isAdmin' => $user['isAdmin'] ?? false
                ]
            ], 200);
        } else {
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

    public function saveBook() { 
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (empty($_SESSION['user_id'])) {
            ResponseHandler::respond(false, 'User not authenticated', 401);
            return;
        }
        
        if (empty($_POST)) {
            $inputJSON = file_get_contents('php://input');
            $input = json_decode($inputJSON, true);
            $bookId = $input['book_id'] ?? null;
        } else {
            $bookId = $_POST['book_id'] ?? null;
        }

        if (empty($bookId)) {
            ResponseHandler::respond(false, 'Book ID is required', 400);
            return;
        }

        $userId = $_SESSION['user_id'] ?? null;

        if ($this->userService->saveBook($userId, $bookId)) {
            ResponseHandler::respond(true, 'Book saved successfully', 200);
        } else {
            ResponseHandler::respond(false, 'Failed to save book', 400);
        }
    }

    public function getSavedBooks() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (empty($_SESSION['user_id'])) {
            ResponseHandler::respond(false, 'User not authenticated', 401);
            return;
        }

        $userId = $_SESSION['user_id'] ?? null;
        $bookIds = $this->userService->getSavedBooks($userId);
        
        if (!empty($bookIds)) {
            $books = [];
            foreach ($bookIds as $bookId) {
                $book = $this->bookService->getBookDetails($bookId);
                if ($book) {
                    $books[] = $book;
                }
            }
            
            if (!empty($books)) {
                ResponseHandler::respond(true, $books, 200);
            } else {
                ResponseHandler::respond(false, 'Failed to retrieve book details', 404);
            }
        } else {
            ResponseHandler::respond(false, 'No saved books found', 404);
        }
    }

    public function removeBook() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (empty($_SESSION['user_id'])) {
            ResponseHandler::respond(false, 'User not authenticated', 401);
            return;
        }
        
        if (empty($_POST)) {
            $inputJSON = file_get_contents('php://input');
            $input = json_decode($inputJSON, true);
            $bookId = $input['book_id'] ?? null;
        } else {
            $bookId = $_POST['book_id'] ?? null;
        }

        if (empty($bookId)) {
            ResponseHandler::respond(false, 'Book ID is required', 400);
            return;
        }

        $userId = $_SESSION['user_id'] ?? null;

        if ($this->userService->removeBook($userId, $bookId)) {
            ResponseHandler::respond(true, 'Book removed successfully', 200);
        } else {
            ResponseHandler::respond(false, 'Failed to remove book', 400);
        }
    }
}