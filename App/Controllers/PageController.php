<?php
namespace App\Controllers;

use App\Includes\ResponseHandler;
use App\Services\BookService; 

class PageController {
    private $response;   
    
    public function __construct() {
        // Initialize any services or dependencies here
        $this->response = new ResponseHandler();
    }

    public function home() {
        // Instead of using parent render, use ResponseHandler's renderView
        $this->response->renderView(__DIR__ . '/../views/home.php');
    }

    public function loginForm() {
        $this->response->renderView(__DIR__ . '/../views/login.php');
    }

    public function signupForm() {
        $this->response->renderView(__DIR__ . '/../views/signup.php');
    }

    public function viewBook() {
        $id = $_GET['q'] ?? '';      
        $bookService = new BookService();
        $book = $bookService->getBookDetails($id);
        if ($book) {
            $this->response->renderView(__DIR__ . '/../views/book_detail.php', ['book' => $book]);
        } else {
            $this->error();
        }
    }

    public function addBookForm() {
        $this->response->renderView(__DIR__ . '/../views/add_book.php');
    }

    public function searchBooks() {
        $query = $_GET['q'] ?? '';
        $bookService = new BookService();
        $books = $bookService->searchBooks($query);
        $this->response->renderView(__DIR__ . '/../views/search_results.php', ['books' => $books]);
    }

    public function profile(){
        $this->response->renderView(__DIR__ . '/../views/profile.php');
    }

    public function error() {
        $this->response->renderView(__DIR__ . '/../views/error.php', [], 404);
    }
}
