<?php

namespace App\Controllers;

use App\Services\BookService;
use App\Services\ContentSearchService;
use App\Services\UserService;
use App\Includes\AuthenticatedUser;
use App\Includes\ResponseHandler;
use App\Helpers\FileHelper;
use App\Helpers\BookDisplayHelper;
use App\Helpers\Database\MongoHelper;

class BookController
{
    /** Longer queries add nothing to search quality and only cost embedder time. */
    private const MAX_CONTENT_QUERY_LENGTH = 300;

    private BookService $bookService;
    private ContentSearchService $contentSearchService;

    public function __construct()
    {
        $this->bookService = new BookService();
        $this->contentSearchService = new ContentSearchService();
    }

    public function featuredBooks(): void
    {
        $books = $this->bookService->getFeaturedBooks();
        foreach ($books as &$book) {
            unset($book['pdf_path']);
            unset($book['reviews']);
            BookDisplayHelper::applyThumbnailForApi($book);
        }
        unset($book);
        if ($books) {
            ResponseHandler::respond(true, $books);
        } else {
            ResponseHandler::respond(false, 'No books found', 404);
        }
    }

    public function deleteBook(string $id): void
    {
        // Check if user is admin
        if (!AuthenticatedUser::isAdmin()) {
            ResponseHandler::respond(false, 'Unauthorized: Admin privileges required', 403);
            return;
        }

        $response = $this->bookService->deleteBook($id);
        if ($response) {
            ResponseHandler::respond(true, 'Book deleted successfully');
        } else {
            ResponseHandler::respond(false, 'Error deleting book', 400);
        }
    }

    public function updateBook(string $id): void
    {
        // Check if user is admin
        if (!AuthenticatedUser::isAdmin()) {
            ResponseHandler::respond(false, 'Unauthorized: Admin privileges required', 403);
            return;
        }

        // Read and decode JSON data from request body
        $requestBody = file_get_contents('php://input');
        $data = json_decode($requestBody, true);

        // If JSON parsing failed, check if regular form data exists
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Fall back to $_POST for traditional form submissions
            $data = $_POST;
        }

        // First get the current book data
        $currentBook = $this->bookService->getBookDetails($id);
        if (!$currentBook) {
            ResponseHandler::respond(false, 'Book not found', 404);
            return;
        }

        // Only update fields that are provided in the request
        $title = isset($data['title']) ? $data['title'] : $currentBook['title'];
        $author = isset($data['author']) ? $data['author'] : $currentBook['author'];
        $year = isset($data['year']) ? $data['year'] : ($currentBook['year'] ?? '');
        $description = isset($data['description']) ? $data['description'] : $currentBook['description'];
        $status = isset($data['status']) ? $data['status'] : $currentBook['status'];
        $featured = isset($data['featured']) ? $data['featured'] : $currentBook['featured'];
        $isbn = isset($data['isbn']) ? $data['isbn'] : ($currentBook['isbn'] ?? '');
        $downloadable = isset($data['downloadable']) ?
            ($data['downloadable'] === 'yes' || $data['downloadable'] === true || $data['downloadable'] === 'true') :
            ($currentBook['downloadable'] ?? true);

        // Check if categories are being updated
        $categories = [];
        if (isset($data['categories'])) {
            // Parse categories from the request
            if (is_string($data['categories'])) {
                // Handle JSON string format
                $categories = json_decode($data['categories'], true) ?? [];
            } elseif (is_array($data['categories'])) {
                // Categories already as array
                $categories = $data['categories'];
            }
        } elseif (isset($currentBook['categories'])) {
            // Use existing categories if not provided in request
            $categories = MongoHelper::toArray($currentBook['categories']);
        }

        // Update the book in the database
        $response = $this->bookService->updateBook(
            $id,
            $title,
            $author,
            $year,
            $description,
            $categories,
            $status,
            $featured,
            $isbn,
            $downloadable
        );

