<?php
namespace App\Controllers;

use App\Services\BookService; 
use App\Services\CategoriesService;
use App\Includes\ResponseHandler;
use App\Helpers\PdfHelper;


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
        $title = $data['title'] ?? '';
        $author = $data['author'] ?? '';
        $year = $data['year'] ?? '';
        $condition = $data['condition'] ?? '';
        $copies = $data['copies'] ?? '';
        $description = $data['description'] ?? '';
        $categories = isset($data['categories']) ? json_decode($data['categories'], true) : [];
        
        // Check if file was uploaded
        if (!isset($_FILES['bookPdf']) || $_FILES['bookPdf']['error'] != 0) {
            return $this->response->respond(false, 'PDF file is required', 400);
        }
        
        $bookPdf = $_FILES['bookPdf'];
        
        // Validate required fields
        if (empty($title) || empty($author) || empty($year) || empty($condition) || empty($copies) || empty($description)) {
            return $this->response->respond(false, 'All fields are required', 400);
        }
        
        // Check file type
        $fileInfo = pathinfo($bookPdf['name']);
        $extension = strtolower($fileInfo['extension'] ?? '');
        if ($extension !== 'pdf') {
            return $this->response->respond(false, 'Only PDF files are accepted', 400);
        }
        
        try {
            // Create uploads directory if it doesn't exist
            $uploadsDir = 'uploads/books/';
            if (!is_dir($uploadsDir)) {
                mkdir($uploadsDir, 0755, true);
            }
            
            // Create thumbnails directory if it doesn't exist
            $thumbnailDir = 'uploads/thumbnails/';
            if (!is_dir($thumbnailDir)) {
                mkdir($thumbnailDir, 0755, true);
            }
            
            // Generate unique filename for the PDF
            $pdfFilename = uniqid() . '_' . basename($bookPdf['name']);
            $pdfPath = $uploadsDir . $pdfFilename;
            
            // Move the uploaded file
            if (!move_uploaded_file($bookPdf['tmp_name'], $pdfPath)) {
                return $this->response->respond(false, 'Error storing PDF file', 500);
            }
            
            // Generate thumbnail from first page
            $thumbnailPath = $thumbnailDir . pathinfo($pdfFilename, PATHINFO_FILENAME) . '.jpg';
            $pdfHelper = new PdfHelper();
            if (!$pdfHelper->extractFirstPageAsImage($pdfPath, $thumbnailPath)) {
                // Continue even if thumbnail creation fails
                error_log("Failed to create thumbnail for PDF: $pdfPath");
            }
            
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
            
            // Add book with PDF path and thumbnail path
            $bookData = [
                'title' => $title,
                'author' => $author,
                'year' => $year,
                'condition' => $condition,
                'copies' => $copies,
                'description' => $description,
                'categories' => $categories,
                'pdf_path' => $pdfPath,
                'thumbnail_path' => $thumbnailPath
            ];
            
            $response = $this->bookService->addBook($title, $author, $year, $condition, $copies, $description, $categories, $pdfPath, $thumbnailPath);
            if ($response) {
                return $this->response->respond(true, $response);
            } else {
                return $this->response->respond(false, 'Error adding book', 400);
            }
            
        } catch (\Exception $e) {
            return $this->response->respond(false, 'Error: ' . $e->getMessage(), 500);
        }
    }
}
