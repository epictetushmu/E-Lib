<?php
namespace App\Controllers;

use App\Includes\ResponseHandler;
use App\Services\BookService;
use Exception;

class PageController {
    private $response;   
    
    public function __construct() {
        // Initialize any services or dependencies here
        $this->response = new ResponseHandler();
    }

    public function home() {
        // Instead of using parent render, use ResponseHandler's renderView
        $this->response->renderView(__DIR__ . '/../Views/home.php');
    }

    public function loginForm() {
        $this->response->renderView(__DIR__ . '/../Views/login.php');
    }

    public function signupForm() {
        $this->response->renderView(__DIR__ . '/../Views/signup.php');
    }

    public function viewBook($path = null, $id = null) {
        try {
            // Remove debug echo statements that cause output before headers
            if (is_null($id)) {
                echo "Invalid book ID. id is null.";
                $this->error();
                return;
            }
            
            $bookService = new BookService();
            $book = $bookService->getBookDetails($id);
            
            if ($book) {
                $this->response->renderView(__DIR__ . '/../Views/book_detail.php', ['book' => $book]);
            } else {
                $this->error();
            }
        } catch(Exception $e) {
            // Log the error but don't echo it (causes header issues)
            error_log("Book View Error: " . $e->getMessage());
            $this->error();
        }
    }

    public function addBookForm() {
        $this->response->renderView(__DIR__ . '/../Views/add_book.php');
    }

    public function searchBooks() {
        $searchQuery = $_GET['q'] ?? '';
        if (empty($searchQuery)) {
            $this->response->renderView(__DIR__ . '/../Views/search.php', [
                'error' => 'Please enter a search term'
            ]);
            return;
        }
        
        $bookService = new BookService();
        $results = $bookService->searchBooks($searchQuery);        
        $this->response->renderView(__DIR__ . '/../Views/search_results.php', [
            'query' => $searchQuery,
            'results' => $results
        ]);
    }

    public function profile(){
        $this->response->renderView(__DIR__ . '/../Views/profile.php');
    }

    public function error() {
        $this->response->renderView(__DIR__ . '/../Views/error.php', [], 404);
    }

    public function viewBooks() {
        $bookService = new BookService();
        $books = $bookService->getAllBooks();
        $this->response->renderView(__DIR__ . '/../Views/view_books.php', ['books' => $books]);
    }   
}