        if ($response) {
            ResponseHandler::respond(true, 'Book updated successfully');
        } else {
            ResponseHandler::respond(false, 'Error updating book', 400);
        }
    }

    public function listBooks(): void
    {
        $books = $this->bookService->getPublicBooks();
        foreach ($books as &$book) {
            unset($book['pdf_path']);
            unset($book['reviews']);
            BookDisplayHelper::applyThumbnailForApi($book);
        }
        unset($book);
        if ($books) {
            ResponseHandler::respond(true, $books);
        } else {
            ResponseHandler::respond(false, 'No books found', 404);
        }
    }

    public function getAllBooks(): void
    {
        // Check if user is admin
        if (!AuthenticatedUser::isAdmin()) {
            ResponseHandler::respond(false, 'Unauthorized: Admin privileges required', 403);
            return;
        }

        $books = $this->bookService->getAllBooks();
        if ($books) {
            foreach ($books as &$book) {
                unset($book['pdf_path']);
                unset($book['file_path']);
                unset($book['reviews']);
                BookDisplayHelper::applyThumbnailForApi($book);
            }
            unset($book);
            ResponseHandler::respond(true, $books);
        } else {
            ResponseHandler::respond(false, 'No books found', 404);
        }
    }

    /**
     * List the distinct categories already in use across all books, for picker/autocomplete UIs.
     * An empty catalog is a valid state, not an error.
     */
    public function getCategories(): void
    {
        $categories = $this->bookService->getCategories();
        ResponseHandler::respond(true, $categories);
    }

    public function viewBook(string $id): void
    {
        $book = $this->bookService->getBookDetails($id);
        // Treat a hidden book as not found so its existence isn't disclosed either.
        if ($book && $this->isHiddenFromCaller($book)) {
            $book = null;
        }
        if ($book) {
            unset($book['pdf_path'], $book['file_path']);
            BookDisplayHelper::applyThumbnailForApi($book);
            ResponseHandler::respond(true, $book);
        } else {
            ResponseHandler::respond(false, 'Book not found', 404);
        }
    }

    public function searchBooks(string $search): void
    {
        // Zero matches is a normal outcome for a search, not an error — respond
        // with 200 + an empty array so the frontend's "No books found" message
        // (as opposed to its generic network-error message) is reachable; axios
        // treats any non-2xx status as a rejected promise regardless of body.
        $publicOnly = !AuthenticatedUser::isAdmin();
        $books = $this->bookService->searchBooks(urldecode($search), $publicOnly);
        foreach ($books as &$book) {
            unset($book['pdf_path'], $book['file_path'], $book['reviews']);
            BookDisplayHelper::applyThumbnailForApi($book);
        }
        unset($book);
        ResponseHandler::respond(true, $books);
    }

    /**
     * GET /api/v1/content-search?q=... — books whose pages match the query by words or meaning,
     * each with its best pages (page number + snippet) for deep-linking into the reader.
     */
    /**
     * GET /api/v1/features — optional features this deployment has switched on, so the SPA can hide
     * what isn't available.
     */
    public function features(): void
    {
        ResponseHandler::respond(true, ['contentSearch' => ContentSearchService::enabled()]);
    }

    public function contentSearch(): void
    {
        if (!ContentSearchService::enabled()) {
            ResponseHandler::respond(false, 'Content search is turned off on this server', 404);
            return;
        }
        $query = trim((string) ($_GET['q'] ?? ''));
        if ($query === '') {
            ResponseHandler::respond(true, []);
            return;
        }
        if (mb_strlen($query) > self::MAX_CONTENT_QUERY_LENGTH) {
            ResponseHandler::respond(false, 'Search query is too long (max ' . self::MAX_CONTENT_QUERY_LENGTH . ' characters)', 400);
            return;
        }

        $results = $this->contentSearchService->search($query, !AuthenticatedUser::isAdmin());
        foreach ($results as &$result) {
            unset($result['book']['pdf_path'], $result['book']['file_path'], $result['book']['reviews']);
            BookDisplayHelper::applyThumbnailForApi($result['book']);
        }
        unset($result);
        ResponseHandler::respond(true, $results);
    }

    public function addBook(): void
    {
        // Check if user is admin
        if (!AuthenticatedUser::isAdmin()) {
            ResponseHandler::respond(false, 'Unauthorized: Admin privileges required', 403);
            return;
        }

        // Extract form data
        $title = $_POST['title'] ?? '';
        $author = $_POST['author'] ?? '';
        $year = $_POST['year'] ?? '';
        $isbn = $_POST['isbn'] ?? '';
        $description = $_POST['description'] ?? '';
        $categories = json_decode($_POST['categories'] ?? '[]', true);

        // Parse the downloadable value as a boolean
        $downloadable = filter_var($_POST['downloadable'] ?? 'true', FILTER_VALIDATE_BOOLEAN);

        // Validate required fields
        if (empty($title)) {
            ResponseHandler::respond(false, 'Title is required', 400);
            return;
        }

        if ($this->bookService->getBookByTitle($title)) {
            ResponseHandler::respond(false, 'Book already exists', 400);
            return;
        }

        // Check file upload (see https://www.php.net/manual/en/features.file-upload.errors.php)
        if (!isset($_FILES['bookFile'])) {
            ResponseHandler::respond(false, 'No file uploaded (missing bookFile).', 400);
            return;
        }
        $uploadErr = (int) ($_FILES['bookFile']['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadErr !== UPLOAD_ERR_OK) {
            $msg = match ($uploadErr) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE =>
                    'PDF exceeds server upload size limit (php.ini upload_max_filesize / post_max_size).',
                UPLOAD_ERR_PARTIAL => 'PDF upload was interrupted.',
                UPLOAD_ERR_NO_FILE => 'No PDF file was selected.',
                default => 'PDF file upload error (code ' . $uploadErr . ').',
            };
            error_log('bookFile upload error: ' . $uploadErr);
            ResponseHandler::respond(false, $msg, 400);
            return;
        }

        // Initialize FileHelper with temporary path
        $fileHelper = new FileHelper($_FILES['bookFile']['tmp_name']);

        // Store the PDF
        $storedFile = $fileHelper->storeFile($_FILES['bookFile']);

        if (!$storedFile) {
            error_log("Failed to store file");
            ResponseHandler::respond(false, 'Error storing file', 500);
            return;
        }

        error_log("PDF stored successfully at: " . $storedFile['path']);

        $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? (dirname(__DIR__, 2) . '/public');
        // Update the FileHelper with the new stored file path
        $fileHelper = new FileHelper($docRoot . $storedFile['path']);

        // Generate thumbnail
        $thumbnailPath = $fileHelper->getThumbnail();

        // Add the book to the database with file details
        $response = $this->bookService->addBook(
            $title,
            $author,
            $year,
            $description,
            $categories,
            $isbn,
            $storedFile['path'],
            $thumbnailPath,
            $downloadable,
            $storedFile['type'],
            $storedFile['extension']
        );

        if ($response) {
            ResponseHandler::respond(true, $response);
        } else {
            ResponseHandler::respond(false, 'Error adding book', 400);
        }
    }

    /**
     * Handle secure book download
     *
     * @param string|null $bookId MongoDB ID of the book to download
     */
    public function downloadBook(?string $bookId = null): void
    {
        $userId = AuthenticatedUser::id();
        if ($userId === null) {
            ResponseHandler::respond(false, 'Authentication required', 401);
            return;
        }

        // Validate book ID
        if (!$bookId || !preg_match('/^[0-9a-f]{24}$/', $bookId)) {
            ResponseHandler::respond(false, 'Invalid book ID', 400);
            return;
        }

        // Get book details from database
        $bookService = new BookService();
        $book = $bookService->getBookDetails($bookId);

        $rel = $book['file_path'] ?? $book['pdf_path'] ?? '';
        if (!$book || $rel === '') {
            ResponseHandler::respond(false, 'Book not found or has no PDF', 404);
            return;
        }

        // Self-registration is open to anyone, so holding a valid session doesn't imply
        // the book has been published yet — drafts stay admin-only regardless of login.
        if ($this->isHiddenFromCaller($book)) {
            ResponseHandler::respond(false, 'Book not found or has no PDF', 404);
            return;
        }

        // Check if the book is downloadable
        if (isset($book['downloadable']) && $book['downloadable'] === false) {
            ResponseHandler::respond(false, 'This book is not available for download', 403);
            return;
        }

        $pdfPath = $this->resolveStoredPublicFile($rel);
        if ($pdfPath === null) {
            ResponseHandler::respond(false, 'PDF file not found or not readable', 404);
            return;
        }

        try {
            $userService = new UserService();
            $userService->recordDownload($userId, $bookId);
        } catch (\Throwable $e) {
            error_log('recordDownload failed: ' . $e->getMessage());
        }

        error_log("User {$userId} downloaded book {$bookId}");

        // Get the filename for the Content-Disposition header
        $filename = basename($pdfPath);
        if (!empty($book['title'])) {
            $safeTitle = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $book['title']);
            $ext = isset($book['file_extension']) ? '.' . ltrim((string) $book['file_extension'], '.') : '.pdf';
            $filename = $safeTitle . $ext;
        }

        // Set appropriate headers for file download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($pdfPath));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        // Output file content and stop script execution
        readfile($pdfPath);
        exit;
    }

    /**
     * Add or edit the current user's review of a book (one review per user per book)
     */
    public function saveReview(): void
    {
        $userId = AuthenticatedUser::id();
        if ($userId === null) {
            ResponseHandler::respond(false, 'Authentication required', 401);
            return;
        }

        // Get JSON data
        $inputJSON = file_get_contents('php://input');
        $input = json_decode($inputJSON, true);

        // Validate input
        if (empty($input['book_id']) || !isset($input['rating']) || empty($input['comment'])) {
            ResponseHandler::respond(false, 'Missing required fields', 400);
            return;
        }

        // Validate rating
        $rating = intval($input['rating']);
        if ($rating < 1 || $rating > 5) {
            ResponseHandler::respond(false, 'Rating must be between 1 and 5', 400);
            return;
        }

        $userService = new \App\Services\UserService();
        $user = $userService->getUserById($userId);

        $review = [
            'user_id' => $userId,
            'username' => $user['username'] ?? 'Anonymous User',
            'rating' => $rating,
            'comment' => $input['comment'],
            'created_at' => date('Y-m-d H:i:s')
        ];

        // Save review
        $bookService = new BookService();
        $result = $bookService->saveReview($input['book_id'], $review);

        if ($result) {
            ResponseHandler::respond(true, 'Review saved successfully');
        } else {
            ResponseHandler::respond(false, 'Failed to save review', 500);
        }
    }

    /**
     * Get reviews for a book
     * @param string $bookId
     */
    public function getReviews(string $bookId): void
    {
        if (empty($bookId)) {
            ResponseHandler::respond(false, 'Book ID is required', 400);
            return;
        }

        $reviews = $this->bookService->getBookReviews($bookId);
        if ($reviews) {
            ResponseHandler::respond(true, $reviews, 200);
        } else {
            ResponseHandler::respond(false, 'No reviews found', 404);
        }
    }

    /**
     * Handle PDF upload
     * Processes one or more PDF files at once with common metadata
     */
    public function uploadBooks(): void
    {
        // Verify authentication (session should already be checked by middleware)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Check if user is admin - utilizing the session data set during login
        if (!AuthenticatedUser::isAdmin()) {
            ResponseHandler::respond(false, 'Unauthorized: Admin privileges required', 403);
            return;
        }

        // Validate if files were uploaded
        if (empty($_FILES['books']) || !is_array($_FILES['books']['name'])) {
            ResponseHandler::respond(false, 'No PDF files submitted', 400);
            return;
        }

        // Get default metadata that will apply to all books unless overridden
        $defaultAuthor = $_POST['defaultAuthor'] ?? '';
        $defaultCategories = !empty($_POST['defaultCategories']) ? json_decode($_POST['defaultCategories'], true) : [];
        $defaultStatus = $_POST['defaultStatus'] ?? 'draft';
        $defaultDownloadable = filter_var($_POST['defaultDownloadable'] ?? 'true', FILTER_VALIDATE_BOOLEAN);

        // Track upload results
        $results = [
            'success' => [],
            'failed' => []
        ];

        // Process each file
        $fileCount = count($_FILES['books']['name']);
        for ($i = 0; $i < $fileCount; $i++) {
            // Extract individual file data
            $file = [
                'name' => $_FILES['books']['name'][$i],
                'type' => $_FILES['books']['type'][$i],
                'tmp_name' => $_FILES['books']['tmp_name'][$i],
                'error' => $_FILES['books']['error'][$i],
                'size' => $_FILES['books']['size'][$i],
            ];

            // Skip invalid files (MIME is unreliable; extension is authoritative)
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($file['error'] !== UPLOAD_ERR_OK || $ext !== 'pdf') {
                $results['failed'][] = [
                    'filename' => $file['name'],
                    'reason' => $file['error'] !== UPLOAD_ERR_OK
                        ? 'Upload error code ' . $file['error']
                        : 'Not a PDF file',
                ];
                continue;
            }

            // Get book-specific metadata if provided in the request
            $metadataIndex = "metadata_$i";
            $metadata = !empty($_POST[$metadataIndex]) ? json_decode($_POST[$metadataIndex], true) : [];

            // Extract title from filename if not provided in metadata
            $title = $metadata['title'] ?? pathinfo($file['name'], PATHINFO_FILENAME);
            $title = str_replace(['_', '-'], ' ', $title);

            // Apply metadata with fallbacks to defaults
            $author = $metadata['author'] ?? $defaultAuthor;
            $categories = $metadata['categories'] ?? $defaultCategories;
            $status = $metadata['status'] ?? $defaultStatus;
            $downloadable = isset($metadata['downloadable'])
                ? filter_var($metadata['downloadable'], FILTER_VALIDATE_BOOLEAN)
                : $defaultDownloadable;
            $year = $metadata['year'] ?? '';
            $description = $metadata['description'] ?? 'Uploaded via the upload feature';
            $isbn = $metadata['isbn'] ?? '';

            try {
                // Process the PDF file
                $fileHelper = new FileHelper($file['tmp_name']);
                $pdfPath = $fileHelper->storeFile($file);

                if (!$pdfPath || !is_array($pdfPath)) {
                    $results['failed'][] = [
                        'filename' => $file['name'],
                        'reason' => 'Failed to store PDF'
                    ];
                    continue;
                }

                $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? (dirname(__DIR__, 2) . '/public');
                $fileHelper = new FileHelper($docRoot . $pdfPath['path']);

                // Generate thumbnail
                $thumbnailPath = $fileHelper->getThumbnail();

                // Add the book to the database
                $response = $this->bookService->addBook(
                    $title,
                    $author,
                    $year,
                    $description,
                    $categories,
                    $isbn,
                    $pdfPath['path'],
                    $thumbnailPath,
                    $downloadable,
                    $pdfPath['type'],
                    $pdfPath['extension']
                );

                if ($response) {
                    $bookId = $this->extractInsertedId($response);

                    // If book was added successfully, update its status
                    if ($status !== 'draft') {
                        if ($bookId) {
                            $this->bookService->updateBook(
                                $bookId,
                                $title,
                                $author,
                                $year,
                                $description,
                                $categories,
                                $status,
                                false,
                                $isbn,
                                $downloadable
                            );
                        } else {
                            error_log("ERROR: Could not extract book ID from response during upload");
                        }
                    }

                    $results['success'][] = [
                        'filename' => $file['name'],
                        'title' => $title,
                        'id' => $bookId ?? json_encode($response)
                    ];
                } else {
                    $results['failed'][] = [
                        'filename' => $file['name'],
                        'reason' => 'Database error while storing book information'
                    ];
                }
            } catch (\Exception $e) {
                error_log('Upload error: ' . $e->getMessage());
                $results['failed'][] = [
                    'filename' => $file['name'],
                    'reason' => 'Processing error: ' . $e->getMessage()
                ];
            }
        }

        // Log the upload activity
        error_log(sprintf(
            "User %s uploaded %d books (%d successful, %d failed)",
            AuthenticatedUser::id() ?? 'unknown',
            $fileCount,
            count($results['success']),
            count($results['failed'])
        ));

        // Return response with results
        if (empty($results['failed'])) {
            ResponseHandler::respond(true, [
                'message' => 'All books uploaded successfully',
                'results' => $results
            ]);
        } else {
            ResponseHandler::respond(
                count($results['success']) > 0,
                [
                    'message' => count($results['success']) > 0
                        ? 'Some books were uploaded with errors'
                        : 'Failed to upload books',
                    'results' => $results
                ],
                count($results['success']) > 0 ? 207 : 400
            );
        }
    }

    /**
     * A non-public (draft) book is invisible to anyone but an admin — used by every
     * unauthenticated/lightly-authenticated book route (view/preview/download) since none of
     * them carry JWT middleware of their own.
     *
     * @param array<string, mixed> $book
     */
    private function isHiddenFromCaller(array $book): bool
    {
        return ($book['status'] ?? null) !== 'public' && !AuthenticatedUser::isAdmin();
    }

    /**
     * Extract the inserted book's ID from a Books::addBook() result, whatever shape it comes in
     * (data.insertedId, a BSON _id object/string, or a root-level insertedId). Null when none match.
     */
    private function extractInsertedId(mixed $response): ?string
    {
        if (is_array($response) && isset($response['data']['insertedId'])) {
            return (string) $response['data']['insertedId'];
        }
        if (is_array($response) && isset($response['_id'])) {
            $id = $response['_id'];
            if (is_object($id) && method_exists($id, '__toString')) {
                return (string) $id;
            }
            return is_string($id) ? $id : null;
        }
        if (is_array($response) && isset($response['insertedId'])) {
            return (string) $response['insertedId'];
        }
        return null;
    }

    /**
     * Resolve a web path stored in the DB (e.g. /assets/uploads/documents/...) to a readable absolute path.
     * Tries DOCUMENT_ROOT, project public/, and the legacy wrong directory from an old FileHelper bug (parent/public).
     */
    private function resolveStoredPublicFile(string $relativeWebPath): ?string
    {
        $relativeWebPath = '/' . ltrim($relativeWebPath, '/');
        $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? (dirname(__DIR__, 2) . '/public');
        $candidates = [
            $docRoot . $relativeWebPath,
            dirname(__DIR__, 2) . '/public' . $relativeWebPath,
            dirname(__DIR__, 3) . '/public' . $relativeWebPath,
        ];
        foreach ($candidates as $p) {
            $real = realpath($p);
            if ($real !== false && is_readable($real)) {
                return $real;
            }
        }
        return null;
    }

    /**
     * Serve cover image; uses the same filesystem resolution as PDFs (fixes legacy wrong upload dirs).
     * Public (no JWT) — catalog thumbnails are not treated as secrets.
     */
    public function streamBookThumbnail(?string $bookId = null): void
    {
        if (!$bookId || !preg_match('/^[0-9a-f]{24}$/', $bookId)) {
            http_response_code(400);
            exit;
        }

        $book = $this->bookService->getBookDetails($bookId);
        $rel = '';
        if ($book) {
            $rel = $book['thumbnail'] ?? $book['thumbnail_path'] ?? '';
        }
        $rel = is_string($rel) ? trim($rel) : '';

        if ($rel !== '' && $book) {
            $abs = $this->resolveStoredPublicFile($rel);
            if ($abs !== null) {
                $this->outputImageFile($abs);
                exit;
            }
        }

        $placeholder = $this->resolveStoredPublicFile('/assets/uploads/thumbnails/placeholder-book.jpg');
        if ($placeholder !== null) {
            header('Content-Type: image/jpeg');
            header('Cache-Control: public, max-age=86400');
            readfile($placeholder);
            exit;
        }

        http_response_code(404);
        exit;
    }

    private function outputImageFile(string $absPath): void
    {
        $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
        $map = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
        ];
        header('Content-Type: ' . ($map[$ext] ?? 'image/jpeg'));
        header('Cache-Control: public, max-age=86400');
        readfile($absPath);
    }

    /**
     * Stream book PDF for in-browser preview (public; no JWT required).
     *
     * @param string|null $bookId MongoDB ID of the book to stream
     */
    public function streamBookFile(?string $bookId = null): void
    {
        // Validate book ID
        if (!$bookId || !preg_match('/^[0-9a-f]{24}$/', $bookId)) {
            ResponseHandler::respond(false, 'Invalid book ID', 400);
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Get book details from database
        $book = $this->bookService->getBookDetails($bookId);

        // Online preview has no JWT middleware of its own, so the check has to happen here.
        if ($book && $this->isHiddenFromCaller($book)) {
            $book = null;
        }

        $relativePath = $book['file_path'] ?? $book['pdf_path'] ?? '';
        if (!$book || $relativePath === '') {
            ResponseHandler::respond(false, 'Book not found or has no file', 404);
            return;
        }

        $filePath = $this->resolveStoredPublicFile($relativePath);
        if ($filePath === null) {
            ResponseHandler::respond(false, 'File not found or not accessible', 404);
            return;
        }

        // Determine content type based on file extension
        $contentType = 'application/pdf'; // Default and forced to PDF
        $fileExtension = 'pdf';

        // Ensure we are only serving PDFs
        $actualExtension = isset($book['file_extension']) ? strtolower($book['file_extension']) : 'pdf';
        if ($actualExtension !== 'pdf') {
             // If for some reason a non-PDF is requested (legacy data), we might want to block it or
             // try to serve it as PDF (which might fail in browser but acts as a restriction)
             // For strict restriction:
             // ResponseHandler::respond(false, 'Only PDF files are supported', 400);
             // return;
             // But existing files might still need to be accessible if we didn't delete them.
             // However, the requirement is "Restrict to PDF Only", implying we drop support.
             // Let's force it to treat everything as PDF or just default to it.
        }

        // Generate filename if not provided
        $filename = basename($filePath);
        if (!empty($book['title'])) {
            // Create a safe filename based on the book title
            $safeTitle = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $book['title']);
            $filename = $safeTitle . '.' . $fileExtension;
        }

        // Set appropriate headers for streaming
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate');
        header('Expires: 0');
        header('Pragma: public');

        // Send file content and exit
        readfile($filePath);
        exit;
    }
}
