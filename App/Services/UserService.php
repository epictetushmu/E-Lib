<?php

namespace App\Services;

use App\Models\Users;
use App\Helpers\Database\MongoHelper;

class UserService
{
    private Users $user;

    public function __construct()
    {
        $this->user = new Users();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getUserByEmail(string $email): ?array
    {
        return $this->user->getUserByEmail($email);
    }

    /**
     * @return array<string, mixed>
     */
    public function registerUser(string $userName, string $email, string $password): array
    {
        $user = [
            'username' => $userName,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'isAdmin' => false,
            'createdAt' => new \MongoDB\BSON\UTCDateTime()
        ];
        return $this->user->registerUser($user);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getUserById(string $id): ?array
    {
        return $this->user->getUserById($id);
    }

    /**
     * @return array<string, mixed>|bool
     */
    public function saveBook(string $userId, string $bookId): array|bool
    {
        return $this->user->saveBook($userId, $bookId);
    }

    /**
     * @return list<string>
     */
    public function getSavedBooks(string $userId): array
    {
        $user = $this->getUserById($userId);
        if (empty($user['savedBooks'])) {
            return [];
        }
        $raw = $user['savedBooks'];
        return MongoHelper::toArray($raw);
    }

    /**
     * @return list<string>
     */
    public function getDownloadedBookIds(string $userId): array
    {
        $user = $this->getUserById($userId);
        if (empty($user['downloadedBooks'])) {
            return [];
        }
        $raw = $user['downloadedBooks'];
        return MongoHelper::toArray($raw);
    }

    public function recordDownload(string $userId, string $bookId): bool
    {
        $result = $this->user->recordDownload($userId, $bookId);

        return $result !== false;
    }

    /**
     * @return array<string, mixed>|bool
     */
    public function removeBook(string $userId, string $bookId): array|bool
    {
        return $this->user->removeBook($userId, $bookId);
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
        return $this->user->updateUser($userId, $updates);
    }
}
