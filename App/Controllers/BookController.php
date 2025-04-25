<?php
namespace App\Controllers;

use App\Services\BookService; 
use App\Includes\ResponseHandler;
use App\Helpers\PdfHelper;


class BookController {
    private $bookService;

    private $response; 

    public function __construct() {
        $this->bookService = new BookService();
        $this->response = new ResponseHandler();
    }   

    public function featuredBooks() {
        $books = $this->bookService->getFeaturedBooks();
        foreach ($books as &$book) {
            unset($book['pdf_path']);
            unset($book['reviews']);
        }
        if ($books) {
            $this->response->respond(true, $books);
        } else {
            $this->response->respond(false, 'No books found', 404);
        }
    }   

    public function deleteBook($id) {
        $response = $this->bookService->deleteBook($id);
        if ($response) {
            return $this->response->respond(true, 'Book deleted successfully');
        } else {
            return $this->response->respond(false, 'Error deleting book', 400);
        }
    }

    public function updateBook($id) {
        // Read and decode JSON data from request body
        $requestBody = file_get_contents('php://input');
        $data = json_decode($requestBody, true);
        
        // If JSON parsing failed, check if regular form data exists
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Fall back to $_POST for traditional form submissions
            $data = $_POST;
        }
        
        // First get the current book data
        $currentBook = $this->bookService->getBookDetails($id);
        if (!$currentBook) {
            return $this->response->respond(false, 'Book not found', 404);
        }
        
        // Only update fields that are provided in the request
        $title = isset($data['title']) ? $data['title'] : $currentBook['title'];
        $author = isset($data['author']) ? $data['author'] : $currentBook['author'];
        $year = isset($data['year']) ? $data['year'] : ($currentBook['year'] ?? '');
        $description = isset($data['description']) ? $data['description'] : $currentBook['description'];
        $status = isset($data['status']) ? $data['status'] : $currentBook['status'];
        
        // Check if categories are being updated
        $categories = [];
        if (isset($data['categories'])) {
            // Parse categories from the request
            if (is_string($data['categories'])) {
                // Handle JSON string format
                $categories = json_decode($data['categories'], true) ?? [];
            } else if (is_array($data['categories'])) {
                // Categories already as array
                $categories = $data['categories'];
            }
        } else if (isset($currentBook['categories'])) {
            // Use existing categories if not provided in request
            if ($currentBook['categories'] instanceof \MongoDB\Model\BSONArray) {
                $categories = $currentBook['categories']->getArrayCopy();
            } else if (is_array($currentBook['categories'])) {
                $categories = $currentBook['categories'];
            }
        }
        
        // Update the book in the database
        $response = $this->bookService->updateBook(
            $id, $title, $author, $year, $description, $categories, $status
        );

