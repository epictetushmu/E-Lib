<?php
namespace App\Models;

use App\Controllers\DbController;
use App\Models\Categories;
use MongoDB\BSON\UTCDateTime;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;

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

    // TODO: Return  max 20 random books 
    public function getFeaturedBooks() {
        return $this->db->find($this->collection,  ['sort' => ['featured' => true], 'limit' => 20]);
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
