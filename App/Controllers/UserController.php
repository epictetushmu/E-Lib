<?php

namespace App\Controllers;

use App\Includes\Environment;
use App\Services\UserService;
use App\Services\BookService;
use App\Services\EmailService;
use App\Services\NotificationService;
use MongoDB\BSON\UTCDateTime;
use App\Includes\ResponseHandler;
use App\Includes\JwtHelper;
use App\Includes\AuthenticatedUser;
use App\Helpers\BookDisplayHelper;

class UserController
{
    private UserService $userService;
    private BookService $bookService;
    private EmailService $emailService;

    public function __construct()
    {
        $this->userService = new UserService();
        $this->bookService = new BookService();
        $this->emailService = new EmailService();
    }

    /**
     * User id from PHP session or Bearer JWT (SPA login often has token without session cookie).
     */
    private function getAuthenticatedUserId(): ?string
    {
        return AuthenticatedUser::id();
    }

    /**
     * Request body as an array, from $_POST or a JSON body.
     *
     * @return array<string, mixed>
     */
    private function getRequestData(): array
    {
        if (!empty($_POST)) {
            return $_POST;
        }
        $input = json_decode(file_get_contents('php://input'), true);
        return is_array($input) ? $input : [];
    }

    /**
     * GET /api/v1/user/notifications — the caller's loan notices, newest first.
     */
    public function getNotifications(): void
    {
        $userId = $this->getAuthenticatedUserId();
        if ($userId === null) {
            ResponseHandler::respond(false, 'User not authenticated', 401);
            return;
        }
        ResponseHandler::respond(true, array_map(fn ($n) => [
            'id' => (string) $n['_id'],
            'book_id' => $n['book_id'] ?? null,
            'message' => $n['message'] ?? '',
            'created_at' => ($n['created_at'] ?? null) instanceof UTCDateTime ? $n['created_at']->toDateTime()->format(DATE_ATOM) : null,
            'read' => ($n['read_at'] ?? null) !== null,
        ], (new NotificationService())->forUser($userId)));
    }

    /**
     * POST /api/v1/user/notifications/read — mark them all read.
     */
    public function markNotificationsRead(): void
    {
        $userId = $this->getAuthenticatedUserId();
        if ($userId === null) {
            ResponseHandler::respond(false, 'User not authenticated', 401);
            return;
        }
        (new NotificationService())->markAllRead($userId);
        ResponseHandler::respond(true, 'OK');
    }

    public function handleLogin(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $data = $this->getRequestData();
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        try {
            $user = $this->userService->getUserByEmail($email);
        } catch (\InvalidArgumentException $e) {
            ResponseHandler::respond(false, $e->getMessage(), 400);
            return;
        } catch (\Throwable $e) {
            error_log('Login database error: ' . $e->getMessage());
            ResponseHandler::respond(
                false,
                'Cannot reach the database. Check your network, MongoDB Atlas IP access list, and MONGO_URI / credentials.',
                503
            );
            return;
        }

        if ($user && password_verify($password, $user['password'])) {
            $payload = [
                'user_id' => (string) $user['_id'],
                'email' => $user['email'],
                'isAdmin' => $user['isAdmin'] ?? false,
            ];
            $token = JwtHelper::generateToken($payload);

            $_SESSION['user_id'] = $user['_id'];
            $_SESSION['token'] = $token;
            $_SESSION['username'] = $user['username'];
            $_SESSION['isAdmin'] = $user['isAdmin'] ?? false;

            ResponseHandler::respond(true, [
                'token' => $token,
                'user' => [
                    'id' => $user['_id'],
                    'email' => $user['email'],
                    'username' => $user['username'],
                    'isAdmin' => $user['isAdmin'] ?? false
                ]
            ], 200);
        } else {
            ResponseHandler::respond(false, 'Invalid credentials', 401);
        }
    }

    public function handleLogout(): void
    {
        // Logout logic here...
        if (!isset($_SESSION['user_id'])) {
            ResponseHandler::respond(false, 'No user logged in', 401);
            return;
        }
        $_SESSION = [];
        session_destroy();
        ResponseHandler::respond(true, 'Logout successful');
    }