        if ($response) {
            return $this->response->respond(true, 'Book updated successfully');
        } else {
            return $this->response->respond(false, 'Error updating book', 400);
        }
    }

    public function listBooks() {
        $books = $this->bookService->getAllBooks();
        foreach ($books as &$book) {
            unset($book['pdf_path']);
            unset($book['reviews']);
        } 
        if ($books) {
            return $this->response->respond(true, $books);
        } else {
            return $this->response->respond(false, 'No books found', 404);
        }
    }

    public function getAllBooks() {
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
        // Ensure proper error reporting
        error_log("Book upload attempt started");
        
        // Extract form data
        $title = $_POST['title'] ?? '';
        $author = $_POST['author'] ?? '';
        $year = $_POST['year'] ?? '';
        $description = $_POST['description'] ?? '';
        $categories = json_decode($_POST['categories'] ?? '[]', true);

        // Validate required fields
        if (empty($title)) {
            return $this->response->respond(false, 'Title is required', 400);
        }
      
        // Check file upload
        if (!isset($_FILES['bookPdf']) || $_FILES['bookPdf']['error'] != 0) {
            error_log("File upload error: " . ($_FILES['bookPdf']['error'] ?? 'No file uploaded'));
            return $this->response->respond(false, 'PDF file upload error', 400);
        }

        // Initialize PdfHelper with temporary path
        $pdfHelper = new PdfHelper($_FILES['bookPdf']['tmp_name']);
        
        // Store the PDF
        $pdfPath = $pdfHelper->storePdf($_FILES['bookPdf']);
        
        if (!$pdfPath) {
            error_log("Failed to store PDF");
            return $this->response->respond(false, 'Error storing PDF', 500);
        }
        
        error_log("PDF stored successfully at: $pdfPath");
        
        // Generate thumbnail
        $thumbnailPath = $pdfHelper->getPdfThumbnail();
        
        // Add the book to the database
        $response = $this->bookService->addBook(
            $title, $author, $year, $description, $categories, 
            $pdfPath, $thumbnailPath
        );
        
        if ($response) {
            return $this->response->respond(true, $response);
        } else {
            return $this->response->respond(false, 'Error adding book', 400);
        }
    }

    /**
     * Handle secure book download
     * 
     * @param string $bookId MongoDB ID of the book to download
     */
    public function downloadBook($bookId = null) {
        // Check if user is authenticated
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (empty($_SESSION['user_id'])) {
            header('Location: /?showLogin=true&redirect=' . urlencode($_SERVER['REQUEST_URI']));
            exit;
        }
        
        // Validate book ID
        if (!$bookId || !preg_match('/^[0-9a-f]{24}$/', $bookId)) {
            header('HTTP/1.0 400 Bad Request');
            echo "Invalid book ID";
            exit;
        }
        
        // Get book details from database
        $bookService = new BookService();
        $book = $bookService->getBookDetails($bookId);
        
        if (!$book || empty($book['pdf_path'])) {
            header('HTTP/1.0 404 Not Found');
            echo "Book not found or has no PDF";
            exit;
        }
        
        // Get the absolute path to the PDF file
        $pdfPath = $_SERVER['DOCUMENT_ROOT'] . $book['pdf_path'];
        
        // Check if file exists and is readable
        if (!file_exists($pdfPath) || !is_readable($pdfPath)) {
            header('HTTP/1.0 404 Not Found');
            echo "PDF file not found or not readable";
            exit;
        }
        
        // Log the download
        error_log("User {$_SESSION['user_id']} downloaded book {$bookId}");
        
        // Get the filename for the Content-Disposition header
        $filename = basename($pdfPath);
        if (!empty($book['title'])) {
            // Create a safe filename based on the book title
            $safeTitle = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $book['title']);
            $filename = $safeTitle . '.pdf';
        }
        
        // Set appropriate headers for file download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($pdfPath));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        // Output file content and stop script execution
        readfile($pdfPath);
        exit;
    }

    /**
     * Add a new book review
     */
    public function addReview() {
        // Check authentication
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (empty($_SESSION['user_id'])) {
            ResponseHandler::respond(false, 'Authentication required', 401);
            return;
        }
        
        // Get JSON data
        $inputJSON = file_get_contents('php://input');
        $input = json_decode($inputJSON, true);
        
        // Validate input
        if (empty($input['book_id']) || !isset($input['rating']) || empty($input['comment'])) {
            ResponseHandler::respond(false, 'Missing required fields', 400);
            return;
        }
        
        // Validate rating
        $rating = intval($input['rating']);
        if ($rating < 1 || $rating > 5) {
            ResponseHandler::respond(false, 'Rating must be between 1 and 5', 400);
            return;
        }
        
        $userService = new \App\Services\UserService(); 
        $user = $userService->getUserById($_SESSION['user_id']);
      
        $review = [
            'user_id' => $_SESSION['user_id'],
            'username' => $user['username'] ?? 'Anonymous User',
            'rating' => $rating,
            'comment' => $input['comment'],
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // Save review
        $bookService = new BookService();
        $result = $bookService->addReview($input['book_id'], $review);
        
        if ($result) {
            ResponseHandler::respond(true, 'Review added successfully');
        } else {
            ResponseHandler::respond(false, 'Failed to add review', 500);
        }
    }

    /**
     * Get reviews for a book
     */
    public function getReviews($bookId) {
        if (empty($bookId)) {
            ResponseHandler::respond(false, 'Book ID is required', 400);
            return;
        }
        
        $bookService = new BookService();
        $reviews = $bookService->getBookReviews($bookId);
        if($reviews){ 
            ResponseHandler::respond(true, $reviews, 200, );
        }else { 
            ResponseHandler::respond(false, 'No reviews found', 404);
        }
    }
}
