<?php 

class Category { 
    private $db; 
    public function __construct($dbConnection){
        $this->db = $dbConnection; 
    }

    public function addCategory($category){ 
        $stmt = $this->db->prepare(" INSERT INTO Category (`name`) VALUES (?)"); 
        $stmt->bind_param("s", $category); 

        if($stmt->execute()){ 
            return true; 
        }else { 
            return $stmt->error; 
        }
    }
}