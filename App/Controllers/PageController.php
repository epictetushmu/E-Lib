<?php
namespace App\Controllers;

use App\Includes\ResponseHandler; 
use App\Includes\SessionManager;
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
        $this->response->renderView(__DIR__ . '/../Views/home.php', [
           'isLoggedIn' => SessionManager::getCurrentUserId()
        ]);
    }

    public function dashboard() {
        $this->response->renderView(__DIR__ . '/../Views/admin.php');
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

    public function readBook($path = null, $id = null) {
        try {
            if (is_null($id)) {
                echo "Invalid book ID. id is null.";
                $this->error();
                return;
            }
            
            $bookService = new BookService();
            $book = $bookService->getBookDetails($id);
            
            if ($book) {
                $this->response->renderView(__DIR__ . '/../Views/read_book.php', ['book' => $book]);
            } else {
                $this->error();
            }
        } catch(Exception $e) {
            error_log("Read Book Error: " . $e->getMessage());
            $this->error();
        }
    }

    public function searchBooks() {
        // Get all search parameters
        $title = $_GET['title'] ?? '';
        $author = $_GET['author'] ?? '';
        $category = $_GET['category'] ?? '';
        
        if (empty($title) && empty($author) && empty($category)) {
            $this->response->renderView(__DIR__ . '/../Views/search_results.php', [
                'error' => 'Please enter at least one search term',
                'results' => []
            ]);
            return;
        }
        
        $searchParams = [
            'title' => $title,
            'author' => $author,
            'category' => $category,
        ];
        
        $searchParams = array_filter($searchParams);
        
        // Perform the search
        $bookService = new BookService();
        $results = $bookService->searchBooks($searchParams);
        
        // Prepare data for the view
        $this->response->renderView(__DIR__ . '/../Views/search_results.php', [
            'searchQuery' => $title, // For backward compatibility
            'filters' => [
                'title' => $title,
                'author' => $author,
                'category' => $category,
            ],
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

        // Convert MongoDB objects to plain PHP types
        $books = array_map(function ($book) {
            $book['_id'] = (string) $book['_id']; // Convert ObjectId to string
            $book['categories'] = is_object($book['categories']) ? $book['categories']->getArrayCopy() : $book['categories']; // Convert BSONArray to plain array
            $book['created_at'] = is_object($book['created_at']) ? $book['created_at']->toDateTime()->format('Y-m-d H:i:s') : $book['created_at']; // Convert UTCDateTime to string
            $book['updated_at'] = is_object($book['updated_at']) ? $book['updated_at']->toDateTime()->format('Y-m-d H:i:s') : $book['updated_at']; // Convert UTCDateTime to string

            // Handle reviews if present
            if (isset($book['reviews']) && is_object($book['reviews'])) {
                $book['reviews'] = array_map(function ($review) {
                    $review = $review->getArrayCopy(); // Convert BSONDocument to plain array
                    $review['user_id'] = (string) $review['user_id']; // Convert ObjectId to string
                    return $review;
                }, $book['reviews']->getArrayCopy());
            }

            return $book;
        }, $books);

        $this->response->renderView(__DIR__ . '/../Views/view_books.php', ['books' => $books]);
    }   
}
