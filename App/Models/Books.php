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

    public function getBookDetails($id) {
        return $this->db->findOne($this->collection, ['_id' => new ObjectId($id)]);
    }

    public function getFeaturedBooks() {
        
        $pipeline = [
            ['$match' => ['featured' => true]],
            ['$sample' => ['size' => 20]]
        ];
        return $this->db->getFeatured($this->collection, $pipeline);
    }

    public function addBook($book) {
        return  $this->db->insert($this->collection, $book);
    }

    public function searchBooks($search) {
        $regex = new Regex($search, 'i'); // case-insensitive
        return $this->db->find($this->collection, [
            '$or' => [
                ['title' => $regex],
                ['author' => $regex]
            ]
        ]);
    }
}
