<?php
namespace  Services;

use  Models\Books;
class BookService {
    private $book;

    public function __construct() {
        $this->book = new Books();
    }

    public function getAllBooks() {
        return $this->book->getAllBooks();
    }

    public function getFeaturedBooks(){ 
        return $this->book->getFeaturedBooks(); 
    }

    public function getBookDetails($id) {
        return $this->book->getBookDetails($id);
    }

    public function addBook($title, $author, $year, $condition, $copies,  $description, $category) {
        return $this->book->addBook($title, $author,$year , $condition, $copies,  $description, $category);
    }

    public function searchBooks($search) {
        return $this->book->searchBooks($search);
    }
}

