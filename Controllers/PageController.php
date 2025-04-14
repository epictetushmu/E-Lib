<?php
namespace  Controllers;

use  Includes\Controller;
use  Services\BookService; 

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


    public function viewBook() {
        $id = $_GET['q'] ?? '';      
        $bookService = new BookService();
        $book = $bookService->getBookDetails($id);
        if ($book) {
            $this->render('book_detail', ['book' => $book]);
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
        $this->render('search_results', ['books' => $books]);

    }

    public function profile(){
        $this->render('profile');
    }

    public function error() {
        $this->render('error'); 
    }
}
