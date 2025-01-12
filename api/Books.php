<?php
require 'Database.php';
require '../models/Books.php';

class Api {
    private $db;
    private $book;

    public function __construct() {
        // Enable CORS
        header("Access-Control-Allow-Origin: http://localhost:8000"); // Allow only your frontend origin
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");

        // Handle preflight requests
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            http_response_code(200);
            exit();
        }

        // Initialize the database connection and the Book model
        $this->db = new Database();
        $this->book = new Book($this->db->getConnection());
    }
    public function handleRequest() {

        //Enable CORS for all origins 
      
        // Get the request method and endpoint
        $method = $_SERVER['REQUEST_METHOD'];
        $endpoint = $_GET['api'] ?? '';
        // echo $endpoint; // addBook 
        // Route the request to the appropriate method
        switch ($endpoint) {
            case 'addBook':
                $this->addBook($method);
                break;
            case 'getAllBooks':
                $this->getAllBooks($method);
                break;
            case 'searchBooks':
                $this->searchBooks($method);
                break;
            default:
                $this->respond(404, ['error' => 'Endpoint not found']);
        }
    }

    private function addBook() {
        $data = json_decode(file_get_contents('php://input'), true);

        $title = $data['title'];
        $description = $data['description'];
        $year = $data['year'];
        $copies = $data['copies'];
        $category = $data['category'];
        $condition = $data['condition'];

        // Validate and sanitize input data as needed

        // Insert the book into the database
        $stmt = $this->book->addBook($title, $description, $year, $copies, $category, $condition);

        if ($stmt) {
            echo json_encode(['message' => 'Book added successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['message' => 'Error adding book']);
        }
    }

    private function getAllBooks($method) {
        if ($method !== 'GET') {
            $this->respond(405, ['error' => 'Method not allowed']);
            return;
        }

        $books = $this->book->getAllBooks();
        $this->respond(200, $books);
    }

    private function searchBooks($method) {
        if ($method !== 'GET') {
            $this->respond(405, ['error' => 'Method not allowed']);
            return;
        }

        $title = $_GET['title'] ?? '';
        $books = $this->book->searchBooks($title);
        $this->respond(200, $books);
    }

    private function respond($statusCode, $data) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}

// Handle the API request
$api = new Api();
$api->handleRequest();
