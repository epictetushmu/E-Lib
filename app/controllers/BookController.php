<?php
require_once("../includes/Controller.php"); 
require_once('../services/BookService.php');
require_once('../services/CategoriesService.php');
require_once('../includes/ResponseHandler.php');

class BookController extends Controller {
    private $bookService;

    private $categoriesService;
    private $respond; 
    public function __construct() {
        $this->bookService = new BookService();
        $this->respond = new ResponseHandler();
        $this->categoriesService = new CategoriesService();
    }   

    public function listBooks() {
        $books = $this->bookService->getAllBooks();
        if ($books) { 
            $this->respond->respond( 200, $books);
        }
        $this->respond->respond(404,'No books found');
    }

    public function viewBook($id) {
        $book = $this->bookService->getBookDetails($id);
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
    
    public function addBook() {
        $data = $_POST;
        $title = $data['title'];
        $author = $data['author'];
        $year = $data['year'];
        $condition = $data['condition'];
        $copies = $data['copies'];
        $description = $data['description'];
        $book = $_FILES['cover']['name'];
        $categories = $data['category']; 

        move_uploaded_file($_FILES['book']['bookPdf'], '../uploads/' . $book);

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

        $response = $this->bookService->addBook($title, $author, $year, $condition, $copies, $description, $cover, $categoryIds);
        $this->respond->respond(200,$response);
    }
}