    public function handleSignup(): void
    {
        $data = $this->getRequestData();
        if (empty($data)) {
            ResponseHandler::respond(false, 'No data received', 400);
            return;
        }
        // SignUpForm uses name="name" for the username field.
        $userName = $data['username'] ?? $data['name'] ?? null;
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        // Continue with your validation
        if (empty($userName) || empty($email) || empty($password)) {
            ResponseHandler::respond(false, 'All fields are required', 400);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            ResponseHandler::respond(false, 'Invalid email format', 400);
            return;
        }

        $existingUser = $this->userService->getUserByEmail($email);
        if ($existingUser) {
            ResponseHandler::respond(false, 'Email already exists', 400);
            return;
        }
        try {
            $result = $this->userService->registerUser($userName, $email, $password);
        } catch (\InvalidArgumentException $e) {
            $decoded = json_decode($e->getMessage(), true);
            $msg = is_array($decoded)
                ? implode(' ', $decoded)
                : $e->getMessage();
            ResponseHandler::respond(false, $msg, 400);
            return;
        }
        if ($result) {
            ResponseHandler::respond(true, 'User created successfully', 200);
        } else {
            ResponseHandler::respond(false, 'User creation failed', 400);
        }
    }

    public function getUser(string $id): void
    {
        $user = $this->userService->getUserById($id);
        if ($user) {
            ResponseHandler::respond(true, $user, 200);
        } else {
            ResponseHandler::respond(false, 'User not found', 404);
        }
    }

    /**
     * Current user's profile for GET /api/v1/user/profile (JWT or session).
     */
    public function getProfile(): void
    {
        $userId = $this->getAuthenticatedUserId();
        if ($userId === null) {
            ResponseHandler::respond(false, 'User not authenticated', 401);
            return;
        }

        $user = $this->userService->getUserById($userId);
        if (!$user) {
            ResponseHandler::respond(false, 'User not found', 404);
            return;
        }

        unset($user['password'], $user['token']);
        if (isset($user['_id'])) {
            $user['_id'] = (string) $user['_id'];
        }
        if (isset($user['createdAt'])) {
            $ca = $user['createdAt'];
            if ($ca instanceof \MongoDB\BSON\UTCDateTime) {
                $user['createdAt'] = $ca->toDateTime()->format(DATE_ATOM);
            } elseif (is_object($ca) && method_exists($ca, 'toDateTime')) {
                $user['createdAt'] = $ca->toDateTime()->format(DATE_ATOM);
            }
        }

        ResponseHandler::respond(true, $user, 200);
    }

    public function saveBook(): void
    {
        $userId = $this->getAuthenticatedUserId();
        if ($userId === null) {
            ResponseHandler::respond(false, 'User not authenticated', 401);
            return;
        }

        $bookId = $this->getRequestData()['book_id'] ?? null;

        if (empty($bookId)) {
            ResponseHandler::respond(false, 'Book ID is required', 400);
            return;
        }

        if ($this->userService->saveBook($userId, $bookId)) {
            ResponseHandler::respond(true, 'Book saved successfully', 200);
        } else {
            ResponseHandler::respond(false, 'Failed to save book', 400);
        }
    }

    public function getSavedBooks(): void
    {
        $userId = $this->getAuthenticatedUserId();
        if ($userId === null) {
            ResponseHandler::respond(false, 'User not authenticated', 401);
            return;
        }

        $bookIds = $this->userService->getSavedBooks($userId);
        $books = [];
        foreach ($bookIds as $bookId) {
            $book = $this->bookService->getBookDetails($bookId);
            if ($book) {
                BookDisplayHelper::applyThumbnailForApi($book);
                $books[] = $book;
            }
        }

        ResponseHandler::respond(true, $books, 200);
    }

    public function getDownloadedBooks(): void
    {
        $userId = $this->getAuthenticatedUserId();
        if ($userId === null) {
            ResponseHandler::respond(false, 'User not authenticated', 401);
            return;
        }

        $bookIds = $this->userService->getDownloadedBookIds($userId);
        $books = [];
        foreach ($bookIds as $bookId) {
            $book = $this->bookService->getBookDetails($bookId);
            if ($book) {
                BookDisplayHelper::applyThumbnailForApi($book);
                $books[] = $book;
            }
        }

        ResponseHandler::respond(true, $books, 200);
    }

