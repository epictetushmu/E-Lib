<?php
require_once('../services/BookService.php');
require_once('../controllers/Controller.php');
require_once('../utils/ResponseHandler.php');

class BookController extends Controller {
    private $bookService;
    private $respond; 
    public function __construct() {
        $this->bookService = new BookService();
        $this->respond = new ResponseHandler();
    }

    public function listBooks() {
        $books = $this->bookService->getAllBooks();
        if ($books) { 
            $this->respond->respond( 200, $books);
        }
        $this->respond->respond(404,'No books found');
    }

    public function viewBook($id) {
        $book = $this->bookService->getBookById($id);
        if (!$book) {
            echo "Book not found!";
            return;
        }
       $this->respond->respond( 200, $book );
    }

    public function searchBooks($search) {
        $books = $this->bookService->searchBooks($search);
        if ($books) {
            $this->respond->respond( 200, $books);
        }
        $this->respond->respond(404,'No books found');
    }

    public function updateBook($id, $data) {
        $title = $data['title'];
        $author = $data['author'];
        $description = $data['description'];
        $year = $data['year'];
        $condition = $data['condition'];
        $copies = $data['copies'];
        $description = $data['description']; 
        $category = $data['category'];
        
        $success = $this->bookService->updateBook($id, $title, $author,$year , $condition, $copies,  $description, $category);

        if ($success) {
            $this->respond->respond(200,'Book updated successfully');
        } else {
            echo "Failed to update book.";
        }
    }


    public function addBook() {
        $title = $_POST['title'];
        $author = $_POST['author'];
        $description = $_POST['description'];
        $year = $_POST['year'];
        $condition = $_POST['condition'];
        $copies = $_POST['copies'];
        $description = $_POST['description']; 
        $category = $_POST['category'];
        
        $success = $this->bookService->addBook($title, $author,$year , $condition, $copies,  $description, $category);

        if ($success) {
            $this->respond->respond(200,'Book added successfully');
        } else {
            echo "Failed to add book.";
        }
    }

    
}
