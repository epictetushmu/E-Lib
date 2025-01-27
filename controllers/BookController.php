<?php
require_once('../services/BookService.php');
require_once('../controllers/Controller.php');

class BookController extends Controller {
    private $bookService;

    public function __construct() {
        $this->bookService = new BookService();
    }

    public function listBooks() {
        $books = $this->bookService->getAllBooks();
        $this->render('book_list', ['books' => $books]);
    }

    public function viewBook($id) {
        $book = $this->bookService->getBookById($id);
        if (!$book) {
            echo "Book not found!";
            return;
        }
        $this->render('book_detail', ['book' => $book]);
    }

    public function addBookForm() {
        $this->render('add_book');
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
            $this->redirect('/');
        } else {
            echo "Failed to add book.";
        }
    }
}
