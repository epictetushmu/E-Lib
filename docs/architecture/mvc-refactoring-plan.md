# E-Lib MVC Refactoring Plan

## Current Issues Identified

Based on the analysis of the E-Lib codebase, the following issues have been identified that need to be addressed to improve MVC pattern compliance:

1. **DbController is misplaced**: DbController is currently in the Controllers directory but acts more as a service or repository.
2. **Direct Model-View dependencies**: Some views may directly access models or have business logic embedded.
3. **Inconsistent service layer usage**: Not all controllers use service classes as intermediaries.
4. **Mixed responsibilities**: Some classes may contain logic that should be separated into different components.
5. **Lack of dependency injection**: Components are tightly coupled, making testing difficult.

## Refactoring Steps

### Phase 1: Restructure Database Layer

1. Move `DbController` to a new `Repository` directory
2. Rename `DbController` to `DatabaseRepository` to better reflect its role
3. Use dependency injection for database connections
4. Update all references to `DbController` in the codebase

### Phase 2: Strengthen Models

1. Ensure all Models contain only data structure and validation logic
2. Remove any direct database queries from Models where possible
3. Add proper validation for all data properties
4. Implement a consistent interface for all Models
5. Use type declarations and return type hints

### Phase 3: Implement Service Layer Consistently

1. Create Service classes for all Controllers if missing
2. Move business logic from Controllers to Services
3. Ensure Services use dependency injection for Models
4. Implement consistent error handling in Services

### Phase 4: Refactor Controllers

1. Make Controllers use Services exclusively for business logic
2. Remove any direct Model access from Controllers
3. Standardize Controller method signatures and responses
4. Use dependency injection for all Controller dependencies

### Phase 5: Clean Up Views

1. Remove any direct database access or business logic from Views
2. Implement a consistent template system
3. Use view helpers for common UI operations
4. Ensure all data passed to views is properly escaped

### Phase 6: Improve Routing

1. Refactor route definitions to be more declarative
2. Implement proper middleware for cross-cutting concerns
3. Standardize URL patterns and parameter handling

## Implementation Details

### Database Repository Refactoring

```php
<?php
namespace App\Repository;

use App\Includes\Environment;
use App\Database\DatabaseInterface;
use App\Database\JsonDatabase;
use App\Database\MongoDatabase;
use Exception;

class DatabaseRepository {
    private static $instance = null;
    private $database;
    private $databaseName;

    private function __construct($dbName = null) {
        $this->databaseName = $dbName ? $dbName : Environment::get('DB_NAME', 'LibraryDb');
        try {
            $this->database = new MongoDatabase();
        } catch (Exception $e) {
            error_log("MongoDB Connection Error: " . $e->getMessage());
            $this->database = new JsonDatabase();
        }
    }

    // Rest of the class...
}
```

### Model Refactoring Example (Books)

```php
<?php
namespace App\Models;

use App\Repository\DatabaseRepository;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use InvalidArgumentException;

class Books {
    private $db;
    private $collection = 'Books';

    public function __construct(DatabaseRepository $db = null) {
        $this->db = $db ?? DatabaseRepository::getInstance();
    }
    
    // Data validation method
    public function validateBookData(array $data): array {
        $errors = [];
        
        if (empty($data['title'])) {
            $errors['title'] = 'Title is required';
        }
        
        if (empty($data['author'])) {
            $errors['author'] = 'Author is required';
        }
        
        if (!empty($data['year']) && (!is_numeric($data['year']) || $data['year'] < 1000 || $data['year'] > date('Y'))) {
            $errors['year'] = 'Year must be a valid year';
        }
        
        return $errors;
    }
    
    // Rest of methods with proper validation and error handling
}
```

### Service Refactoring Example (BookService)

```php
<?php
namespace App\Services;

use App\Models\Books;
use App\Models\Users;
use App\Includes\SessionManager;
use MongoDB\BSON\UTCDateTime;
use InvalidArgumentException;

class BookService {
    private $bookModel;
    private $userModel;
    
    public function __construct(Books $bookModel = null, Users $userModel = null) {
        $this->bookModel = $bookModel ?? new Books();
        $this->userModel = $userModel ?? new Users();
    }
    
    public function createBook(array $bookData): array {
        // Validate input
        $errors = $this->bookModel->validateBookData($bookData);
        
        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode($errors));
        }
        
        // Enrich data
        $bookData['created_at'] = new UTCDateTime();
        $bookData['updated_at'] = new UTCDateTime();
        $bookData['user_id'] = SessionManager::getCurrentUserId();
        
        // Save to database
        return $this->bookModel->addBook($bookData);
    }
    
    // Rest of the methods...
}
```

### Controller Refactoring Example (BookController)

```php
<?php
namespace App\Controllers;

use App\Services\BookService;
use App\Includes\ResponseHandler;
use InvalidArgumentException;

class BookController {
    private $bookService;
    private $response;
    
    public function __construct(BookService $bookService = null, ResponseHandler $response = null) {
        $this->bookService = $bookService ?? new BookService();
        $this->response = $response ?? new ResponseHandler();
    }
    
    public function createBook() {
        try {
            $requestBody = file_get_contents('php://input');
            $data = json_decode($requestBody, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $data = $_POST;
            }
            
            $result = $this->bookService->createBook($data);
            return $this->response->respond(true, $result);
        } catch (InvalidArgumentException $e) {
            return $this->response->respond(false, json_decode($e->getMessage(), true), 400);
        } catch (\Exception $e) {
            error_log("Error creating book: " . $e->getMessage());
            return $this->response->respond(false, 'An error occurred while creating the book', 500);
        }
    }
    
    // Rest of the methods...
}
```

### View Refactoring Example

```php
<!-- Before -->
<?php
$books = (new App\Models\Books())->getAllBooks();
?>
<div class="books-list">
    <?php foreach ($books as $book): ?>
        <div class="book-item">
            <h3><?= $book['title'] ?></h3>
        </div>
    <?php endforeach; ?>
</div>

<!-- After -->
<div class="books-list">
    <?php if (empty($books)): ?>
        <div class="no-books">No books found</div>
    <?php else: ?>
        <?php foreach ($books as $book): ?>
            <div class="book-item">
                <h3><?= htmlspecialchars($book['title']) ?></h3>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
```

## Testing Strategy

Each refactored component should be tested thoroughly:

1. **Unit Tests**: For Models, Services, and Controllers
2. **Integration Tests**: For end-to-end functionality
3. **Manual Testing**: To ensure UI functionality is preserved

## Rollout Plan

The refactoring will be performed incrementally to minimize disruption:

1. Start with the Database Repository layer
2. Next, refactor Models to remove database dependencies
3. Implement Service layer changes
4. Update Controllers to use Services
5. Clean up Views last

Each phase will be tested thoroughly before moving to the next.

## Documentation

All changes will be documented in code comments and in the project documentation:

1. Update API documentation
2. Document new class relationships
3. Update developer guides with MVC best practices
4. Create diagrams illustrating the new architecture
