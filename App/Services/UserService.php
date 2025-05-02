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
            'password' => password_hash($password, PASSWORD_BCRYPT), 
            'isAdmin' => false,
            'createdAt' => new \MongoDB\BSON\UTCDateTime()
        ];
        return $this->user->registerUser($user);
    }

    public function getUserById($id) {
        return $this->user->getUserById($id);
    }

    public function saveBook($userId, $bookId) {
        return $this->user->saveBook($userId, $bookId);
    }

    public function getSavedBooks($userId) {
        $user = $this->getUserById($userId);
        if (!empty($user['savedBooks'])) {
            return $user['savedBooks'];
        }
        return null;
    }

    public function removeBook($userId, $bookId) {       
        return $this->user->removeBook($userId, $bookId);
    }

    /**
     * Update user profile information
     * 
     * @param string $userId The ID of the user to update
     * @param array $updates Associative array of fields to update
     * @return bool True on success, false on failure
     */
    public function updateUser($userId, array $updates) {
        return $this->user->updateUser($userId, $updates);
    }
}
