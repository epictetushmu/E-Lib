<?php
namespace App\Models;

use App\Includes\Database;
use App\Models\Categories;

class Book {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance();
    }

    public function getAllBooks() {
        $sql = 'SELECT * FROM Book';
        return $this->pdo->execQuery($sql);
    }

    public function getBookDetails($id) {
        $sql = "SELECT * FROM Book WHERE id = :id";
        return $this->pdo->execQuery($sql, array("id" => $id));
    }

    public function getFeaturedBooks() {
        $sql = "SELECT b.*, c.name as genre FROM Book b 
                LEFT JOIN book_categories bc ON b.id = bc.book_id 
                LEFT JOIN categories c ON bc.category_id = c.id 
                GROUP BY b.id 
                ORDER BY b.id DESC LIMIT 12";
        return $this->pdo->execQuery($sql);
    }

    public function addBook($title, $author, $year, $condition, $copies, $description, $categories) {
        $sql = "INSERT INTO Book (title, author, publication_year, `condition`, number_of_copies, `description`) VALUES (:title, :author, :year, :condition, :copies, :description)";
        $book = [
            "title" => $title,
            "author" => $author,
            "year" => $year,
            "condition" => $condition,
            "copies" => $copies,
            "description" => $description,
        ];
        $bookId = $this->pdo->execQuery($sql, $book, true); 

        $category = new Categories();
        foreach ($categories as $category_id) {
            $categoryId = $category->addCategory($category_id);
            $sql = "INSERT INTO book_categories (book_id, category_id) VALUES (:book_id, :category_id)"; 
            $this->pdo->execQuery($sql, ["book_id" => $bookId, "category_id" => $categoryId]);
        }
        return $bookId;
    }

    public function searchBooks($filters = [], $offset = 0, $limit = 12) {
        $conditions = [];
        $params = [];
        
        // Build search conditions
        if (!empty($filters['q'])) {
            $conditions[] = "(b.title LIKE :query OR b.author LIKE :query OR b.description LIKE :query)";
            $params['query'] = '%' . $filters['q'] . '%';
        }
        
        if (!empty($filters['title'])) {
            $conditions[] = "b.title LIKE :title";
            $params['title'] = '%' . $filters['title'] . '%';
        }
        
        if (!empty($filters['author'])) {
            $conditions[] = "b.author LIKE :author";
            $params['author'] = '%' . $filters['author'] . '%';
        }
        
        if (!empty($filters['category'])) {
            $conditions[] = "c.id = :category_id";
            $params['category_id'] = $filters['category'];
        }
        
        // Build the WHERE clause
        $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
        
        // Build the complete SQL query
        $sql = "SELECT b.*, c.name as genre FROM Book b 
                LEFT JOIN book_categories bc ON b.id = bc.book_id 
                LEFT JOIN categories c ON bc.category_id = c.id 
                $whereClause
                GROUP BY b.id 
                ORDER BY b.title ASC 
                LIMIT :offset, :limit";
        
        // Add pagination parameters
        $params['offset'] = $offset;
        $params['limit'] = $limit;
        
        return $this->pdo->execQuery($sql, $params);
    }
    
    public function countSearchResults($filters = []) {
        $conditions = [];
        $params = [];
        
        // Build search conditions (same as searchBooks method)
        if (!empty($filters['q'])) {
            $conditions[] = "(b.title LIKE :query OR b.author LIKE :query OR b.description LIKE :query)";
            $params['query'] = '%' . $filters['q'] . '%';
        }
        
        if (!empty($filters['title'])) {
            $conditions[] = "b.title LIKE :title";
            $params['title'] = '%' . $filters['title'] . '%';
        }
        
        if (!empty($filters['author'])) {
            $conditions[] = "b.author LIKE :author";
            $params['author'] = '%' . $filters['author'] . '%';
        }
        
        if (!empty($filters['category'])) {
            $conditions[] = "c.id = :category_id";
            $params['category_id'] = $filters['category'];
        }
        
        // Build the WHERE clause
        $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
        
        $sql = "SELECT COUNT(DISTINCT b.id) as total FROM Book b 
                LEFT JOIN book_categories bc ON b.id = bc.book_id 
                LEFT JOIN categories c ON bc.category_id = c.id 
                $whereClause";
        
        $result = $this->pdo->execQuery($sql, $params);
        return isset($result[0]['total']) ? (int)$result[0]['total'] : 0;
    }
    
    public function getBookById($id) {
        $sql = "SELECT b.*, c.name as genre FROM Book b 
                LEFT JOIN book_categories bc ON b.id = bc.book_id 
                LEFT JOIN categories c ON bc.category_id = c.id 
                WHERE b.id = :id";
        
        $result = $this->pdo->execQuery($sql, ['id' => $id]);
        return !empty($result) ? $result[0] : null;
    }
    
    public function getBookReviews($bookId) {
        $sql = "SELECT r.*, u.username FROM reviews r
                JOIN users u ON r.user_id = u.id
                WHERE r.book_id = :book_id
                ORDER BY r.created_at DESC";
        
        return $this->pdo->execQuery($sql, ['book_id' => $bookId]);
    }
    
    public function addReview($bookId, $userId, $rating, $comment) {
        $sql = "INSERT INTO reviews (book_id, user_id, rating, comment) 
                VALUES (:book_id, :user_id, :rating, :comment)";
        
        $params = [
            'book_id' => $bookId,
            'user_id' => $userId,
            'rating' => $rating,
            'comment' => $comment
        ];
        
        return $this->pdo->execQuery($sql, $params, true);
    }
    
    public function borrowBook($bookId, $userId) {
        // First, check if the book is available
        $book = $this->getBookById($bookId);
        
        if (!$book) {
            return "Book not found";
        }
        
        if ($book['number_of_copies'] <= 0) {
            return "No copies available for borrowing";
        }
        
        // Check if user already borrowed this book
        $sql = "SELECT * FROM borrowed_books WHERE book_id = :book_id AND user_id = :user_id AND return_date IS NULL";
        $existing = $this->pdo->execQuery($sql, ['book_id' => $bookId, 'user_id' => $userId]);
        
        if (!empty($existing)) {
            return "You have already borrowed this book";
        }
        
        // Start transaction
        $this->pdo->beginTransaction();
        
        try {
            // Decrease available copies
            $sql = "UPDATE Book SET number_of_copies = number_of_copies - 1 WHERE id = :id AND number_of_copies > 0";
            $updated = $this->pdo->execQuery($sql, ['id' => $bookId]);
            
            if (!$updated) {
                $this->pdo->rollBack();
                return "Failed to update book availability";
            }
            
            // Add borrow record
            $sql = "INSERT INTO borrowed_books (book_id, user_id, borrow_date, due_date) 
                    VALUES (:book_id, :user_id, NOW(), DATE_ADD(NOW(), INTERVAL 14 DAY))";
            
            $inserted = $this->pdo->execQuery($sql, [
                'book_id' => $bookId,
                'user_id' => $userId
            ], true);
            
            if (!$inserted) {
                $this->pdo->rollBack();
                return "Failed to record borrowing";
            }
            
            $this->pdo->commit();
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return "An error occurred: " . $e->getMessage();
        }
    }
    
    public function saveToList($bookId, $userId) {
        // Check if already saved
        $sql = "SELECT * FROM saved_books WHERE book_id = :book_id AND user_id = :user_id";
        $existing = $this->pdo->execQuery($sql, ['book_id' => $bookId, 'user_id' => $userId]);
        
        if (!empty($existing)) {
            return "Book already in your saved list";
        }
        
        // Save book
        $sql = "INSERT INTO saved_books (book_id, user_id, saved_date) 
                VALUES (:book_id, :user_id, NOW())";
        
        $result = $this->pdo->execQuery($sql, [
            'book_id' => $bookId,
            'user_id' => $userId
        ], true);
        
        return $result ? true : "Failed to save book";
    }
}
