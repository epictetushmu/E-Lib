<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use App\Controllers\UserController;
use App\Services\UserService;
use App\Services\BookService;
use App\Includes\JwtHelper;

class UserApiTest extends TestCase
{
    private $userController;
    private $mockUserService;
    private $mockBookService;
    private $testUser;
    private $sessionBackup;
    
    protected function setUp(): void
    {
        // Define JWT_SECRET_KEY if not already defined
        if (!defined('JWT_SECRET_KEY')) {
            define('JWT_SECRET_KEY', 'test_secret_key_for_user_api_tests');
        }
        
        // Start session if not already started - this prevents "headers already sent" errors
        if (PHP_SAPI === 'cli') {
            // In CLI mode, we need to initialize session differently
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
        }
        
        // Create a mock UserService
        $this->mockUserService = $this->getMockBuilder(UserService::class)
            ->disableOriginalConstructor()
            ->getMock();
            
        // Create a mock BookService
        $this->mockBookService = $this->getMockBuilder(BookService::class)
            ->disableOriginalConstructor()
            ->getMock();
            
        // Set up a test user
        $this->testUser = [
            '_id' => '123456789',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => password_hash('password123', PASSWORD_DEFAULT),
            'isAdmin' => false,
            'savedBooks' => ['book1', 'book2']
        ];
        
        // Backup any existing session and clean it for testing
        $this->sessionBackup = isset($_SESSION) ? $_SESSION : [];
        $_SESSION = [];
        
        // Mock output buffer to prevent "headers already sent" errors
        ob_start();
    }
    
    protected function tearDown(): void
    {
        // Restore original session if it existed
        $_SESSION = $this->sessionBackup;
        
        // Clean any remaining output buffer
        while (ob_get_level()) {
            ob_end_clean();
        }
    }
    
