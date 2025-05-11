<?php
namespace App\Controllers;

use App\Includes\ResponseHandler; 
use App\Includes\SessionManager;
use Exception;

class PageController {
    private $response;   
    
    public function __construct() {
        // Initialize any services or dependencies here
        $this->response = new ResponseHandler();
    }

    public function home() {
        // Only pass minimal data, client will fetch what it needs via API
        $this->response->renderView(__DIR__ . '/../Views/home.php', [
           'isLoggedIn' => SessionManager::getCurrentUserId() ? true : false
        ]);
    }

    public function dashboard() {
        // Admin dashboard - client will fetch data via API
        $this->response->renderView(__DIR__ . '/../Views/admin.php');
    }

    /**
     * Book detail page - data will be fetched client-side via API
     */
    public function viewBook($path = null, $id = null) {
        try {
            // Check if ID is valid but don't fetch the book data here
            if (is_null($id) || !preg_match('/^[0-9a-f]{24}$/', $id)) {
                $this->error();
                return;
            }
            // Just render the page shell, client will fetch the data
            $this->response->renderView(__DIR__ . '/../Views/book_detail.php', ['bookId' => $id]);
            
        } catch(Exception $e) {
            // Log the error but don't echo it (causes header issues)
            error_log("Book View Error: " . $e->getMessage());
            $this->error();
        }
    }

    public function addBookForm() {
        $this->response->renderView(__DIR__ . '/../Views/add_book.php');
    }

    public function readBook($path = null, $id = null) {
        try {
            if (is_null($id) || !preg_match('/^[0-9a-f]{24}$/', $id)) {
                $this->error();
                return;
            }
            
            // Pass only the bookId, client will fetch the content
            $this->response->renderView(__DIR__ . '/../Views/read_book.php', ['bookId' => $id]);
            
        } catch(Exception $e) {
            error_log("Read Book Error: " . $e->getMessage());
            $this->error();
        }
    }

    public function searchBooks() {
        // Just render the search results page, client will fetch results
        $this->response->renderView(__DIR__ . '/../Views/search_results.php');
    }

    public function docs() {
        $this->response->renderView(__DIR__ . '/../Views/docs.php');
    }

    public function profile() {
        // Check if the user is logged in
        if (!SessionManager::isLoggedIn()) {
            // Redirect to home with parameter to show login popup
            header('Location: /?showLogin=1&redirect=' + urlencode($_SERVER['REQUEST_URI']));
            exit;
        }
        
        // Just render the profile page, client will fetch user data
        $this->response->renderView(__DIR__ . '/../Views/profile.php');
    }

    public function error() {
        $this->response->renderView(__DIR__ . '/../Views/error.php', [], 404);
    }

    public function viewBooks() {
        // Just render the view books page, client will fetch book data
        $this->response->renderView(__DIR__ . '/../Views/view_books.php');
    }   
    
    /**
     * View system logs (admin-only page)
     */
    public function viewLogs() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check if user is authenticated
        if (!SessionManager::isLoggedIn()) {
            // Redirect to home page with parameter to show login popup
            header('Location: /?showLogin=1&redirect=' . urlencode('/admin/logs'));
            exit;
        }
        
        // Check if user is admin
        if (empty($_SESSION['isAdmin']) || $_SESSION['isAdmin'] !== true) {
            // Redirect to home page with unauthorized message
            header('Location: /?error=' . urlencode('You do not have permission to access this page.'));
            exit;
        }
        
        // Just render the logs view, client will fetch log data
        $this->response->renderView(__DIR__ . '/../Views/logs.php');
    }
}
