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
        $data = $_POST;
        $title = $data['title'];
        $author = $data['author'];
        $year = $data['year'];
        $condition = $data['condition'];
        $description = $data['description'];
        $bookPdf = $_FILES['bookPdf']; 
        $categories = json_decode($data['categories'], true); 

        // Validate required fields
        if (empty($title)) {
            return $this->response->respond(false, 'Title is required', 400);
        }
        // Validate file upload
        $fileUploadPath = "public/assets/";
        if (isset($bookPdf) && $bookPdf['error'] == 0) {
            $pdfHelper = new PdfHelper($bookPdf['tmp_name']);
            $thumbnailDir = 'thumbnails/';
            $thumbnailPath = $thumbnailDir . basename($bookPdf['name'], '.pdf') . '.jpg';
            if (!$pdfHelper->extractFirstPageAsImage($thumbnailPath, $fileUploadPath)) {
                return $this->response->respond(false, 'Error creating thumbnail', 500);
            }
        } else {
            return $this->response->respond(false, 'Error uploading PDF', 400);
        }

        $pdfHelper = new PdfHelper($bookPdf['tmp_name']);
        $pdfPath = $pdfHelper->storePdf($bookPdf);
        if (!$pdfPath) {
            return $this->response->respond(false, 'Error storing PDF', 500);
        }
    
        $response = $this->bookService->addBook($title, $author, $year,  $description, $categories, $pdfPath, $thumbnailPath);
        if ($response) {
            return $this->response->respond(true, $response);
        } else {
            return $this->response->respond(false, 'Error adding book', 400);
    
        }
    }
}
