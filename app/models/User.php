<?php
namespace App\Models;
use App\Includes\Database;
class User {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance();
    }

    public function getUserByEmail($email) {
        $sql = "SELECT * FROM users WHERE email = :email";
        return $this->pdo->execQuery($sql, array("email" => $email));
    }

    public function registerUser($email, $password, $username = null) {
        $sql = "INSERT INTO users (email, password, username) VALUES (:email, :password, :username)";
        $user = [
            "email" => $email,
            "password" => password_hash($password, PASSWORD_BCRYPT),
            "username" => $username ?: $email
        ];
        return $this->pdo->execQuery($sql, $user, true);
    }

    public function login($email, $password) {
        $sql = "SELECT * FROM users WHERE email = :email";
        $user = $this->pdo->execQuery($sql, array("email" => $email));
        if (!empty($user) && password_verify($password, $user[0]['password'])) {
            return $user[0];
        }
        return false;
    }
    
    public function getUserProfile($userId) {
        $sql = "SELECT id, email, username, created_at FROM users WHERE id = :id";
        $result = $this->pdo->execQuery($sql, array("id" => $userId));
        return !empty($result) ? $result[0] : false;
    }
}
