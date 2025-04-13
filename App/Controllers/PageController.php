<?php
namespace App\Controllers;

use App\Includes\Controller;
use App\Services\BookService; 

class PageController extends Controller{
    public function home() {
       $this->render('home');
    }

    public function loginForm() {
        $this->render('login');
    }

    public function signupForm() {
        $this->render('signup');
    }

    public function listBooks() {
        $this->render('view_books');
    }

    public function viewBook() {
        $id = $_GET['q'] ?? '';      
        $bookService = new BookService();
        $book = $bookService->getBookDetails($id);
        if ($book) {
            $this->render('view_book', ['book' => $book]);
        } else {
            $this->error();
        }
    }

    public function addBookForm() {
        $this->render('add_book');
    }


    public function searchBooks() {
        $query = $_GET['q'] ?? '';
        $bookService = new BookService();
        $books = $bookService->searchBooks($query);
        $this->render('view_book', ['books' => $books]);

    }

    public function error() {
        $this->render('error'); 
    }
}
