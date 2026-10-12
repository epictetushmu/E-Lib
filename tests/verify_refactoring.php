<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Books;
use App\Models\Users;
use App\Helpers\Database\MongoHelper;

// Mock Database Connection for testing if possible, or use actual DB if environment allows.
// Since we don't have an easy mock setup, we'll assume the environment is set for local dev DB.
// NOTE: This script interacts with the database. using a test collection would be safer but
// for now we will try to insert and immediately delete.

echo "Verifying Models Refactoring...\n";

try {
    $booksModel = new Books();
    $usersModel = new Users();

    echo "1. Testing Books::create (via addBook)\n";
    $newBook = [
        'title' => 'Refactoring Test Book ' . uniqid(),
        'author' => 'Test Author',
        'year' => 2023,
        'status' => 'private'
    ];
    $result = $booksModel->addBook($newBook);
    
    if (empty($result['insertedId'])) {
        throw new Exception("Failed to add book");
    }
    $bookId = (string)$result['insertedId'];
    echo "   - Book added with ID: $bookId\n";

    echo "2. Testing Books::findById (via getBookDetails)\n";
    $book = $booksModel->getBookDetails($bookId);
    if (!$book || $book['title'] !== $newBook['title']) {
        throw new Exception("Failed to retrieve book details");
    }
    echo "   - Book retrieved successfully\n";

    echo "3. Testing Books::updateById (via updateBook)\n";
    $updateData = ['author' => 'Updated Author'];
    $booksModel->updateBook($bookId, $updateData);
    $updatedBook = $booksModel->getBookDetails($bookId);
    if ($updatedBook['author'] !== 'Updated Author') {
        throw new Exception("Failed to update book");
    }
    echo "   - Book updated successfully\n";

    echo "4. Testing Books::deleteById (via deleteBook)\n";
    $booksModel->deleteBook($bookId);
    $deletedBook = $booksModel->getBookDetails($bookId);
    if ($deletedBook) {
        throw new Exception("Failed to delete book");
    }
    echo "   - Book deleted successfully\n";

    // --- Users verification ---

    echo "5. Testing Users::create (via registerUser)\n";
    $email = 'test_' . uniqid() . '@example.com';
    $newUser = [
        'name' => 'Test User',
        'email' => $email,
        'password' => password_hash('password123', PASSWORD_DEFAULT),
        'role' => 'user'
    ];
    $userResult = $usersModel->registerUser($newUser);
    if (empty($userResult['insertedId'])) {
        throw new Exception("Failed to register user");
    }
    $userId = (string)$userResult['insertedId'];
    echo "   - User registered with ID: $userId\n";

    echo "6. Testing Users::findById (via getUserById)\n";
    $user = $usersModel->getUserById($userId);
    if (!$user || $user['email'] !== $email) {
        throw new Exception("Failed to retrieve user");
    }
    echo "   - User retrieved successfully\n";

    echo "7. Testing Users::updateById (via updateUser)\n";
    $usersModel->updateUser($userId, ['name' => 'Updated User Name']);
    $updatedUser = $usersModel->getUserById($userId);
    if ($updatedUser['name'] !== 'Updated User Name') {
        throw new Exception("Failed to update user profile");
    }
    echo "   - User profile updated successfully\n";
    
    // Cleanup User
    // Users model doesn't have a delete method exposed directly in the interface?
    // Let's use the inherited deleteById if it was public, but BaseModel methods are public.
    echo "8. Testing BaseModel::deleteById on User\n";
    $usersModel->deleteById($userId);
    $deletedUser = $usersModel->getUserById($userId);
    if ($deletedUser) {
        throw new Exception("Failed to delete user");
    }
    echo "   - User deleted successfully\n";

    echo "\nALL TESTS PASSED!\n";

} catch (Exception $e) {
    echo "\nTEST FAILED: " . $e->getMessage() . "\n";
    exit(1);
}
