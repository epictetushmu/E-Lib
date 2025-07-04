# MVC Architecture in E-Lib Project

## Overview

The Model-View-Controller (MVC) architecture pattern separates an application into three main components:

1. **Models**: Responsible for data structure and business logic
2. **Views**: Responsible for the presentation layer
3. **Controllers**: Act as intermediaries between Models and Views

This document outlines how the E-Lib project implements the MVC pattern and provides guidelines for maintaining and extending the codebase.

## Core MVC Principles

### Models

Models represent the data structures and business rules of the application. They:
- Define data structures
- Implement business rules and validation
- Interact with databases or storage systems
- Are independent of the user interface
- Contain no references to Views or Controllers

### Views

Views are responsible for presenting data to users. They:
- Generate HTML output
- Display data provided by Controllers
- Handle UI-specific logic only
- Contain minimal or no business logic
- Do not directly interact with Models

### Controllers

Controllers coordinate interactions between Models and Views. They:
- Process HTTP requests
- Call appropriate Model methods to manipulate data
- Pass data to Views for rendering
- Handle application flow and routing
- Contain minimal business logic

## E-Lib MVC Implementation

### Directory Structure

```
E-Lib/
├── App/
│   ├── Models/          # Data structures and business logic
│   │   ├── Books.php
│   │   └── Users.php
│   ├── Views/           # UI templates and presentation logic
│   │   ├── Components/  # Reusable UI components
│   │   ├── Partials/    # Partial templates (header, footer, etc.)
│   │   └── *.php        # Page templates
│   ├── Controllers/     # Request handlers
│   │   ├── BookController.php
│   │   ├── PageController.php
│   │   └── UserController.php
│   ├── Services/        # Business logic layer between Controllers and Models
│   │   ├── BookService.php
│   │   └── UserService.php
│   ├── Database/        # Database abstraction layer
│   ├── Router/          # Request routing
│   ├── Middleware/      # Request/response middleware
│   └── Helpers/         # Utility classes
```

### Data Flow

1. **Request Handling**:
   - `BaseRouter` receives HTTP request
   - Request is routed to appropriate Controller method
   - Middleware can process request before/after Controller

2. **Data Processing**:
   - Controller calls Service methods
   - Service implements business logic
   - Service uses Models to interact with data storage
   - Models validate and store/retrieve data

3. **Response Generation**:
   - Controller receives data from Service
   - Controller passes data to View
   - View renders HTML/JSON response
   - Response is returned to client

## Best Practices

### Models

- Models should only contain data structure, validation, and storage logic
- Use type hinting and validation for all data
- Models should never reference Controllers or Views
- Models should not output HTML or generate UI elements

Example:
```php
class Book {
    // Properties
    private $id;
    private $title;
    
    // Data validation
    public function setTitle($title) {
        if (empty($title)) {
            throw new ValidationException("Title cannot be empty");
        }
        $this->title = $title;
    }
    
    // Database interaction
    public function save() {
        // Save to database
    }
}
```

### Views

- Views should only contain presentation logic
- Use template variables passed from Controllers
- Avoid direct database queries or business logic
- Use partial views and components for reusability

Example:
```php
<!-- book_detail.php -->
<div class="book-detail">
    <h1><?= htmlspecialchars($book['title']) ?></h1>
    <div class="author"><?= htmlspecialchars($book['author']) ?></div>
    <?php if ($userCanEdit): ?>
        <a href="/edit-book/<?= $book['id'] ?>">Edit</a>
    <?php endif; ?>
</div>
```

### Controllers

- Controllers should be thin and delegate business logic to Services
- Controllers should not contain complex queries or validation logic
- Use dependency injection for Services and other dependencies

Example:
```php
class BookController {
    private $bookService;
    
    public function __construct(BookService $bookService) {
        $this->bookService = $bookService;
    }
    
    public function viewBook($id) {
        try {
            $book = $this->bookService->getBookById($id);
            $userCanEdit = $this->bookService->userCanEditBook($id);
            
            $this->renderView('book_detail', [
                'book' => $book,
                'userCanEdit' => $userCanEdit
            ]);
        } catch (BookNotFoundException $e) {
            $this->renderError(404, "Book not found");
        }
    }
}
```

### Services

- Services implement business logic and coordinate between Models
- Services abstract complex operations from Controllers
- Services provide a clean API for Controllers

Example:
```php
class BookService {
    private $bookModel;
    private $userModel;
    
    public function __construct(Books $bookModel, Users $userModel) {
        $this->bookModel = $bookModel;
        $this->userModel = $userModel;
    }
    
    public function getBookById($id) {
        return $this->bookModel->getBookDetails($id);
    }
    
    public function userCanEditBook($bookId) {
        $currentUserId = SessionManager::getCurrentUserId();
        if (!$currentUserId) return false;
        
        $user = $this->userModel->getUserById($currentUserId);
        $book = $this->bookModel->getBookDetails($bookId);
        
        return $user['isAdmin'] || $book['ownerId'] === $currentUserId;
    }
}
```

## Common Anti-Patterns to Avoid

1. **Fat Controllers**: Controllers containing business logic or database queries
2. **Smart Views**: Views that contain business logic or direct database access
3. **Anemic Models**: Models that are just data containers without business logic
4. **Bypassing Layers**: Controllers directly accessing Models, bypassing Services
5. **Mixed Responsibilities**: Files that combine Model, View, and Controller logic

## Refactoring Guidelines

When refactoring existing code:

1. Identify business logic in Controllers and Views and move it to Services
2. Ensure Models handle data validation and storage logic
3. Make Views presentation-only
4. Use dependency injection for cleaner component relationships
5. Follow consistent naming conventions
6. Add proper documentation for all classes and methods

## Testing MVC Components

- **Models**: Unit tests for validation, business rules, and storage
- **Controllers**: Unit tests with mocked Services
- **Services**: Unit tests with mocked Models
- **Views**: Visual regression tests or snapshot tests
- **Integration Tests**: Test the full request/response cycle

## Conclusion

Adhering to MVC principles helps create maintainable, testable, and scalable code. By clearly separating concerns, the E-Lib project can evolve more easily with changing requirements while maintaining code quality.
