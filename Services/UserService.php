<?php
namespace E_Lib\Services;
use E_Lib\Models\Users;

class UserService {
    private $user;

    public function __construct() {
        $this->user = new Users();
    }

    public function getUserByEmail($email) {
        return $this->user->getUserByEmail($email);
    }

    public function registerUser($email, $password) {
        return $this->user->registerUser($email, $password);
    }
}
