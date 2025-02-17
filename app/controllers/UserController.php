<?php
require_once(__DIR__ . '/../services/UserService.php');
require_once(__DIR__ . '/../includes/ResponseHandler.php');

class UserController {
    private $userService;
    private $respond;

    public function __construct() {
        $this->userService = new UserService();
        $this->respond = new ResponseHandler();
    }

    public function handleLogin() {
        $email = $_POST['email'];
        $password = $_POST['password'];

        $user = $this->userService->getUserByEmail($email);
        if ($user && $user['password'] === $password) {
            session_start();
            $_SESSION['user'] = $user;
            $this->respond->redirect('301', '');    
        } else {
            $this->respond->respond(401, 'Invalid credentials');
        }
    }

    public function handleLogout() {
        // Logout logic here...
        $this->respond->respond(200, 'Logout successful');
    }
}
