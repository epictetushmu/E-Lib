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

    public function deleteBook($id){ 
        return $this->book->deleteBook($id); 
    }

    public function getPublicBooks() {
        return $this->book->getPublicBooks();
    }

    public function updateBook($id, string $title, string $author, string $year, string $description, array $categories, string $status, $featured, string $isbn, bool $downloadable = true) {
        // Add validation here
  
        // Properly handle featured parameter conversion to boolean
        $featuredBool = false;
        if ($featured === true || $featured === 'true' || $featured === 1 || $featured === '1') {
            $featuredBool = true;
        }
    
        $book = [
            'title' => $title,
            'author' => $author,
            'year' => (int)$year?? null,
            'description' => $description,
            'categories' => $categories,
            'isbn' => $isbn,
            'downloadable' => $downloadable,
            'featured' => $featuredBool,
            'status' => $status,
            'updated_at' => new UTCDateTime()
        ];
        
        return $this->book->updateBook($id, $book);
    }

    public function getBookDetails($id) {
        return $this->book->getBookDetails($id);
    }

    public function addBook(string $title, string $author, string $year, string $description, array $categories, string $isbn, $filePath = null, $thumbnailPath = null, bool $downloadable = true, string $fileType = 'pdf', string $fileExtension = 'pdf') {
        // Add validation here
        
        $book = [
            'title' => $title,
            'author' => $author,
            'year' => (int)$year?? null,
            'description' => $description,
            'categories' => $categories,
            'isbn' => $isbn,
            'file_path' => $filePath,          // Renamed from pdf_path for clarity
            'file_type' => $fileType,          // New field to store the file format type
            'file_extension' => $fileExtension, // New field to store the file extension
            'thumbnail' => $thumbnailPath,
            'created_at' => new UTCDateTime(),
            'updated_at' => new UTCDateTime(),
            'status' => 'draft',
            'views' => 0,
            'downloads' => 0,
            'featured' => false,
            'downloadable' => $downloadable,
            'reviews' => []
        ];
        
        return $this->book->addBook($book);
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
            if (is_array($books)) {
                return $books; // Ensure all matching books are returned
            }
            return []; // Return an empty array if no matches are found
        } catch (\Exception $e) {
            error_log("Search error: " . $e->getMessage());
            return [];
        }
    }

    public function addReview($bookId, $review, $rating = null) {
        // Make sure rating is included
        if (isset($rating) && !isset($review['rating'])) {
            $review['rating'] = intval($rating);
        }
        
        // Make sure review has a timestamp if not already set
        if (!isset($review['created_at'])) {
            $review['created_at'] = date('Y-m-d H:i:s');
        }
        
        // Add the review to the book
        $result = $this->book->addReview($bookId, $review);
        
        if ($result) {
            // Update the book's average rating
            $this->updateBookRating($bookId);
        }
        
        return $result;
    }

    public function getBookByTitle($title) {
        return $this->book->getBookByTitle($title);
    }

    /**
     * Update book's average rating based on all reviews
     */
    private function updateBookRating($bookId) {
        $book = $this->getBookDetails($bookId);
        if (!$book || empty($book['reviews'])) {
            return false;
        }
        
        $totalRating = 0;
        $count = 0;
        
        foreach ($book['reviews'] as $review) {
            if (isset($review['rating'])) {
                $totalRating += $review['rating'];
                $count++;
            }
        }
        
        $averageRating = $count > 0 ? round($totalRating / $count, 1) : 0;
        
        // Update the book with the new average rating
        return $this->book->updateBookRating($bookId, $averageRating, $count);
    }

    public function getBookReviews($bookId) {
        $book = $this->book->getBookDetails($bookId);
        if ($book) {
            return $book['reviews'] ?? [];
        }
        return [];
    }
}

