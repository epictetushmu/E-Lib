<?php 
require("../db/connection.php"); 
require("../models/Category.php"); 


class Categories{ 

    private $db; 
    private $category; 

    public function __construct() { 
        $this->db = new Database(); 
        $this->category = new Category($this->db->getConnection()); 

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
            default: 
                $this->respond(status_code: 404 , data: ['error' => 'Endpoint not found']);
        }
    }

    private function addCategory($method){
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
            http_response_code(500);
            echo json_encode(['message' => 'Error adding book', 'error' => $stmt]);
        }
    }
}