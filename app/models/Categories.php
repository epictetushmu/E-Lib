<?php
require_once("../includes/database.php");

class Categories{
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

    public function addCategory($category){
        $sql = "INSERT INTO categories (`name`) VALUES (:name)";
        return $this->pdo->execQuery($sql, $category, true);
    }

    public function deleteCategory($id){
        $sql = "DELETE FROM categories WHERE id = :id";
        return $this->pdo->execQuery($sql, [$id]);
    }

}