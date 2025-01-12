<?php

class Book {
    private $db;

    public function __construct($dbConnection) {
        $this->db = $dbConnection;
    }

    public function addBook($title, $author, $year, $condition, $copies, $description, $category) {
        // Ensure $year and $copies are integers
        $year = (int) $year;
        $copies = (int) $copies;
    
        // Prepare the SQL query with placeholders
        $stmt = $this->db->prepare("INSERT INTO book (title, author, `publication_year`, `condition`, `number_of_copies`, `description`, category_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
        // Bind the parameters with correct data types
        $stmt->bind_param("sssiiss", $title, $author, $year, $condition, $copies, $description, $category);
    
        // Execute the statement and check if successful
        if ($stmt->execute()) {
            return true;
        } else {
            return $stmt->error; // Return the error message if execution fails
        }
    }    

    // Method to retrieve all books
    public function getAllBooks() {
        $result = $this->db->query("SELECT * FROM book");
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // Method to search books by title
    public function searchBooks($title) {
        $stmt = $this->db->prepare("SELECT * FROM book WHERE title LIKE ?");
        $searchTerm = "%" . $title . "%";
        $stmt->bind_param("s", $searchTerm);
        $stmt->execute();

        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
}