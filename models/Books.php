<?php

class Book {
    private $db;

    public function __construct($dbConnection) {
        $this->db = $dbConnection;
    }

    public function addBook($title, $author, $year, $condition, $copies, $description) {
        // Ensure $year and $copies are integers
        $year = (int) $year;
        $copies = (int) $copies;
        
        // Prepare the SQL query with positional placeholders
        $query = "INSERT INTO book (title, author, publication_year, `condition`, number_of_copies, `description`)
        VALUES (?, ?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($query);
        // Check if statement preparation succeeded
        if (!$stmt) {
         die('Prepare failed: ' . $this->db->error);
        }

        $stmt->bind_param("ssisis", $title, $author, $year, $condition, $copies, $description);
        // Execute the statement and check if successful
        if ($stmt->execute()) {
            $bookId = $stmt->insert_id;
            $stmt->close();
            return ['status' => true, 'data' => 'Book added successfully', 'bookId' => $bookId];
        } else {
            return ['status'=> false , 'error' => 'Something went wrong']; // Return the error message if execution fails
        }
    }    

    // Method to retrieve all books
    public function getAllBooks() {
        $result = $this->db->query("SELECT * FROM book");
        return ['status' => 'success', 'data' => $result->fetch_all(MYSQLI_ASSOC)];
    }

    // Method to search books by title
    public function searchBooks($title) {
        $stmt = $this->db->prepare("SELECT * FROM book WHERE title LIKE ?");
        $searchTerm = "%" . $title . "%";
        $stmt->bind_param("s", $searchTerm);
        $stmt->execute();

        $result = $stmt->get_result();
        return ['status' => 'success', 'data' => $result->fetch_all(MYSQLI_ASSOC)];

    }

    public function getBookDetails($bookId) {
        try{
            $stmt = $this->db->prepare("SELECT * FROM book WHERE id = ?");
            $stmt->bind_param("i", $bookId);
            $stmt->execute();

            $result = $stmt->get_result();
            $stmt->close();
            return ['status' => true, 'data' => $result->fetch_assoc()];
        } catch (\Exception $e) {
            return ['status' => false, 'error' => $e->getMessage()];   
        }
    }
}