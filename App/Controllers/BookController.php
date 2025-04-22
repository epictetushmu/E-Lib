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
}
