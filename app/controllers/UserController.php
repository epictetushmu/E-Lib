<?php
namespace App\Controllers;

use App\Models\User;

class UserController {
    private $userModel;
    
    public function __construct() {
        $this->userModel = new User();
    }
    
    public function login() {
        // Get JSON data from request body
        $data = json_decode(file_get_contents('php://input'), true);
        
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Email and password are required']);
            return;
        }
        
        $user = $this->userModel->login($email, $password);
        
        if ($user) {
            // Start session and store user data
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'] ?? $user['email'];
            $_SESSION['email'] = $user['email'];
            
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => 'Login successful',
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'] ?? $user['email'],
                    'email' => $user['email']
                ]
            ]);
            return;
        }
        
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Invalid email or password']);
    }
    
    public function signup() {
        // Get JSON data from request body
        $data = json_decode(file_get_contents('php://input'), true);
        
        $username = $data['username'] ?? '';
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        
        if (empty($username) || empty($email) || empty($password)) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
            return;
        }
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Invalid email format']);
            return;
        }
        
        // Check if email already exists
        $existingUser = $this->userModel->getUserByEmail($email);
        if (!empty($existingUser)) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Email already registered']);
            return;
        }
        
        // Register user
        $userId = $this->userModel->registerUser($email, $password, $username);
        
        if ($userId) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'message' => 'Registration successful']);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Registration failed']);
        }
    }
    
    public function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Clear session data
        $_SESSION = array();
        
        // Destroy the session
        session_destroy();
        
        // Redirect to home page
        header('Location: /E-Lib/');
        exit();
    }
    
    public function displayProfile() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /E-Lib/login');
            exit();
        }
        
        $profile = $this->userModel->getUserProfile($_SESSION['user_id']);
        $userBooks = $this->getUserBooks();
        
        include __DIR__ . '/../views/profile.php';
    }
    
    public function getUserBooks() {
        if (!isset($_SESSION['user_id'])) {
            return ['borrowed' => [], 'saved' => []];
        }
        
        $borrowedBooks = $this->userModel->getBorrowedBooks($_SESSION['user_id']);
        $savedBooks = $this->userModel->getSavedBooks($_SESSION['user_id']);
        
        return [
            'borrowed' => $borrowedBooks,
            'saved' => $savedBooks
        ];
    }
}
