<?php

class ViewsController{ 
    public function home() {
        include('../views/home.php');
    }

    public function loginForm() {
        include('../views/login.php');
    }

    public function signupForm() {
        include('../views/signup.php');
    }

    public function listBooks() {
        include('../views/book_list.php');
    }

    public function viewBooks() {
        include('../views/book_detail.php');
    }

    public function addBookForm() {
        include('../views/add_book.php');
    }

    public function updateBook() {
        include('../views/update_book.php');
    }

    public function searchBooks() {
        include('../views/search_results.php');
    }

    
}