    public function removeBook(): void
    {
        $userId = $this->getAuthenticatedUserId();
        if ($userId === null) {
            ResponseHandler::respond(false, 'User not authenticated', 401);
            return;
        }

        $bookId = $this->getRequestData()['book_id'] ?? null;

        if (empty($bookId)) {
            ResponseHandler::respond(false, 'Book ID is required', 400);
            return;
        }

        if ($this->userService->removeBook($userId, $bookId)) {
            ResponseHandler::respond(true, 'Book removed successfully', 200);
        } else {
            ResponseHandler::respond(false, 'Failed to remove book', 400);
        }
    }

    /**
     * View error logs (admin only)
     */
    public function viewLogs(): void
    {
        // Check if user is admin
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // First verify JWT token (should be handled by middleware)
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? null;

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            ResponseHandler::respond(false, 'Unauthorized access', 401);
            exit();
        }

        // Even with valid token, check if user is admin in session
        if (!AuthenticatedUser::isAdmin()) {
            ResponseHandler::respond(false, 'Unauthorized: Admin privileges required', 403);
            return;
        }

        $logPath = dirname(__DIR__, 2) . '/storage/logs/php_errors.log';
        $requestLogPath = dirname(__DIR__, 2) . '/storage/logs/requests.log';

        $logs = [];

        // Check if error log exists and is readable
        if (file_exists($logPath) && is_readable($logPath)) {
            // Get the last 100 lines (adjust as needed)
            $errorLogs = $this->getTailOfFile($logPath, 100);
            $logs['errors'] = $errorLogs;
        } else {
            $logs['errors'] = 'Error log file not found or not readable';
        }

        // Check if request log exists and is readable
        if (file_exists($requestLogPath) && is_readable($requestLogPath)) {
            $requestLogs = $this->getTailOfFile($requestLogPath, 50);
            $logs['requests'] = $requestLogs;
        } else {
            $logs['requests'] = 'Request log file not found or not readable';
        }

