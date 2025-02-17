<?php
require_once(__DIR__ . '/../models/User.php');

class UserService {
    private $user;

    public function __construct() {
        $this->user = new User();
    }

    public function getUserByEmail($email) {
        return $this->user->getUserByEmail($email);
    }

    public function registerUser($email, $password) {
        return $this->user->registerUser($email, $password);
    }
}
