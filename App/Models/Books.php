<?php
namespace App\Models;

use App\Controllers\DbController;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex; // Ensure this line is present and the MongoDB library is installed

class Books {
    private $db;
    private $collection = 'Books';

    public function __construct() {
        $this->db = DbController::getInstance();
    }

    public function getAllBooks() {
        return $this->db->find($this->collection);
    }

    public function getPublicBooks() { 
        return $this->db->find($this->collection, ['status' => 'public']);
    }

    public function getBookDetails($id) {
        return $this->db->findOne($this->collection, ['_id' => new ObjectId($id)]);
    }

    public function getFeaturedBooks() {
        
        $pipeline = [
            ['$match' => ['featured' => true, 'status' => 'public']],
            ['$sample' => ['size' => 20]]
        ];
        return $this->db->getFeatured($this->collection, $pipeline);
    }

    public function addBook($book) {
        return  $this->db->insert($this->collection, $book);
    }

    public function searchBooks($searchQuery) {
       
        return $this->db->find($this->collection, $searchQuery);
    }

    public function addReview($bookId, $review) {
        try {
            // Return the result of the update operation
            $result = $this->db->update(
                $this->collection, 
                ['_id' => new ObjectId($bookId)], 
                ['$push' => ['reviews' => $review]]
            );
            
            return $result;
        } catch (\Exception $e) {
            error_log("Error adding review: " . $e->getMessage());
            return false;
        }
    }

    public function getBookByTitle($title) {
        $regex = new Regex($title, 'i'); // 'i' for case-insensitive
        return $this->db->findOne($this->collection, ['title' => $regex]);
    }

    public function updateBookRating($bookId, $rating, $reviewCount) {
        try {
            return $this->db->update(
                $this->collection,
                ['_id' => new ObjectId($bookId)],
                [
                    '$set' => [
                        'average_rating' => $rating,
                        'review_count' => $reviewCount
                    ]
                ]
            );
        } catch (\Exception $e) {
            error_log("Error updating book rating: " . $e->getMessage());
            return false;
        }
    }

    public function deleteBook($id) {
        return $this->db->delete($this->collection, ['_id' => new ObjectId($id)]);
    }
    public function updateBook($id, $book) {
        $filteredBook = array_filter($book, function ($value) {
            return $value !== null && $value !== '';
        });

        if (empty($filteredBook)) {
            return false; // No fields to update
        }
        
        return $this->db->update($this->collection, ['_id' => new ObjectId($id)], ['$set' => $filteredBook]);
    }
    
}
