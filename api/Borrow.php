<?php
require('../db/connection.php');
require('../models/Books.php');

class Borrow {
    private $db;
    private $borrow;

    public function __construct() {
        // Initialize the database connection and the Borrow model
        $this->db = new Database();
        $this->borrow = new Borrow($this->db->getConnection());
        
        // Enable CORS
        header("Access-Control-Allow-Origin: http://localhost:8000"); // Allow only your frontend origin
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authentication, Authorization");
        
        
    }

    public function handleRequest() {
        
        // Handle preflight requests
        // if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        //     http_response_code(200);
        //     exit();
        // }
        //Enable CORS for all origins 
      
        // Get the request method and endpoint
        $method = $_SERVER['REQUEST_METHOD'];
        $endpoint = $_GET['api'] ?? '';
        // echo $endpoint; // BorrowBook 
        // Route the request to the appropriate method
        switch ($endpoint) {
            case 'BorrowBook':
                $this->BorrowBook($method);
                break;
            case 'returnBook':
                $this->returnBook($method);
                break;
            default:
                $this->respond(404, ['error' => 'Endpoint not found']);
        }
}
private function borrowBook($method) {
        
    if ($method != 'POST') {
        $this->respond(405, ['error' => 'Method not allowed']);
        return;

    }
        $data = json_decode(file_get_contents('php://input'), true);

        $book_id = $data['book_id'];
        $borrower_name = $data['borrower_name']; 
        $borrow_date = $data['borrow_date'];
        $return_date = $data['return_date'];
  
        
        $stmt = $this->borrow->borrowBook($book_id, $borrower_name, $borrow_date, $return_date);

        if ($stmt) {
            echo json_encode(['message' => 'borrow added successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['message' => 'Error adding borrow', 'error' => $stmt]);
        }

    
}
private function returnBook($method) {
    if ($method !== 'GET') {
        $this->respond(405, ['error' => 'Method not allowed']);
        return;
    }

    $borrow = $this->borrow->returnBook();
    $this->respond(200, $borrow);
}

}

$api = new Books();
$api->handleRequest();
