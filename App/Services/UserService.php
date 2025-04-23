<?php
namespace App\Services;
use App\Models\Users;

class UserService {
    private $user;

    public function __construct() {
        $this->user = new Users();
    }

    public function getUserByEmail($email) {
        return $this->user->getUserByEmail($email);
    }

    public function registerUser($userName, $email, $password) {
        $user = [ 
            'username' => $userName,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_BCRYPT)
        ];
        return $this->user->registerUser($user);
    }

    public function getUserById($id) {
        return $this->user->getUserById($id);
    }
}
