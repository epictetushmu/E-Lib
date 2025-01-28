<?php

class Categories{
    private $pdo; 

    public function __construct() {
        $this->pdo = Database::getInstance();
    }

    public function getCategory($id){
        $sql = "SELECT * FROM categories WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam("i", $id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        //return id of category
    }   

    public function getCategoryId($name){
        $sql = "SELECT id FROM categories WHERE name = :name";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam("s", $name);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function addCategory($name){
        $sql = "INSERT INTO categories (`name`) VALUES (:name)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam("s", $name);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function deleteCategory($id){
        $sql = "DELETE FROM categories WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam("i", $id);
        return $stmt->execute();
    }

}