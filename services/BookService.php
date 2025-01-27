<?php
require_once('../models/Book.php');

class BookService {
    private $book;

    public function __construct() {
        $this->book = new Book();
    }

    public function getAllBooks() {
        return $this->book->getAllBooks();
    }

    public function getBookById($id) {
        return $this->book->getBookById($id);
    }

    public function addBook($title, $author, $description) {
        return $this->book->addBook($title, $author, $description);
    }
}
