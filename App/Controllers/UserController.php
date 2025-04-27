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
            $_SESSION['username'] = $user['username'];
            $_SESSION['isAdmin'] = $user['isAdmin'] ?? false;

            ResponseHandler::respond(true, [
                'token' => $token, 
                'user' => [
                    'id' => $user['_id'],
                    'email' => $user['email'],
                    'username' => $user['username'],
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
            } 
        }
        ResponseHandler::respond(true, 'No saved books found', 404);
        
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
    
    /**
     * View error logs (admin only)
     */
    public function viewLogs() {
        // Check if user is admin
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // First verify JWT token (should be handled by middleware)
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? null;
        
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            ResponseHandler::respond(false, 'Unauthorized access', 401);
            exit();
        }

        // Even with valid token, check if user is admin in session
        if (empty($_SESSION['isAdmin']) || $_SESSION['isAdmin'] !== true) {
            ResponseHandler::respond(false, 'Unauthorized: Admin privileges required', 403);
            return;
        }
        
        $logPath = dirname(__DIR__, 2) . '/storage/logs/php_errors.log';
        $requestLogPath = dirname(__DIR__, 2) . '/storage/logs/requests.log';
        
        $logs = [];
        
        // Check if error log exists and is readable
        if (file_exists($logPath) && is_readable($logPath)) {
            // Get the last 100 lines (adjust as needed)
            $errorLogs = $this->getTailOfFile($logPath, 100);
            $logs['errors'] = $errorLogs;
        } else {
            $logs['errors'] = 'Error log file not found or not readable';
        }
        
        // Check if request log exists and is readable
        if (file_exists($requestLogPath) && is_readable($requestLogPath)) {
            $requestLogs = $this->getTailOfFile($requestLogPath, 50);
            $logs['requests'] = $requestLogs;
        } else {
            $logs['requests'] = 'Request log file not found or not readable';
        }
        
        // Send logs as JSON response
        ResponseHandler::respond(true, 'Logs retrieved successfully', 200, $logs);
    }
    
    /**
     * Helper method to get the last N lines of a file
     */
    private function getTailOfFile($filePath, $lines = 100) {
        $handle = fopen($filePath, "r");
        $linecounter = $lines;
        $pos = -2;
        $beginning = false;
        $text = [];
        
        while ($linecounter > 0) {
            $t = " ";
            while ($t != "\n") {
                if (fseek($handle, $pos, SEEK_END) == -1) {
                    $beginning = true;
                    break;
                }
                $t = fgetc($handle);
                $pos--;
            }
            
            if ($beginning) {
                rewind($handle);
            }
            
            $text[] = fgets($handle);
            
            if ($beginning) {
                break;
            }
            
            $linecounter--;
        }
        
        fclose($handle);
        return array_reverse($text);
    }
}