<?php
namespace App\Models;

use App\Includes\Database;

class Categories {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance();
    }

    public function getAllCategories() {
        $sql = "SELECT * FROM categories ORDER BY name ASC";
        return $this->pdo->execQuery($sql);
    }
    
    public function getCategoryById($id) {
        $sql = "SELECT * FROM categories WHERE id = :id";
        $result = $this->pdo->execQuery($sql, ["id" => $id]);
        return !empty($result) ? $result[0] : null;
    }
    
    public function getCategoryByName($name) {
        $sql = "SELECT * FROM categories WHERE name = :name";
        $result = $this->pdo->execQuery($sql, ["name" => $name]);
        return !empty($result) ? $result[0] : null;
    }
    
    public function addCategory($name) {
        // Check if category exists
        $existing = $this->getCategoryByName($name);
        
        if ($existing) {
            return $existing['id']; // Return existing ID
        }
        
        // Create new category
        $sql = "INSERT INTO categories (name) VALUES (:name)";
        return $this->pdo->execQuery($sql, ["name" => $name], true);
    }

    public function getCategory($id){
        $sql = "SELECT * FROM categories WHERE id = :id";
        return $this->pdo->execQuery($sql, [$id]); 
    }   

    public function getCategoryId($name){
        $sql = "SELECT id FROM categories WHERE name = :name";
        return $this->pdo->execQuery($sql , [$name]); 
    }

    public function deleteCategory($id){
        $sql = "DELETE FROM categories WHERE id = :id";
        return $this->pdo->execQuery($sql, [$id]);
    }

}