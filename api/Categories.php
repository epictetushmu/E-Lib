<?php 
require("../db/connection.php"); 
require("../models/Category.php"); 
require('../models/BookCat.php');

class Categories{ 

    private $db; 
    private $category; 
    private $bookCat; 

    public function __construct() { 
        $this->db = new Database(); 
        $this->category = new Category($this->db->getConnection()); 
        $this->bookCat = new BookCat($this->db->getConnection());
        // Enable CORS
        header("Access-Control-Allow-Origin: http://localhost:8000"); // Allow only your frontend origin
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authentication, Authorization");
    }


    public function handleRequest(){

        $method = $_SERVER['REQUEST_METHOD']; 
        $endpoint = $_GET['api'] ?? '';

        switch($endpoint){ 
            case 'addCategory': 
                $this->addCategory($method); 
                break; 
            case 'addCategories': 
                $this->addCategories($method); 
                break;
            default: 
                $this->respond( 404 , data: ['error' => 'Endpoint not found']);
        }
    }

    private function addCategory($method){
        try{
            if ($method != 'POST'){
                $this->respond(405,['error' => 'Endpoint not found']);
                return; 
            }
            $data = json_decode(file_get_contents('php://input'), true);

            $name = $data['name']; 

            $stmt = $this->category->addCategory($name); 
            
            if ($stmt) {
                echo json_encode(['message' => 'Book added successfully']);
            } else {
            $this->respond(500, data: ['error'=> 'Book not added']);
            }
        }catch (Exception $e){
            $this->respond(500, data: ['error'=> $e->getMessage()]);
        }
    }

    private function addCategories($method){    
        try {  
            if ($method != 'POST'){
                $this->respond(405, ['error'=> 'Endpoint not found']);
            }
            $data = json_decode(file_get_contents('php://input'), true);
            $categories = $data['category'];
            foreach($categories as $category){
                $stmt = $this->category->addCategory($category);
            }
            if ($stmt) {
                $this->respond(200, ['message' => 'Book added successfully']);
            } else {
            $this->respond(500, data: ['error'=> 'Book not added']);
            }
        }catch (Exception $e){
            $this->respond(500, data: ['error'=> $e->getMessage()]);
        }
    }
    private function respond($statusCode, $data) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}