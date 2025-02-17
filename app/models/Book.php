<?php
require_once(__DIR__ . '/../includes/database.php');
require_once(__DIR__ . '/../models/Categories.php');

class Book {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance();
    }

    public function getAllBooks() {
        $sql = 'SELECT * FROM books';
        return $this->pdo->execQuery($sql);
    }

    public function getBookDetails($id) {
        $sql = "SELECT * FROM books WHERE id = :id";
        return $this->pdo->execQuery($sql, array("id" => $id));
    }

    public function getFeaturedBooks() {
        $sql = "SELECT * FROM books ORDER BY id DESC LIMIT 20";
        return $this->pdo->execQuery($sql);
    }

    public function addBook($title, $author, $year, $condition, $copies, $description, $categories) {
        $sql = "INSERT INTO books (title, author, year, `condition`, copies, description, cover) VALUES (:title, :author, :year, :condition, :copies, :description, :cover)";
        $book = [
            "title" => $title,
            "author" => $author,
            "year" => $year,
            "condition" => $condition,
            "copies" => $copies,
            "description" => $description,
            "cover" => "default.jpg"
        ];
        $bookId = $this->pdo->execQuery($sql, $book, true); 

        $category = new Categories();
        foreach ($categories as $category_id) {
            $categoryId = $category->addCategory($category_id);
            $sql = "INSERT INTO book_categories (book_id, category_id) VALUES (:book_id, :category_id)"; 
            $this->pdo->execQuery($sql, ["book_id" => $bookId, "category_id" => $categoryId]);
        }
        return $bookId;
    }

    public function searchBooks($search) {
        $sql = "SELECT * FROM books WHERE title LIKE :search OR author LIKE :search";
        return $this->pdo->execQuery($sql, ["search" => "%$search%"]);
    }
}