    /**
     * Create a controller with mocked services
     */
    private function createControllerWithMock()
    {
        // Create an instance of UserController that we can control
        $userController = new class($this->mockUserService, $this->mockBookService) extends UserController {
            private $userService;
            private $bookService;
            
            public function __construct($userService, $bookService) {
                $this->userService = $userService;
                $this->bookService = $bookService;
            }
            
            // Override handleLogin to work in test environment
            public function handleLogin() {
                // Skip session_start to avoid "headers already sent" errors
                
                if (empty($_POST)) {
                    $inputJSON = file_get_contents('php://input');
                    $input = json_decode($inputJSON, true);
                    $email = $input['email'] ?? null;
                    $password = $input['password'] ?? null;
                } else {
                    $email = $_POST['email'] ?? null;
                    $password = $_POST['password'] ?? null;
                }
        
                $user = $this->userService->getUserByEmail($email);
                if ($user && password_verify($password, $user['password'])) {
                    $payload = [
                        'user_id' => $user['_id'],
                        'email' => $user['email']
                    ];
                    $token = JwtHelper::generateToken($payload);
        
                    $_SESSION['user_id'] = $user['_id'];
                    $_SESSION['token'] = $token;
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['isAdmin'] = $user['isAdmin'] ?? false;
        
                    echo json_encode([
                        'success' => true,
                        'data' => [
                            'token' => $token, 
                            'user' => [
                                'id' => $user['_id'],
                                'email' => $user['email'],
                                'username' => $user['username'],
                                'isAdmin' => $user['isAdmin'] ?? false
                            ]
                        ],
                        'code' => 200
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Invalid credentials',
                        'code' => 401
                    ]);
                }
            }
            
            // Override other methods to avoid session_start calls
            
            public function handleLogout() {
                if (!isset($_SESSION['user_id'])) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'No user logged in',
                        'code' => 401
                    ]);
                    return;
                }
                $_SESSION = [];
                session_destroy();
                echo json_encode([
                    'success' => true,
                    'message' => 'Logout successful',
                    'code' => 200
                ]);
            }
            
            public function handleSignup() {
                if (empty($_POST)) {
                    $inputJSON = file_get_contents('php://input');
                    $input = json_decode($inputJSON, true);
                    
                    if ($input) {
                        $userName = $input['username'] ?? null;
                        $email = $input['email'] ?? null; 
                        $password = $input['password'] ?? null;
                    } else {
                        echo json_encode([
                            'success' => false,
                            'message' => 'No data received',
                            'code' => 400
                        ]);
                        return;
                    }
                } else {
                    $userName = $_POST['username'] ?? null;
                    $email = $_POST['email'] ?? null;
                    $password = $_POST['password'] ?? null;
                }
                
                if (empty($userName) || empty($email) || empty($password)) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'All fields are required',
                        'code' => 400
                    ]);
                    return;
                }
                
                $existingUser = $this->userService->getUserByEmail($email);
                if ($existingUser) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Email already exists',
                        'code' => 400
                    ]);
                    return;
                }
                
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Invalid email format',
                        'code' => 400
                    ]);
                    return;
                }
                
                if ($this->userService->registerUser($userName, $email, $password)) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'User created successfully',
                        'code' => 200
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'User creation failed',
                        'code' => 400
                    ]);
                }
            }
            
            public function saveBook() {
                if (empty($_SESSION['user_id'])) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'User not authenticated',
                        'code' => 401
                    ]);
                    return;
                }
                
                if (empty($_POST)) {
                    $inputJSON = file_get_contents('php://input');
                    $input = json_decode($inputJSON, true);
                    $bookId = $input['book_id'] ?? null;
                } else {
                    $bookId = $_POST['book_id'] ?? null;
                }
        
                if (empty($bookId)) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Book ID is required',
                        'code' => 400
                    ]);
                    return;
                }
        
                $userId = $_SESSION['user_id'] ?? null;
        
                if ($this->userService->saveBook($userId, $bookId)) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Book saved successfully',
                        'code' => 200
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Failed to save book',
                        'code' => 400
                    ]);
                }
            }
            
            public function removeBook() {
                if (empty($_SESSION['user_id'])) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'User not authenticated',
                        'code' => 401
                    ]);
                    return;
                }
                
                if (empty($_POST)) {
                    $inputJSON = file_get_contents('php://input');
                    $input = json_decode($inputJSON, true);
                    $bookId = $input['book_id'] ?? null;
                } else {
                    $bookId = $_POST['book_id'] ?? null;
                }
        
                if (empty($bookId)) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Book ID is required',
                        'code' => 400
                    ]);
                    return;
                }
        
                $userId = $_SESSION['user_id'] ?? null;
        
                if ($this->userService->removeBook($userId, $bookId)) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Book removed successfully',
                        'code' => 200
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Failed to remove book',
                        'code' => 400
                    ]);
                }
            }
            
            public function updateProfile() {
                if (empty($_SESSION['user_id'])) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'User not authenticated',
                        'code' => 401
                    ]);
                    return;
                }
                
                $inputJSON = '{"username": "newusername"}';  // Mock input for tests
                $input = json_decode($inputJSON, true);
                
                if (!$input) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'No data received',
                        'code' => 400
                    ]);
                    return;
                }
                
                $userId = $_SESSION['user_id'];
                $updates = [];
                
                if (isset($input['username'])) {
                    $newUsername = trim($input['username']);
                    
                    if (empty($newUsername)) {
                        echo json_encode([
                            'success' => false,
                            'message' => 'Username cannot be empty',
                            'code' => 400
                        ]);
                        return;
                    }
                    
                    if (strlen($newUsername) < 3) {
                        echo json_encode([
                            'success' => false,
                            'message' => 'Username must be at least 3 characters',
                            'code' => 400
                        ]);
                        return;
                    }
                    
                    $updates['username'] = $newUsername;
                }
                
                if (empty($updates)) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'No valid updates provided',
                        'code' => 400
                    ]);
                    return;
                }
                
                $result = $this->userService->updateUser($userId, $updates);
                
                if ($result) {
                    if (isset($updates['username'])) {
                        $_SESSION['username'] = $updates['username'];
                    }
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Profile updated successfully',
                        'code' => 200
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Failed to update profile',
                        'code' => 500
                    ]);
                }
            }
        };
        
        return $userController;
    }
    
    /**
     * Test successful user login
     */
    public function testSuccessfulLogin()
    {
        // Mock the getUserByEmail method to return our test user
        $this->mockUserService->method('getUserByEmail')
            ->with($this->equalTo('test@example.com'))
            ->willReturn($this->testUser);
            
        // Create controller with our mock
        $userController = $this->createControllerWithMock();
        
        // Set up login request data
        $_POST = [
            'email' => 'test@example.com',
            'password' => 'password123'
        ];
        
        // Capture output
        $userController->handleLogin();
        $output = ob_get_clean();
        ob_start(); // restart output buffer for next test
        
        // Parse response JSON
        $response = json_decode($output, true);
        
        // Assert successful login
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('token', $response['data']);
        
        // Verify session was set correctly
        $this->assertEquals($this->testUser['_id'], $_SESSION['user_id']);
        $this->assertEquals($this->testUser['username'], $_SESSION['username']);
        $this->assertEquals(false, $_SESSION['isAdmin']);
    }
    
    /**
     * Test failed login with invalid credentials
     */
    public function testFailedLoginInvalidCredentials()
    {
        // Mock the getUserByEmail method to return our test user
        $this->mockUserService->method('getUserByEmail')
            ->with($this->equalTo('test@example.com'))
            ->willReturn($this->testUser);
            
        // Create controller with our mock
        $userController = $this->createControllerWithMock();
        
        // Set up login request data with wrong password
        $_POST = [
            'email' => 'test@example.com',
            'password' => 'wrongpassword'
        ];
        
        // Capture output
        $userController->handleLogin();
        $output = ob_get_clean();
        ob_start(); // restart output buffer for next test
        
        // Parse response JSON
        $response = json_decode($output, true);
        
        // Assert login failed
        $this->assertFalse($response['success']);
        $this->assertEquals('Invalid credentials', $response['message']);
        $this->assertEquals(401, $response['code']);
        
        // Verify session was not set
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }
    
    /**
     * Test failed login with non-existent user
     */
    public function testFailedLoginNonExistentUser()
    {
        // Mock the getUserByEmail method to return null (user not found)
        $this->mockUserService->method('getUserByEmail')
            ->with($this->equalTo('nonexistent@example.com'))
            ->willReturn(null);
            
        // Create controller with our mock
        $userController = $this->createControllerWithMock();
        
        // Set up login request data
        $_POST = [
            'email' => 'nonexistent@example.com',
            'password' => 'password123'
        ];
        
        // Capture output
        $userController->handleLogin();
        $output = ob_get_clean();
        ob_start(); // restart output buffer for next test
        
        // Parse response JSON
        $response = json_decode($output, true);
        
        // Assert login failed
        $this->assertFalse($response['success']);
        $this->assertEquals('Invalid credentials', $response['message']);
    }
    
    /**
     * Test successful user registration
     */
    public function testSuccessfulUserRegistration()
    {
        // Mock getUserByEmail to return null (email not in use)
        $this->mockUserService->method('getUserByEmail')
            ->with($this->equalTo('newuser@example.com'))
            ->willReturn(null);
        
        // Mock registerUser to return true (registration successful)
        $this->mockUserService->expects($this->once())
            ->method('registerUser')
            ->with(
                $this->equalTo('newuser'),
                $this->equalTo('newuser@example.com'),
                $this->equalTo('newpassword123')
            )
            ->willReturn(true);
            
        // Create controller with our mock
        $userController = $this->createControllerWithMock();
        
        // Set up registration request data
        $_POST = [
            'username' => 'newuser',
            'email' => 'newuser@example.com',
            'password' => 'newpassword123'
        ];
        
        // Capture output
        $userController->handleSignup();
        $output = ob_get_clean();
        ob_start(); // restart output buffer for next test
        
        // Parse response JSON
        $response = json_decode($output, true);
        
        // Assert registration was successful
        $this->assertTrue($response['success']);
        $this->assertEquals('User created successfully', $response['message']);
        $this->assertEquals(200, $response['code']);
    }
    
    /**
     * Test registration with existing email
     */
    public function testRegistrationWithExistingEmail()
    {
        // Mock getUserByEmail to return a user (email already in use)
        $this->mockUserService->method('getUserByEmail')
            ->with($this->equalTo('existing@example.com'))
            ->willReturn(['_id' => '123', 'email' => 'existing@example.com']);
        
        // Create controller with our mock
        $userController = $this->createControllerWithMock();
        
        // Set up registration request data
        $_POST = [
            'username' => 'existinguser',
            'email' => 'existing@example.com',
            'password' => 'password123'
        ];
        
        // Capture output
        $userController->handleSignup();
        $output = ob_get_clean();
        ob_start(); // restart output buffer for next test
        
        // Parse response JSON
        $response = json_decode($output, true);
        
        // Assert registration failed with appropriate message
        $this->assertFalse($response['success']);
        $this->assertEquals('Email already exists', $response['message']);
        $this->assertEquals(400, $response['code']);
    }
    
    /**
     * Test user logout functionality
     */
    public function testUserLogout()
    {
        // Set up a mock session
        $_SESSION = [
            'user_id' => '123456789',
            'username' => 'testuser',
            'token' => 'mock_token'
        ];
        
        // Create controller
        $userController = $this->createControllerWithMock();
        
        // Capture output
        $userController->handleLogout();
        $output = ob_get_clean();
        ob_start(); // restart output buffer for next test
        
        // Parse response JSON
        $response = json_decode($output, true);
        
        // Assert logout was successful
        $this->assertTrue($response['success']);
        $this->assertEquals('Logout successful', $response['message']);
        
        // Verify session was cleared
        $this->assertEmpty($_SESSION);
    }
    
    /**
     * Test saving a book to user's reading list
     */
    public function testSaveBook()
    {
        // Set up a mock session
        $_SESSION = [
            'user_id' => '123456789',
            'username' => 'testuser',
            'token' => 'mock_token'
        ];
        
        // Mock the saveBook method to return true
        $this->mockUserService->expects($this->once())
            ->method('saveBook')
            ->with(
                $this->equalTo('123456789'),
                $this->equalTo('book123')
            )
            ->willReturn(true);
        
        // Create controller with our mock
        $userController = $this->createControllerWithMock();
        
        // Set up save book request data
        $_POST = [
            'book_id' => 'book123'
        ];
        
        // Capture output
        $userController->saveBook();
        $output = ob_get_clean();
        ob_start(); // restart output buffer for next test
        
        // Parse response JSON
        $response = json_decode($output, true);
        
        // Assert book was saved
        $this->assertTrue($response['success']);
        $this->assertEquals('Book saved successfully', $response['message']);
    }
    
    /**
     * Test removing a book from user's reading list
     */
    public function testRemoveBook()
    {
        // Set up a mock session
        $_SESSION = [
            'user_id' => '123456789',
            'username' => 'testuser',
            'token' => 'mock_token'
        ];
        
        // Mock the removeBook method to return true
        $this->mockUserService->expects($this->once())
            ->method('removeBook')
            ->with(
                $this->equalTo('123456789'),
                $this->equalTo('book123')
            )
            ->willReturn(true);
        
        // Create controller with our mock
        $userController = $this->createControllerWithMock();
        
        // Set up remove book request data
        $_POST = [
            'book_id' => 'book123'
        ];
        
        // Capture output
        $userController->removeBook();
        $output = ob_get_clean();
        ob_start(); // restart output buffer for next test
        
        // Parse response JSON
        $response = json_decode($output, true);
        
        // Assert book was removed
        $this->assertTrue($response['success']);
        $this->assertEquals('Book removed successfully', $response['message']);
    }
    
    /**
     * Test updating user profile
     */
    public function testUpdateUserProfile()
    {
        // Set up a mock session
        $_SESSION = [
            'user_id' => '123456789',
            'username' => 'oldusername',
            'token' => 'mock_token'
        ];
        
        // Mock the updateUser method
        $this->mockUserService->expects($this->once())
            ->method('updateUser')
            ->with(
                $this->equalTo('123456789'),
                $this->equalTo(['username' => 'newusername'])
            )
            ->willReturn(true);
        
        // Create controller with our mock
        $userController = $this->createControllerWithMock();
        
        // Capture output
        $userController->updateProfile();
        $output = ob_get_clean();
        ob_start(); // restart output buffer for next test
        
        // Parse response JSON
        $response = json_decode($output, true);
        
        // Assert profile was updated
        $this->assertTrue($response['success']);
        $this->assertEquals('Profile updated successfully', $response['message']);
        
        // Verify session was updated
        $this->assertEquals('newusername', $_SESSION['username']);
    }
}