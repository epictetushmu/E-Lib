<?php
require_once('../services/UserService.php');
require_once('../controllers/Controller.php');

class UserController extends Controller {
    private $userService;

    public function __construct() {
        $this->userService = new UserService();
    }

    public function showLoginForm() {
        $this->render('login_form');
    }

    public function handleLogin() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = $_POST['email'];
            $password = $_POST['password'];

            $user = $this->userService->getUserByEmail($email);
            if ($user && $user['password'] === $password) {
                session_start();
                $_SESSION['user'] = $user;
                $this->redirect('/');
            } else {
                echo "Invalid credentials!";
            }
        }
    }

    public function handleLogout() {
        session_start();
        session_destroy();
        $this->redirect('/login');
    }
}
