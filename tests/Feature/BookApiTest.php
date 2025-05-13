<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use App\Controllers\BookController;
use App\Services\BookService;
use App\Includes\JwtHelper;

class BookApiTest extends TestCase
{
    private $bookController;
    private $mockBookService;
    
    protected function setUp(): void
    {
        // Define JWT_SECRET_KEY if not already defined
        if (!defined('JWT_SECRET_KEY')) {
            define('JWT_SECRET_KEY', 'test_secret_key_for_book_api_tests');
        }
        
        // Create a mock BookService
        $this->mockBookService = $this->getMockBuilder(BookService::class)
            ->disableOriginalConstructor()
            ->getMock();
            
        // Set up test books
        $this->testBooks = [
            [
                '_id' => 'book1',
                'title' => 'Test Book 1',
                'author' => 'Test Author 1',
                'description' => 'Description for test book 1',
                'pages' => 200,
                'published_year' => 2020,
                'path' => '/uploads/books/testbook1.pdf',
                'thumbnail' => '/uploads/thumbnails/testbook1.jpg'
            ],
            [
                '_id' => 'book2',
                'title' => 'Test Book 2',
                'author' => 'Test Author 2',
                'description' => 'Description for test book 2',
                'pages' => 300,
                'published_year' => 2021,
                'path' => '/uploads/books/testbook2.pdf',
                'thumbnail' => '/uploads/thumbnails/testbook2.jpg'
            ]
        ];
        
        // Backup any existing session and clean it for testing
        $this->sessionBackup = isset($_SESSION) ? $_SESSION : null;
        $_SESSION = [];
        
        // Ensure output buffering is clean
        if (ob_get_level()) {
            ob_end_clean();
        }
    }
    
    protected function tearDown(): void
    {
        // Restore original session if it existed
        if ($this->sessionBackup !== null) {
            $_SESSION = $this->sessionBackup;
        } else {
            $_SESSION = [];
        }
        
        // Clean output buffer
        if (ob_get_level()) {
            ob_end_clean();
        }
    }
    
    /**
     * Create a controller with mocked service
     */
    private function createControllerWithMock()
    {
        // Create reflection for BookController
        $reflection = new \ReflectionClass(BookController::class);
        
        // Create an instance without calling constructor
        $bookController = $reflection->newInstanceWithoutConstructor();
        
        // Set the mocked bookService property
        $bookServiceProperty = $reflection->getProperty('bookService');
        $bookServiceProperty->setAccessible(true);
        $bookServiceProperty->setValue($bookController, $this->mockBookService);
        
        return $bookController;
    }
    
    /**
     * Test getting all books
     */
    public function testGetAllBooks()
    {
        // Mock the getAllBooks method to return our test books
        $this->mockBookService->method('getAllBooks')
            ->willReturn($this->testBooks);
            
        // Create controller with our mock
        $bookController = $this->createControllerWithMock();
        
        // Capture output
        ob_start();
        $bookController->getAllBooks();
        $output = ob_get_clean();
        
        // Parse response JSON
        $response = json_decode($output, true);
        
        // Assert response was successful
        $this->assertTrue($response['success']);
        $this->assertCount(2, $response['data']);
        $this->assertEquals('Test Book 1', $response['data'][0]['title']);
        $this->assertEquals('Test Book 2', $response['data'][1]['title']);
    }
    
    /**
     * Test getting a single book by ID
     */
    public function testGetBookById()
    {
        // Mock the getBookDetails method to return a specific book
        $this->mockBookService->method('getBookDetails')
            ->with($this->equalTo('book1'))
            ->willReturn($this->testBooks[0]);
            
        // Create controller with our mock
        $bookController = $this->createControllerWithMock();
        
        // Set up request parameters
        $_GET['id'] = 'book1';
        
        // Capture output
        ob_start();
        $bookController->getBook();
        $output = ob_get_clean();
        
        // Parse response JSON
        $response = json_decode($output, true);
        
        // Assert response was successful
        $this->assertTrue($response['success']);
        $this->assertEquals('Test Book 1', $response['data']['title']);
        $this->assertEquals('Test Author 1', $response['data']['author']);
    }
    
    /**
     * Test getting a non-existent book
     */
    public function testGetNonExistentBook()
    {
        // Mock the getBookDetails method to return null
        $this->mockBookService->method('getBookDetails')
            ->with($this->equalTo('nonexistent'))
            ->willReturn(null);
            
        // Create controller with our mock
        $bookController = $this->createControllerWithMock();
        
        // Set up request parameters
        $_GET['id'] = 'nonexistent';
        
        // Capture output
        ob_start();
        $bookController->getBook();
        $output = ob_get_clean();
        
        // Parse response JSON
        $response = json_decode($output, true);
        
        // Assert response indicates failure
        $this->assertFalse($response['success']);
        $this->assertEquals('Book not found', $response['message']);
    }
    
