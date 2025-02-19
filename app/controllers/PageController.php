<?php

class PageController {
    public function home() {
        include __DIR__ . '/../views/home.php';
    }

    public function loginForm() {
        include __DIR__ . '/../views/login.php';
    }

    public function signupForm() {
        include __DIR__ . '/../views/signup.php';
    }

    public function listBooks() {
        include __DIR__ . '/../views/list_books.php';
    }

    public function viewBook($id) {
        include __DIR__ . '/../views/view_book.php';
    }

    public function addBookForm() {
        include __DIR__ . '/../views/add_book.php';
    }

    public function updateBook($id) {
        include __DIR__ . '/../views/update_book.php';
    }

    public function searchBooks() {
        $query = $_GET['q'] ?? '';
        $bookService = new BookService();
        $books = $bookService->searchBooks($query);
        include(__DIR__ . '/../views/search_results.php');
    }

    public static function error() {
        include __DIR__ . '/../views/404.php';
    }
}
