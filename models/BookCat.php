<?php 

class BookCat extends Books{
    private $db; 

    public function __construct($dbConnection){
        $this->db = $dbConnection; 

    }

    public function addBookCategory($book_id, $category_id){
        $query = "INSERT INTO book_category (book_id, category_id) VALUES (?, ?)";
        $stmt = $this->db->prepare($query);
        if(!$stmt){
            die('Prepare failed: ' . $this->db->error);
        }
        $stmt->bind_param("ii", $book_id, $category_id);
        if($stmt->execute()){
            $stmt->close();
            return ['status' => true, 'data' => $this->db->insert_id];
        }else{
            return ['status' => false, 'error' => $stmt->error];
        }
    }   
}