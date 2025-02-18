<?php

class PageController {
    public function home() {
        include('../views/home.php');
    }

    public function addBook() {
        include('../views/add_book.php');
    }

    public function bookDetail(){ 
        include('../views/book_detail.php');
    }

    public function login() {
        include('../views/login.php');
    }

    public function register() {
        include('../views/register.php');
    }

    public static function error(){
        include('../views/404.php');
    }
}
