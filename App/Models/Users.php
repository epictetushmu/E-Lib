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

    public function saveBook($userId, $bookId) {
        $user = $this->getUserById($userId);
        if ($user) {
            // Convert MongoDB BSONArray to PHP array if needed
            $savedBooksOriginal = $user['savedBooks'] ?? [];
            
            // Convert to PHP array if it's a MongoDB\Model\BSONArray
            $savedBooks = is_object($savedBooksOriginal) && method_exists($savedBooksOriginal, 'getArrayCopy') 
                ? $savedBooksOriginal->getArrayCopy() 
                : (array)$savedBooksOriginal;
            
            if (!in_array($bookId, $savedBooks)) {
                $savedBooks[] = $bookId;
                return $this->db->update($this->collection, ['_id' => $userId], ['$set' => ['savedBooks' => $savedBooks]]);
            }
            return true; // Book was already saved
        }
        return false;
    }

    public function removeBook($userId, $bookId) {
        $user = $this->getUserById($userId);
        if ($user) {
            // Convert MongoDB BSONArray to PHP array if needed
            $savedBooksOriginal = $user['savedBooks'] ?? [];
            
            // Convert to PHP array if it's a MongoDB\Model\BSONArray
            $savedBooks = is_object($savedBooksOriginal) && method_exists($savedBooksOriginal, 'getArrayCopy') 
                ? $savedBooksOriginal->getArrayCopy() 
                : (array)$savedBooksOriginal;
            
            if (in_array($bookId, $savedBooks)) {
                $savedBooks = array_diff($savedBooks, [$bookId]);
                return $this->db->update($this->collection, ['_id' => $userId], ['$set' => ['savedBooks' => $savedBooks]]);
            }
            return true;
        }
        return false;
    }
}
