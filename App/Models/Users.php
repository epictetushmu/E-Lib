<?php
namespace App\Models;

use App\Controllers\DbController;

class Users {
    private $db;

    public function __construct() {
        $this->db = DbController::getInstance();
    }

    public function getUserByEmail($email) {
        return $this->db->findOne('users', ['email' => $email]);
    }

    public function registerUser($email, $password) {
        $user = [
            'email' => $email,
            'password' => password_hash($password, PASSWORD_BCRYPT)
        ];
        return $this->db->insert('users', $user);
    }

    public function login($email, $password) {
        $user = $this->getUserByEmail($email);
        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        return false;
    }
}