    /**
     * Test searching for books
     */
    public function testSearchBooks()
    {
        // Mock the searchBooks method to return filtered books
        $this->mockBookService->method('searchBooks')
            ->with($this->equalTo('Test Author 1'))
            ->willReturn([$this->testBooks[0]]);
            
        // Create controller with our mock
        $bookController = $this->createControllerWithMock();
        
        // Set up request parameters
        $_GET['query'] = 'Test Author 1';
        
        // Capture output
        ob_start();
        $bookController->searchBooks();
        $output = ob_get_clean();
        
        // Parse response JSON
        $response = json_decode($output, true);
        
        // Assert response was successful
        $this->assertTrue($response['success']);
        $this->assertCount(1, $response['data']);
        $this->assertEquals('Test Book 1', $response['data'][0]['title']);
    }
    
    /**
     * Test adding a new book (admin functionality)
     */
    public function testAddBook()
    {
        // Setup admin session
        $_SESSION = [
            'user_id' => 'admin123',
            'isAdmin' => true
        ];
        
        // Mock processBookUpload to return true with fake file info
        $this->mockBookService->method('processBookUpload')
            ->willReturn([
                'success' => true,
                'path' => '/uploads/books/newbook.pdf',
                'thumbnail' => '/uploads/thumbnails/newbook.jpg'
            ]);
        
        // Mock addBook to return success with new book ID
        $this->mockBookService->method('addBook')
            ->willReturn(['success' => true, 'id' => 'newbook123']);
            
        // Create controller with our mock
        $bookController = $this->createControllerWithMock();
        
        // Set up request data for new book
        $_POST = [
            'title' => 'New Test Book',
            'author' => 'New Test Author',
            'description' => 'Description for new test book',
            'pages' => 150,
            'published_year' => 2022,
            'downloadable' => 'true'
        ];
        
        // Mock file upload
        $_FILES = [
            'bookPdf' => [
                'tmp_name' => '/tmp/phpxyz123',
                'name' => 'newbook.pdf',
                'type' => 'application/pdf',
                'size' => 1024000,
                'error' => 0
            ]
        ];
        
        // Capture output
        ob_start();
        $bookController->addBook();
        $output = ob_get_clean();
        
        // Parse response JSON
        $response = json_decode($output, true);
        
        // Assert response was successful
        $this->assertTrue($response['success']);
        $this->assertEquals('Book added successfully', $response['message']);
        $this->assertEquals('newbook123', $response['data']['id']);
    }
    
    /**
     * Test adding a book without admin privileges
     */
    public function testAddBookWithoutAdminPrivileges()
    {
        // Setup non-admin session
        $_SESSION = [
            'user_id' => 'user123',
            'isAdmin' => false
        ];
        
        // Create controller with our mock
        $bookController = $this->createControllerWithMock();
        
        // Capture output
        ob_start();
        $bookController->addBook();
        $output = ob_get_clean();
        
        // Parse response JSON
        $response = json_decode($output, true);
        
        // Assert response indicates failure due to lack of privileges
        $this->assertFalse($response['success']);
        $this->assertEquals('Unauthorized: Admin privileges required', $response['message']);
    }
    
    /**
     * Test deleting a book
     */
    public function testDeleteBook()
    {
        // Setup admin session
        $_SESSION = [
            'user_id' => 'admin123',
            'isAdmin' => true
        ];
        
        // Mock deleteBook to return success
        $this->mockBookService->method('deleteBook')
            ->with($this->equalTo('book1'))
            ->willReturn(['success' => true]);
            
        // Create controller with our mock
        $bookController = $this->createControllerWithMock();
        
        // Set up request parameters
        $_GET['id'] = 'book1';
        
        // Capture output
        ob_start();
        $bookController->deleteBook();
        $output = ob_get_clean();
        
        // Parse response JSON
        $response = json_decode($output, true);
        
        // Assert response was successful
        $this->assertTrue($response['success']);
        $this->assertEquals('Book deleted successfully', $response['message']);
    }
    
    /**
     * Test getting featured books
     */
    public function testGetFeaturedBooks()
    {
        // Mock the getFeaturedBooks method
        $this->mockBookService->method('getFeaturedBooks')
            ->willReturn([$this->testBooks[0]]);
            
        // Create controller with our mock
        $bookController = $this->createControllerWithMock();
        
        // Capture output
        ob_start();
        $bookController->getFeaturedBooks();
        $output = ob_get_clean();
        
        // Parse response JSON
        $response = json_decode($output, true);
        
        // Assert response was successful
        $this->assertTrue($response['success']);
        $this->assertCount(1, $response['data']);
        $this->assertEquals('Test Book 1', $response['data'][0]['title']);
    }
}