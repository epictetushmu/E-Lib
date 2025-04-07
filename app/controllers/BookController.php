<?php
namespace App\Controllers;

use App\Models\Book;
use App\Models\Categories;

class BookController {
    private $bookModel;
    private $categoryModel;
    
    public function __construct() {
        $this->bookModel = new Book();
        $this->categoryModel = new Categories();
    }
    
    public function getFeaturedBooks() {
        $books = $this->bookModel->getFeaturedBooks();
        
        header('Content-Type: application/json');
        if ($books) {
            echo json_encode(['status' => 'success', 'books' => $books]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to fetch books']);
        }
    }
    
    public function getBookDetails($id) {
        $book = $this->bookModel->getBookById($id);
        $reviews = $this->bookModel->getBookReviews($id);
        
        return [
            'book' => $book,
            'reviews' => $reviews
        ];
    }
    
    public function searchBooks($filters = [], $page = 1, $limit = 12) {
        $offset = ($page - 1) * $limit;
        $books = $this->bookModel->searchBooks($filters, $offset, $limit);
        $totalBooks = $this->bookModel->countSearchResults($filters);
        
        $totalPages = ceil($totalBooks / $limit);
        
        return [
            'books' => $books,
            'totalBooks' => $totalBooks,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'filters' => $filters
        ];
    }
    
    public function getCategories() {
        return $this->categoryModel->getAllCategories();
    }
    
    public function addBook() {
        // Check if user is logged in and has permission
        if (!isset($_SESSION['user_id'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'You must be logged in to add a book']);
            return;
        }
        
        // Process form data
        $title = $_POST['title'] ?? '';
        $author = $_POST['author'] ?? '';
        $categories = json_decode($_POST['categories'] ?? '[]');
        $year = $_POST['year'] ?? null;
        $condition = $_POST['condition'] ?? '';
        $copies = $_POST['copies'] ?? 0;
        $description = $_POST['description'] ?? '';
        
        if (empty($title)) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Book title is required']);
            return;
        }
        
        // Process cover image if uploaded
        $coverPath = null;
        if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../public/uploads/covers/';
            
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $filename = uniqid() . '_' . basename($_FILES['cover']['name']);
            $uploadFile = $uploadDir . $filename;
            
            if (move_uploaded_file($_FILES['cover']['tmp_name'], $uploadFile)) {
                $coverPath = '/E-Lib/uploads/covers/' . $filename;
            }
        }
        
        $bookId = $this->bookModel->addBook(
            $title,
            $author,
            $year,
            $condition,
            $copies,
            $description,
            $categories
        );
        
        header('Content-Type: application/json');
        if ($bookId) {
            echo json_encode(['status' => 'success', 'message' => 'Book added successfully', 'book_id' => $bookId]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to add book']);
        }
    }
    
    public function addReview() {
        if (!isset($_SESSION['user_id'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'You must be logged in to add a review']);
            return;
        }
        
        // Get JSON data from request body
        $data = json_decode(file_get_contents('php://input'), true);
        
        $bookId = $data['book_id'] ?? 0;
        $rating = $data['rating'] ?? 0;
        $comment = $data['comment'] ?? '';
        
        if (!$bookId || !$rating || !$comment) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
            return;
        }
        
        $result = $this->bookModel->addReview($bookId, $_SESSION['user_id'], $rating, $comment);
        
        header('Content-Type: application/json');
        if ($result) {
            echo json_encode(['status' => 'success', 'message' => 'Review added successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to add review']);
        }
    }
    
    public function borrowBook() {
        if (!isset($_SESSION['user_id'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'You must be logged in to borrow books']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $bookId = $data['book_id'] ?? 0;
        
        if (!$bookId) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Invalid book ID']);
            return;
        }
        
        $result = $this->bookModel->borrowBook($bookId, $_SESSION['user_id']);
        
        header('Content-Type: application/json');
        if ($result === true) {
            echo json_encode(['status' => 'success', 'message' => 'Book borrowed successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $result ?: 'Failed to borrow book']);
        }
    }
    
    public function saveToList() {
        if (!isset($_SESSION['user_id'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'You must be logged in to save books']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $bookId = $data['book_id'] ?? 0;
        
        if (!$bookId) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Invalid book ID']);
            return;
        }
        
        $result = $this->bookModel->saveToList($bookId, $_SESSION['user_id']);
        
        header('Content-Type: application/json');
        if ($result === true) {
            echo json_encode(['status' => 'success', 'message' => 'Book saved to your list']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $result ?: 'Failed to save book']);
        }
    }
}