        // Send logs as JSON response
        ResponseHandler::respond(true, $logs, 200);
    }

    /**
     * Helper method to get the last N lines of a file
     *
     * @return list<string>
     */
    private function getTailOfFile(string $filePath, int $lines = 100): array
    {
        return array_slice(file($filePath, FILE_IGNORE_NEW_LINES) ?: [], -$lines);
    }

    /**
     * Update user profile information
     * Currently supports updating username
     */
    public function updateProfile(): void
    {
        $userId = $this->getAuthenticatedUserId();
        if ($userId === null) {
            ResponseHandler::respond(false, 'User not authenticated', 401);
            return;
        }

        // Get input data
        $inputJSON = file_get_contents('php://input');
        $input = json_decode($inputJSON, true);

        if (!$input) {
            ResponseHandler::respond(false, 'No data received', 400);
            return;
        }
        $updates = [];

        // Handle username update
        if (isset($input['username'])) {
            $newUsername = trim($input['username']);

            // Validate username
            if (empty($newUsername)) {
                ResponseHandler::respond(false, 'Username cannot be empty', 400);
                return;
            }

            if (strlen($newUsername) < 3) {
                ResponseHandler::respond(false, 'Username must be at least 3 characters', 400);
                return;
            }

            $updates['username'] = $newUsername;
        }

        // No updates to process
        if (empty($updates)) {
            ResponseHandler::respond(false, 'No valid updates provided', 400);
            return;
        }

        // Update the user profile
        $result = $this->userService->updateUser($userId, $updates);

        if ($result) {
            // Update session data if username was changed
            if (isset($updates['username'])) {
                $_SESSION['username'] = $updates['username'];
            }

            ResponseHandler::respond(true, 'Profile updated successfully', 200);
        } else {
            ResponseHandler::respond(false, 'Failed to update profile', 500);
        }
    }

    /**
     * Change password (requires current password). JSON: current_password, new_password
     */
    public function changePassword(): void
    {
        $userId = $this->getAuthenticatedUserId();
        if ($userId === null) {
            ResponseHandler::respond(false, 'User not authenticated', 401);
            return;
        }

        $inputJSON = file_get_contents('php://input');
        $input = json_decode($inputJSON, true);
        if (!is_array($input)) {
            ResponseHandler::respond(false, 'Invalid request body', 400);
            return;
        }

        $current = (string) ($input['current_password'] ?? '');
        $new = (string) ($input['new_password'] ?? '');

        if ($current === '' || $new === '') {
            ResponseHandler::respond(false, 'Current password and new password are required', 400);
            return;
        }

        if (strlen($new) < 8) {
            ResponseHandler::respond(false, 'New password must be at least 8 characters', 400);
            return;
        }

        $user = $this->userService->getUserById($userId);
        if (!$user || empty($user['password'])) {
            ResponseHandler::respond(false, 'Unable to update password', 500);
            return;
        }

        if (!password_verify($current, $user['password'])) {
            ResponseHandler::respond(false, 'Current password is incorrect', 403);
            return;
        }

        $ok = $this->userService->updateUser($userId, [
            'password' => password_hash($new, PASSWORD_BCRYPT),
        ]);

        if ($ok) {
            ResponseHandler::respond(true, 'Password updated successfully', 200);
        } else {
            ResponseHandler::respond(false, 'Failed to update password', 500);
        }
    }

    public function support(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Process form data (or a JSON body, for backward compatibility)
        $data = $this->getRequestData();
        $name = $data['name'] ?? 'Anonymous';
        $email = $data['email'] ?? 'no-reply@example.com';
        $message = $data['message'] ?? null;

        if (empty($message)) {
            ResponseHandler::respond(false, 'Message is required', 400);
            return;
        }

        try {
            // Process uploaded embedded images (if any)
            $attachments = [];

            if (!empty($_FILES['embedded_images']) && is_array($_FILES['embedded_images']['name'])) {
                $uploadDir = dirname(__DIR__, 2) . '/public/uploads/support/';

                // Create directory if it doesn't exist
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                // Process each uploaded file
                $fileCount = count($_FILES['embedded_images']['name']);

                // Limit to 5 files max
                $fileCount = min($fileCount, 5);

                for ($i = 0; $i < $fileCount; $i++) {
                    // Skip files with errors
                    if ($_FILES['embedded_images']['error'][$i] !== UPLOAD_ERR_OK) {
                        continue;
                    }

                    // Validate file size (5MB max)
                    if ($_FILES['embedded_images']['size'][$i] > 5 * 1024 * 1024) {
                        continue;
                    }

                    // Validate mime type by sniffing the actual bytes (not the client-supplied
                    // filename/Content-Type), and derive the stored extension from that same
                    // allowlist — never from the client filename, which a file with faked magic
                    // bytes plus a ".php" name would otherwise smuggle straight into the webroot
                    // and hand mod_php a script to execute (CWE-434).
                    $extensionsByMime = [
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/gif' => 'gif',
                    ];
                    $finfo = new \finfo(FILEINFO_MIME_TYPE);
                    $mimeType = $finfo->file($_FILES['embedded_images']['tmp_name'][$i]);

                    if (!isset($extensionsByMime[$mimeType])) {
                        continue;
                    }

                    // Generate unique filename
                    $filename = uniqid('support_', true) . '.' . $extensionsByMime[$mimeType];
                    $filepath = $uploadDir . $filename;

                    // Move the uploaded file to the destination
                    if (move_uploaded_file($_FILES['embedded_images']['tmp_name'][$i], $filepath)) {
                        // Add to attachments array
                        $attachments[] = [
                            'path' => $filepath,
                            'filename' => $_FILES['embedded_images']['name'][$i],
                            'type' => $mimeType
                        ];
                    }
                }
            }

            // Use the EmailService with PHPMailer to send email with attachments
            $result = $this->emailService->sendSupportEmail($email, $name, $message, $attachments);

            $this->deleteSupportAttachments($attachments);

            if ($result) {
                ResponseHandler::respond(true, 'Support request sent successfully', 200);
            } else {
                throw new \Exception('Email sending failed');
            }
        } catch (\Exception $e) {
            // Log the error
            error_log('Error sending support email: ' . $e->getMessage());
            $this->deleteSupportAttachments($attachments);
            ResponseHandler::respond(false, 'Failed to send support request. Please try again later.', 500);
        }
    }

    /**
     * Support attachments are only ever needed for the outgoing email — leaving them under
     * the webroot indefinitely is unbounded exposure for no benefit once it's sent (or failed).
     *
     * @param array<int, array{path: string}> $attachments
     */
    private function deleteSupportAttachments(array $attachments): void
    {
        foreach ($attachments as $attachment) {
            if (is_file($attachment['path'])) {
                @unlink($attachment['path']);
            }
        }
    }
}
