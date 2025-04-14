<?php
namespace App\Controllers;

use App\Services\BookService; 
use App\Services\CategoriesService;
use App\Includes\ResponseHandler;

class BookController {
    private $bookService;
    private $categoriesService;

    private $response; 

    public function __construct() {
        $this->bookService = new BookService();
        $this->categoriesService = new CategoriesService();
        $this->response = new ResponseHandler();
    }   

    public function featuredBooks() {
        $books = $this->bookService->getFeaturedBooks();
        if ($books) {
            $this->response->respond(true, $books);
        } else {
            $this->response->respond(false, 'No books found', 404);
        }
    }   

    public function listBooks() {
        $books = $this->bookService->getAllBooks();
        if ($books) { 
            return $this->response->respond(true, $books);
        } else {
            return $this->response->respond(false, 'No books found', 404);
        }
    }

    public function viewBook($id) {
        $book = $this->bookService->getBookDetails($id);
        if ($book) {
            return $this->response->respond(true, $book);
        } else {
            return $this->response->respond(false, 'Book not found', 404);
        }
    }

    public function searchBooks($search) {
        $books = $this->bookService->searchBooks($search);
        if ($books) {
            return $this->response->respond(true, $books);
        } else {
            return $this->response->respond(false, 'No books found', 404);
        }
    }
    
    public function addBook() {
        $data = $_POST;
        $title = $data['title'];
        $author = $data['author'];
        $year = $data['year'];
        $condition = $data['condition'];
        $copies = $data['copies'];
        $description = $data['description'];
        // $cover = $_FILES['cover']['name'];
        $categories = json_decode($data['category'], true); 

        //file upload
        // move_uploaded_file($_FILES['cover']['tmp_name'], '../uploads/' . $cover);

        // Convert category names to category IDs
        $categoryIds = [];
        foreach ($categories as $categoryName) {
            $category = $this->categoriesService->getCategoryId($categoryName);
            if ($category) {
                $categoryIds[] = $category['id'];
            } else {
                $newCategoryId = $this->categoriesService->addCategory($categoryName);
                $categoryIds[] = $newCategoryId;
            }
        }

        
            $response = $this->bookService->addBook($title, $author, $year, $condition, $copies, $description, $categories);
           if ($response) {
            return $this->response->respond(true, $response);
        } else {
            return $this->response->respond(false, 'Error adding book', 400);
    
        }
    }
}
