<?php
class Book {
    private $db;

    public function __construct($dbConnection) {
        $this->db = $dbConnection;
    }

    // Method to add a new book
    public function addBook($title, $author, $year, $copies, $description, $category) {
        $stmt = $this->db->prepare("INSERT INTO books (title, author, year, copies, description, category) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssiss", $title, $author, $year, $copies, $description, $category);

        if ($stmt->execute()) {
            return true;
        } else {
            return $stmt->error;
        }
    }

    // Method to retrieve all books
    public function getAllBooks() {
        $result = $this->db->query("SELECT * FROM books");
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // Method to search books by title
    public function searchBooks($title) {
        $stmt = $this->db->prepare("SELECT * FROM books WHERE title LIKE ?");
        $searchTerm = "%" . $title . "%";
        $stmt->bind_param("s", $searchTerm);
        $stmt->execute();

        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
}