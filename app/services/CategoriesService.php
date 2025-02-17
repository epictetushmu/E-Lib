<?php 
require_once(__DIR__ . '/../models/Categories.php');

class CategoriesService{ 
    private $categories; 

    public function __construct(){
        $this->categories = new Categories();
    }
    public function getCategoryId($name){
        return $this->categories->getCategoryId($name);
    }
    public function getCategory($id){
        return $this->categories->getCategory($id);
    }

    public function addCategory($data){
        return $this->categories->addCategory($data);
    }
}