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
            $categoryId = $stmt->insert_id; 
            $stmt->close();
            return ['status' => true , 'categoryId' => $categoryId]; 
        }else { 
            return ['status' => false , 'error' =>$stmt->error]; 
        }
    }
}