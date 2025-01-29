<?php
require_once('../services/UserService.php');
require_once('../controllers/Controller.php');
require_once('../utils/ResponseHandler.php');

class UserController extends Controller {
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
            $this->respond->redirect('');    
        } else {
            echo "Invalid credentials!";
        }
    }

    public function handleLogout() {
        session_start();
        session_destroy();
        $this->redirect('/login');
    }
}
