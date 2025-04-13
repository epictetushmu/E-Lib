<?php
namespace E-Lib\Controllers;

use E-Lib\Services\BookService; 
use E-Lib\Services\CategoriesService;
use E-Lib\Includes\ResponseHandler;
use E-Lib\Includes\Controller;

class BookController extends Controller {
    private $bookService;
    private $categoriesService;
    private $respond; 

    public function __construct() {
        $this->bookService = new BookService();
        $this->respond = new ResponseHandler();
        $this->categoriesService = new CategoriesService();
    }   

    public function featuredBooks() {
        $books = $this->bookService->getFeaturedBooks();
        if ($books) {
            $this->respond->respond(200, $books);
        } else {
            $this->respond->respond(404, 'No books found');
        }
    }   

    public function listBooks() {
        $books = $this->bookService->getAllBooks();
        if ($books) { 
            return $this->respond->respond(200, $books);
        } else {
            return $this->respond->respond(404, 'No books found');
        }
    }

    public function viewBook($id) {
        $book = $this->bookService->getBookDetails($id);
        if ($book) {
            return $this->respond->respond(200, $book);
        } else {
            return $this->respond->respond(404, 'Book not found');
        }
    }

    public function searchBooks($search) {
        $books = $this->bookService->searchBooks($search);
        if ($books) {
            return $this->respond->respond(200, $books);
        } else {
            return $this->respond->respond(404, 'No books found');
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
            return $this->respond->respond(200, $response);
        } else {
            return $this->respond->respond(400, 'Error adding book');
    
        }
    }
}
