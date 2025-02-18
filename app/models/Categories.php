<?php
require_once(__DIR__ . '/../includes/database.php');

class Categories {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance();
    }

    public function getCategory($id){
        $sql = "SELECT * FROM categories WHERE id = :id";
        return $this->pdo->execQuery($sql, [$id]); 
    }   

    public function getCategoryId($name){
        $sql = "SELECT id FROM categories WHERE name = :name";
        return $this->pdo->execQuery($sql , [$name]); 
    }

    public function addCategory($category_id) {
        $sql = "SELECT id FROM categories WHERE id = :id";
        $result = $this->pdo->execQuery($sql, ["id" => $category_id]);
        if ($result) {
            return $result[0]['id'];
        } else {
            $sql = "INSERT INTO categories (id) VALUES (:id)";
            $this->pdo->execQuery($sql, ["id" => $category_id]);
            return $category_id;
        }
    }

    public function deleteCategory($id){
        $sql = "DELETE FROM categories WHERE id = :id";
        return $this->pdo->execQuery($sql, [$id]);
    }

}