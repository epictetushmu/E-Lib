<?php
namespace App\Controllers;

use App\Services\BookService;
use App\Models\Book;
use App\Models\Categories;
use App\Controllers\UserController; 

class PageController {
    private $bookModel;
    private $categoryModel;
    private $userController;
    
    public function __construct() {
        $this->bookModel = new Book();
        $this->categoryModel = new Categories();
        $this->userController = new UserController();
    }
    
    public function home() {
        include __DIR__ . '/../views/home.php';
    }

    public function loginForm() {
        include __DIR__ . '/../views/login.php';
    }

    public function signupForm() {
        include __DIR__ . '/../views/signup.php';
    }
    
    public function logout() {
        $this->userController->logout();
    }
    
    public function profile() {
        $this->userController->displayProfile();
    }

    public function listBooks() {
        $books = $this->bookModel->getAllBooks();
        $categories = $this->categoryModel->getAllCategories();
        include __DIR__ . '/../views/list_books.php';
    }

    public function viewBook($id) {
        $bookDetails = $this->bookModel->getBookDetails($id);
        $book = !empty($bookDetails) ? $bookDetails[0] : null;
        
        if (!$book) {
            $this->error();
            return;
        }
        
        include __DIR__ . '/../views/view_book.php';
    }

    public function addBookForm() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /E-Lib/login');
            exit();
        }
        
        $categories = $this->categoryModel->getAllCategories();
        include __DIR__ . '/../views/add_book.php';
    }

    public function searchBooks() {
        $query = $_GET['q'] ?? '';
        $category = $_GET['category'] ?? '';
        $author = $_GET['author'] ?? '';
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        
        $filters = [];
        if (!empty($query)) $filters['q'] = $query;
        if (!empty($category)) $filters['category'] = $category;
        if (!empty($author)) $filters['author'] = $author;
        
        $searchResults = $this->bookModel->searchBooks($filters, ($page - 1) * 12, 12);
        $totalBooks = $this->bookModel->countSearchResults($filters);
        $totalPages = ceil($totalBooks / 12);
        $categories = $this->categoryModel->getAllCategories();
        
        include __DIR__ . '/../views/search_results.php';
    }

    public function error() {
        include __DIR__ . '/../views/404.php';
    }
}
