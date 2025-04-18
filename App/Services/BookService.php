<?php
namespace App\Services;

use App\Models\Books;
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

    public function addBook($title, $author, $year, $condition, $copies, $description, $categories, $pdfPath = null, $thumbnailPath = null) {
        // Add validation here
        
        $book = [
            'title' => $title,
            'author' => $author,
            'year' => (int)$year,
            'condition' => $condition,
            'copies' => (int)$copies,
            'description' => $description,
            'categories' => $categories,
            'created_at' => new \MongoDB\BSON\UTCDateTime(),
            'updated_at' => new \MongoDB\BSON\UTCDateTime()
        ];
        
        // Add PDF paths if available
        if ($pdfPath) {
            $book['pdf_path'] = $pdfPath;
        }
        
        if ($thumbnailPath) {
            $book['thumbnail_path'] = $thumbnailPath;
        }
        
        try {
            $result = $this->booksCollection->insertOne($book);
            return ['insertedId' => (string)$result->getInsertedId()];
        } catch (\Exception $e) {
            error_log("Error adding book: " . $e->getMessage());
            return null;
        }
    }

    public function searchBooks($search) {
        return $this->book->searchBooks($search);
    }
}

