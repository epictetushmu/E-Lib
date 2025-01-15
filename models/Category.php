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

    public function getBookCategories($bookId){ 
        try{
            $stmt = $this->db->prepare("SELECT * FROM Category WHERE id IN (SELECT category_id FROM BookCategory WHERE book_id = ?)"); 
            $stmt->bind_param("i", $bookId); 
            $stmt->execute(); 

            $result = $stmt->get_result(); 
            return ['status' => true, 'data' => $result->fetch_all(MYSQLI_ASSOC)]; 
        }catch(\Exception $e){
            return ['status' => false, 'error' => $e->getMessage()]; 
        }
    }
}