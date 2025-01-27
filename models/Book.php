<?php
require_once('../includes/database.php');
require_once('../models/Category.php');
class Book {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance();
    }

    public function getAllBooks() {
        $stmt = $this->pdo->prepare("SELECT * FROM books");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getBookById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM books WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function addBook($title, $author, $year, $condition, $copies, $description, $categories) {
        $sql = "INSERT INTO books (title, author, year, `condition`, copies, description, cover) VALUES (:title, :author, :year, :condition, :copies, :description, :cover)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(":title", $title);
        $stmt->bindParam(":author", $author);
        $stmt->bindParam(":year", $year);
        $stmt->bindParam(":condition", $condition);
        $stmt->bindParam(":copies", $copies);
        $stmt->bindParam(":description", $description);
        $stmt->execute();
        $book_id = $this->pdo->lastInsertId();
        $category = new Categories();
        foreach ($categories as $category_id) {
            $categoryId = $category->addCategory($category_id);
            $stmt = $this->pdo->prepare("INSERT INTO book_categories (book_id, category_id) VALUES (:book_id, :category_id)");
            $stmt->bindParam("i", $book_id);
            $stmt->bindParam("i", $categoryId);
            $stmt->execute();
        }
        return $book_id;
    }
}
