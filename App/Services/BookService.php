<?php
namespace App\Services;

use App\Models\Books;
use MongoDB\BSON\UTCDateTime;

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
            'created_at' => new UTCDateTime(),
            'updated_at' => new UTCDateTime()
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

    /**
     * Search for books based on multiple criteria
     */
    public function searchBooks($params) {
        // If only a string is passed, treat it as a title search (backwards compatibility)
        if (is_string($params)) {
            $params = ['title' => $params];
        }
               
        $query = [];
        if (!empty($params['title'])) {
            $query['title'] = ['$regex' => $params['title'], '$options' => 'i'];
        }        
        if (!empty($params['author'])) {
            $query['author'] = ['$regex' => $params['author'], '$options' => 'i'];
        }        
        if (!empty($params['category'])) {
            $query['categories'] = ['$in' => [$params['category']]];
        }
        
        if (empty($query)) {
            return [];
        }
        
        try {
            $books = $this->book->searchBooks($query);
            return $books;
        } catch (\Exception $e) {
            error_log("Search error: " . $e->getMessage());
            return [];
        }
    }
}

