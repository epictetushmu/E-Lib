<?php
namespace App\Models;

use App\Controllers\DbController;

class Users {
    private $db;

    private $collection = 'Users';

    public function __construct() {
        $this->db = DbController::getInstance();
    }

    public function getUserByEmail($email) {
        return $this->db->findOne($this->collection, ['email' => $email]);
    }

    public function registerUser($user) {
       
        return $this->db->insert($this->collection, $user);
    }

    public function login($email, $password) {
        $user = $this->getUserByEmail($email);
        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        return false;
    }

    public function getUserById($id) {
        return $this->db->findOne($this->collection, ['_id' => $id]);
    }
}
