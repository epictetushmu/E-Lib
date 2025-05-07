<?php
namespace App\Controllers;

use App\Services\BookService; 
use App\Includes\ResponseHandler;
use App\Helpers\FileHelper;

class BookController {
    private $bookService;
    private $response; 

    public function __construct() {
        $this->bookService = new BookService();
        $this->response = new ResponseHandler();
    }   

    public function featuredBooks() {
        $books = $this->bookService->getFeaturedBooks();
        foreach ($books as &$book) {
            unset($book['pdf_path']);
            unset($book['reviews']);
        }
        if ($books) {
            $this->response->respond(true, $books);
        } else {
            $this->response->respond(false, 'No books found', 404);
        }
    }   

    public function deleteBook($id) {
        $response = $this->bookService->deleteBook($id);
        if ($response) {
            return $this->response->respond(true, 'Book deleted successfully');
        } else {
            return $this->response->respond(false, 'Error deleting book', 400);
        }
    }

    public function updateBook($id) {
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
            return $this->response->respond(false, 'Book not found', 404);
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
            } else if (is_array($data['categories'])) {
                // Categories already as array
                $categories = $data['categories'];
            }
        } else if (isset($currentBook['categories'])) {
            // Use existing categories if not provided in request
            if ($currentBook['categories'] instanceof \MongoDB\Model\BSONArray) {
                $categories = $currentBook['categories']->getArrayCopy();
            } else if (is_array($currentBook['categories'])) {
                $categories = $currentBook['categories'];
            }
        }
        
        // Update the book in the database
        $response = $this->bookService->updateBook(
            $id, $title, $author, $year, $description, $categories, $status, $featured, $isbn, $downloadable
        );

        if ($response) {
            return $this->response->respond(true, 'Book updated successfully');
        } else {
            return $this->response->respond(false, 'Error updating book', 400);
        }
    }

    public function listBooks() {
        $books = $this->bookService->getPublicBooks();
        foreach ($books as &$book) {
            unset($book['pdf_path']);
            unset($book['reviews']);
        } 
        if ($books) {
            return $this->response->respond(true, $books);
        } else {
            return $this->response->respond(false, 'No books found', 404);
        }
    }

    public function getAllBooks() {
        $books = $this->bookService->getAllBooks();
        if ($books) { 
            return $this->response->respond(true, $books);
        } else {
            return $this->response->respond(false, 'No books found', 404);
        }
    }

    public function viewBook($id) {
        $book = $this->bookService->getBookDetails($id);
        if ($book) {
            return $this->response->respond(true, $book);
        } else {
            return $this->response->respond(false, 'Book not found', 404);
        }
    }

    public function searchBooks($search) {
        $books = $this->bookService->searchBooks($search);
        if ($books) {
            return $this->response->respond(true, $books);
        } else {
            return $this->response->respond(false, 'No books found', 404);
        }
    }
    
    public function addBook() {
        
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
            return $this->response->respond(false, 'Title is required', 400);
        }

        if ($this->bookService->getBookByTitle($title)) {
            return $this->response->respond(false, 'Book already exists', 400);
        }
      
        // Check file upload
        if (!isset($_FILES['bookPdf']) || $_FILES['bookPdf']['error'] != 0) {
            error_log("File upload error: " . ($_FILES['bookPdf']['error'] ?? 'No file uploaded'));
            return $this->response->respond(false, 'PDF file upload error', 400);
        }

        // Initialize FileHelper with temporary path
        $fileHelper = new FileHelper($_FILES['bookPdf']['tmp_name']);
        
        // Store the PDF
        $storedPdf = $fileHelper->storeFile($_FILES['bookPdf']);
        
        if (!$storedPdf) {
            error_log("Failed to store PDF");
            return $this->response->respond(false, 'Error storing PDF', 500);
        }
        
        // Extract the path from the result if it's an array
        
        error_log("PDF stored successfully at: {${$storedPdf['path']}}");
        
        // Generate thumbnail
        $thumbnailPath = $fileHelper->getThumbnail();
        
        // Add the book to the database
        $response = $this->bookService->addBook(
            $title, $author, $year, $description, $categories, $isbn,
            $storedPdf['path'], $thumbnailPath, $downloadable
        );
        
        if ($response) {
            return $this->response->respond(true, $response);
        } else {
            return $this->response->respond(false, 'Error adding book', 400);
        }
    }

    /**
     * Handle secure book download
     * 
     * @param string $bookId MongoDB ID of the book to download
     */
    public function downloadBook($bookId = null) {
        // Check if user is authenticated
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (empty($_SESSION['user_id'])) {
            header('Location: /?showLogin=true&redirect=' . urlencode($_SERVER['REQUEST_URI']));
            exit;
        }
        
        // Validate book ID
        if (!$bookId || !preg_match('/^[0-9a-f]{24}$/', $bookId)) {
            header('HTTP/1.0 400 Bad Request');
            echo "Invalid book ID";
            exit;
        }
        
        // Get book details from database
        $bookService = new BookService();
        $book = $bookService->getBookDetails($bookId);
        
        if (!$book || empty($book['pdf_path'])) {
            header('HTTP/1.0 404 Not Found');
            echo "Book not found or has no PDF";
            exit;
        }
        
        // Check if the book is downloadable
        if (isset($book['downloadable']) && $book['downloadable'] === false) {
            header('HTTP/1.0 403 Forbidden');
            echo "This book is not available for download";
            exit;
        }
        
        // Get the absolute path to the PDF file
        $pdfPath = $_SERVER['DOCUMENT_ROOT'] . $book['pdf_path'];
        
        // Check if file exists and is readable
        if (!file_exists($pdfPath) || !is_readable($pdfPath)) {
            header('HTTP/1.0 404 Not Found');
            echo "PDF file not found or not readable";
            exit;
        }
        
        // Log the download
        error_log("User {$_SESSION['user_id']} downloaded book {$bookId}");
        
        // Get the filename for the Content-Disposition header
        $filename = basename($pdfPath);
        if (!empty($book['title'])) {
            // Create a safe filename based on the book title
            $safeTitle = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $book['title']);
            $filename = $safeTitle . '.pdf';
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
     * Add a new book review
     */
    public function addReview() {
        // Check authentication
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (empty($_SESSION['user_id'])) {
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
        $user = $userService->getUserById($_SESSION['user_id']);
      
        $review = [
            'user_id' => $_SESSION['user_id'],
            'username' => $user['username'] ?? 'Anonymous User',
            'rating' => $rating,
            'comment' => $input['comment'],
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // Save review
        $bookService = new BookService();
        $result = $bookService->addReview($input['book_id'], $review);
        
        if ($result) {
            ResponseHandler::respond(true, 'Review added successfully');
        } else {
            ResponseHandler::respond(false, 'Failed to add review', 500);
        }
    }

    /**
     * Get reviews for a book
     */
    public function getReviews($bookId) {
        if (empty($bookId)) {
            ResponseHandler::respond(false, 'Book ID is required', 400);
            return;
        }
        
        $bookService = new BookService();
        $reviews = $bookService->getBookReviews($bookId);
        if($reviews){ 
            ResponseHandler::respond(true, $reviews, 200, );
        }else { 
            ResponseHandler::respond(false, 'No reviews found', 404);
        }
    }

    /**
     * Handle mass upload of PDF books
     * Processes multiple PDF files at once with common metadata
     */
    public function massUploadBooks() {
        // Verify authentication (session should already be checked by middleware)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (empty($_SESSION['user_id'])) {
            return $this->response->respond(false, 'Authentication required', 401);
        }
        
        // Validate if files were uploaded
        if (empty($_FILES['books']) || !is_array($_FILES['books']['name'])) {
            return $this->response->respond(false, 'No PDF files submitted', 400);
        }
        
        // Get default metadata that will apply to all books unless overridden
        $defaultAuthor = $_POST['defaultAuthor'] ?? '';
        $defaultCategories = !empty($_POST['defaultCategories']) ? json_decode($_POST['defaultCategories'], true) : [];
        $defaultStatus = $_POST['defaultStatus'] ?? 'draft';
        $defaultDownloadable = filter_var($_POST['defaultDownloadable'] ?? 'true', FILTER_VALIDATE_BOOLEAN);
        // Remove the debug echo that corrupts JSON response
        
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
            
            // Skip invalid files
            if ($file['error'] !== UPLOAD_ERR_OK || $file['type'] !== 'application/pdf') {
                $results['failed'][] = [
                    'filename' => $file['name'],
                    'reason' => 'Invalid file or not a PDF'
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
            $description = $metadata['description'] ?? 'Uploaded via mass upload feature';
            $isbn = $metadata['isbn'] ?? '';
            
            try {
                // Process the PDF file
                $fileHelper = new FileHelper($file['tmp_name']);
                $pdfPath = $fileHelper->storeFile($file);
                
                if (!$pdfPath) {
                    $results['failed'][] = [
                        'filename' => $file['name'],
                        'reason' => 'Failed to store PDF'
                    ];
                    continue;
                }
                
                // Generate thumbnail
                $thumbnailPath = $fileHelper->getThumbnail();
                
                // Add the book to the database
                $response = $this->bookService->addBook(
                    $title, $author, $year, $description, $categories, $isbn,
                    $pdfPath, $thumbnailPath, $downloadable
                );
                
                if ($response) {
                    // If book was added successfully, update its status
                    if ($status !== 'draft') {
                        // Extract the book ID from the response based on the actual format:
                        // {"status":"success","data":{"insertedId":"681391ccee67d0f7440d8e5a"}}
                        $bookId = null;
                        
                        // Log the exact response format for debugging
                        error_log("Book creation response: " . (is_string($response) ? $response : json_encode($response)));
                        
                        // Handle specific response format with data.insertedId pattern
                        if (is_array($response) && isset($response['data']) && isset($response['data']['insertedId'])) {
                            $bookId = $response['data']['insertedId'];
                            error_log("Found bookId in data.insertedId: $bookId");
                        }
                        // Handle direct string ID (as originally expected)
                        elseif (is_string($response)) {
                            $bookId = $response;
                            error_log("Found bookId as direct string: $bookId");
                        }
                        // Handle _id object scenario
                        elseif (is_array($response) && isset($response['_id'])) {
                            if (is_object($response['_id']) && method_exists($response['_id'], '__toString')) {
                                $bookId = $response['_id']->__toString();
                            } elseif (is_string($response['_id'])) {
                                $bookId = $response['_id'];
                            }
                            error_log("Found bookId in _id field: $bookId");
                        }
                        // Handle direct insertedId at the root level
                        elseif (is_array($response) && isset($response['insertedId'])) {
                            $bookId = $response['insertedId'];
                            error_log("Found bookId in root insertedId: $bookId");
                        }
                        
                        // Only attempt update if we successfully extracted an ID
                        if ($bookId) {
                            error_log("Updating book $bookId to status: $status");
                            
                            $updateResult = $this->bookService->updateBook(
                                $bookId, $title, $author, $year, $description, 
                                $categories, $status, false, $isbn, $downloadable
                            );
                            
                            if ($updateResult) {
                                error_log("Successfully updated book $bookId status to: $status");
                            } else {
                                error_log("Failed to update book $bookId status");
                            }
                        } else {
                            error_log("ERROR: Could not extract book ID from response: " . print_r($response, true));
                        }
                    }
                    
                    // Use the same ID extraction logic for the results section
                    $resultId = null;
                    
                    if (is_array($response) && isset($response['data']) && isset($response['data']['insertedId'])) {
                        $resultId = $response['data']['insertedId'];
                    } elseif (is_string($response)) {
                        $resultId = $response;
                    } elseif (is_array($response) && isset($response['_id'])) {
                        $resultId = is_object($response['_id']) && method_exists($response['_id'], '__toString') 
                            ? $response['_id']->__toString() 
                            : (is_string($response['_id']) ? $response['_id'] : json_encode($response));
                    } elseif (is_array($response) && isset($response['insertedId'])) {
                        $resultId = $response['insertedId'];
                    } else {
                        $resultId = is_string($response) ? $response : json_encode($response);
                    }
                    
                    $results['success'][] = [
                        'filename' => $file['name'],
                        'title' => $title,
                        'id' => $resultId
                    ];
                } else {
                    $results['failed'][] = [
                        'filename' => $file['name'],
                        'reason' => 'Database error while storing book information'
                    ];
                }
            } catch (\Exception $e) {
                error_log('Mass upload error: ' . $e->getMessage());
                $results['failed'][] = [
                    'filename' => $file['name'],
                    'reason' => 'Processing error: ' . $e->getMessage()
                ];
            }
        }
        
        // Log the upload activity
        error_log(sprintf(
            "User %s uploaded %d books (%d successful, %d failed)",
            $_SESSION['user_id'],
            $fileCount,
            count($results['success']),
            count($results['failed'])
        ));
        
        // Return response with results
        if (empty($results['failed'])) {
            return $this->response->respond(true, [
                'message' => 'All books uploaded successfully',
                'results' => $results
            ]);
        } else {
            return $this->response->respond(
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
     * Stream book file with secure token
     * 
     * @param string $bookId MongoDB ID of the book to stream
     */
    public function streamBookFile($bookId = null) {
        // Validate book ID
        if (!$bookId || !preg_match('/^[0-9a-f]{24}$/', $bookId)) {
            $this->response->respond(false, 'Invalid book ID', 400);
            return;
        }
        
        // Check if user is authenticated (already checked by middleware)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Get book details from database
        $book = $this->bookService->getBookDetails($bookId);
        
        if (!$book || empty($book['pdf_path'])) {
            $this->response->respond(false, 'Book not found or has no file', 404);
            return;
        }
        
        // Get the absolute path to the file
        $filePath = $_SERVER['DOCUMENT_ROOT'] . $book['pdf_path'];
        
        // Check if file exists and is readable
        if (!file_exists($filePath) || !is_readable($filePath)) {
            $this->response->respond(false, 'File not found or not accessible', 404);
            return;
        }
        
        // Determine content type based on file extension
        $contentType = 'application/pdf'; // Default to PDF
        if (isset($book['file_extension'])) {
            switch(strtolower($book['file_extension'])) {
                case 'pdf':
                    $contentType = 'application/pdf';
                    break;
                case 'epub':
                    $contentType = 'application/epub+zip';
                    break;
                case 'ppt':
                    $contentType = 'application/vnd.ms-powerpoint';
                    break;
                case 'pptx':
                    $contentType = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
                    break;
                case 'doc':
                    $contentType = 'application/msword';
                    break;
                case 'docx':
                    $contentType = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
                    break;
            }
        }
        
        // Generate filename if not provided
        $filename = basename($filePath);
        if (!empty($book['title'])) {
            // Create a safe filename based on the book title
            $safeTitle = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $book['title']);
            $extension = pathinfo($filePath, PATHINFO_EXTENSION);
            $filename = $safeTitle . '.' . $extension;
        }
        
        // Stream the file using the ResponseHandler
        $this->response->respondWithFile($filePath, $contentType, false, $filename);
    }
}
