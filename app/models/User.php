<?php
require_once(__DIR__ . '/../includes/database.php');

class User {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance();
    }

    public function getUserByEmail($email) {
        $sql = "SELECT * FROM users WHERE email = :email";
        return $this->pdo->execQuery($sql, array("email" => $email));
    }

    public function registerUser($email, $password) {
        $sql = "INSERT INTO users (email, password) VALUES (:email, :password)";
        $user = [
            "email" => $email,
            "password" => password_hash($password, PASSWORD_BCRYPT)
        ];
        return $this->pdo->execQuery($sql, $user);
    }

    public function login($username, $password) {
        $sql = "SELECT * FROM users WHERE email = :email";
        $user = $this->pdo->execQuery($sql, array("email" => $username));
        if ($user && password_verify($password, $user[0]['password'])) {
            return $user[0];
        }
        return false;
    }
}
