<?php

namespace App\Models;

use App\Helpers\Database\MongoHelper;
use InvalidArgumentException;

/**
 * Users model handles all user-related data operations
 */
class Users extends BaseModel
{
    protected string $collection = 'Users';

    /**
     * Required fields for user registration
     */
    private const REQUIRED_FIELDS = ['email', 'password', 'username'];

    /**
     * Get a user by their email address
     *
     * @param string $email User's email
     * @return array<string, mixed>|null User data or null if not found
     * @throws InvalidArgumentException If email is invalid
     */
    public function getUserByEmail(string $email)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email format');
        }

        return $this->db->findOne($this->collection, ['email' => $email]);
    }

    /**
     * Register a new user
     *
     * @param array<string, mixed> $user User data
     * @return array<string, mixed> Insert operation result
     * @throws InvalidArgumentException If validation fails
     */
    public function registerUser(array $user): array
    {
        return $this->create($user);
    }

    /**
     * Get a user by their ID
     *
     * @param string $id User ID
     * @return array<string, mixed>|null User data or null if not found
     */
    public function getUserById(string $id)
    {
        try {
            return $this->findById($id);
        } catch (\Exception $e) {
            // Handle case where ID isn't a valid ObjectId
            error_log("Invalid user ID format: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Validate user data
     *
     * @param array<string, mixed> $data User data to validate
     * @return array<string, string> Validation errors
     */
    public function validate(array $data): array
    {
        $errors = [];

        // Check required fields
        foreach (self::REQUIRED_FIELDS as $field) {
            if (empty($data[$field])) {
                $errors[$field] = ucfirst($field) . ' is required';
            }
        }

        // Email validation
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        }

        // Check if email already exists when registering
        if (!empty($data['email']) && empty($errors['email'])) {
            $existingUser = $this->getUserByEmail($data['email']);
            if ($existingUser && (!isset($data['_id']) || $existingUser['_id'] != $data['_id'])) {
                $errors['email'] = 'Email already in use';
            }
        }

        // Password strength validation
        if (!empty($data['password']) && strlen($data['password']) < 8) {
            $errors['password'] = 'Password must be at least 8 characters';
        }

        return $errors;
    }

    /**
     * Save a book to user's saved books
     *
     * @param string $userId User ID
     * @param string $bookId Book ID
     * @return array<string, mixed>|bool Update result or false if user not found or book already saved
     */
    public function saveBook(string $userId, string $bookId)
    {
        $user = $this->getUserById($userId);
        if (!$user) {
            return false;
        }

        // Convert MongoDB BSONArray to PHP array if needed
        $savedBooksOriginal = $user['savedBooks'] ?? [];
        $savedBooks = MongoHelper::toArray($savedBooksOriginal);

        if (!in_array($bookId, $savedBooks)) {
            $savedBooks[] = $bookId;
            try {
                return $this->updateById($userId, ['savedBooks' => $savedBooks]);
            } catch (\Exception $e) {
                error_log("Error saving book: " . $e->getMessage());
                return false;
            }
        }

        return true; // Book already saved
    }

    /**
     * Remove a book from user's saved books
     *
     * @param string $userId User ID
     * @param string $bookId Book ID
     * @return array<string, mixed>|bool Update result or false if user not found
     */
    public function removeBook(string $userId, string $bookId)
    {
        $user = $this->getUserById($userId);
        if (!$user) {
            return false;
        }

        // Convert MongoDB BSONArray to PHP array if needed
        $savedBooksOriginal = $user['savedBooks'] ?? [];
        $savedBooks = MongoHelper::toArray($savedBooksOriginal);

        if (in_array($bookId, $savedBooks)) {
            $savedBooks = array_diff($savedBooks, [$bookId]);
            try {
                return $this->updateById($userId, ['savedBooks' => $savedBooks]);
            } catch (\Exception $e) {
                error_log("Error removing book: " . $e->getMessage());
                return false;
            }
        }

        return true; // Book wasn't saved
    }

    /**
     * Record a successful book download for a user (unique book ids, newest last).
     *
     * @return array<string, mixed>|bool
     */
    public function recordDownload(string $userId, string $bookId): array|bool
    {
        $user = $this->getUserById($userId);
        if (!$user) {
            return false;
        }

        $original = $user['downloadedBooks'] ?? [];
        $list = MongoHelper::toArray($original);

        $ids = $list;
        $bid = (string) $bookId;
        if (!in_array($bid, $ids, true)) {
            $list[] = $bid;
            try {
                return $this->updateById($userId, ['downloadedBooks' => array_values($list)]);
            } catch (\Exception $e) {
                error_log('Error recording download: ' . $e->getMessage());
                return false;
            }
        }

        return true;
    }

    /**
     * Update user profile information
     *
     * @param string $userId The ID of the user to update
     * @param array<string, mixed> $updates Associative array of fields to update
     * @return bool True on success, false on failure
     */
    public function updateUser($userId, array $updates)
    {
        try {
            // Make sure the user exists
            $user = $this->getUserById($userId);
            if (!$user) {
                return false;
            }

            // Update the user document with the provided fields
            $result = $this->updateById($userId, $updates);

            return $result !== false;
        } catch (\Exception $e) {
            error_log('Error updating user: ' . $e->getMessage());
            return false;
        }
    }
}
