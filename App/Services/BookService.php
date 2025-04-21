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

    public function addBook(string $title, string $author,int $year, string $description, array $categories , $pdfPath = null, $thumbnailPath = null) {
        // Add validation here
        
        $book = [
            'title' => $title,
            'author' => $author,
            'year' => (int)$year,
            'description' => $description,
            'categories' => $categories,
            'pdf_path' => $pdfPath,
            'thumbnail_path' => $thumbnailPath,
            'featured' => random_int(0, 100)< 20 ? true : false, 
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
            return $this->book->addBook($book);
        } catch (\Exception $e) {
            echo("Error adding book: " . $e->getMessage());
            return null;
        }
    }

    public function searchBooks($search) {
        return $this->book->searchBooks($search);
    }
}

