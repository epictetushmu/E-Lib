# E-Lib Developer Guide

## Introduction

This developer guide provides comprehensive information for working with the E-Lib project. It covers architecture, coding standards, and development workflows.

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [MVC Implementation](#mvc-implementation)
3. [Development Environment Setup](#development-environment-setup)
4. [Coding Standards](#coding-standards)
5. [Working with Docker](#working-with-docker)
6. [Database Integration](#database-integration)
7. [Authentication](#authentication)
8. [File Handling](#file-handling)
9. [API Reference](#api-reference)
10. [Testing](#testing)
11. [Troubleshooting](#troubleshooting)

## Architecture Overview

E-Lib is built using the Model-View-Controller (MVC) architectural pattern with additional layers for services, repositories, and middleware.

### High-Level Architecture

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│   Client    │────▶│   Router    │────▶│ Controllers │────▶│  Services   │────▶│   Models    │
└─────────────┘     └─────────────┘     └─────────────┘     └─────────────┘     └─────────────┘
                          │                    │                   │                   │
                          ▼                    ▼                   ▼                   ▼
                    ┌─────────────┐     ┌─────────────┐     ┌─────────────┐     ┌─────────────┐
                    │ Middleware  │     │    Views    │     │ Repositories │────▶│  Database   │
                    └─────────────┘     └─────────────┘     └─────────────┘     └─────────────┘
```

### Directory Structure

```
E-Lib/
├── App/                 # Main application code
│   ├── Controllers/     # Request handlers
│   ├── Models/          # Data models
│   ├── Views/           # UI templates
│   ├── Services/        # Business logic
│   ├── Repository/      # Data access
│   ├── Database/        # Database connections
│   ├── Router/          # Request routing
│   ├── Middleware/      # Request processors
│   ├── Helpers/         # Utility functions
│   └── Includes/        # Common components
├── public/              # Public-facing files
│   ├── index.php        # Application entry point
│   ├── assets/          # Static assets
│   └── uploads/         # User uploads
├── vendor/              # Dependencies (Composer)
├── docker/              # Docker configuration
├── tests/               # Test files
└── docs/                # Documentation
```

## MVC Implementation

The E-Lib project follows a strict MVC pattern with clear separation of concerns. For detailed information about the MVC architecture, see [MVC Architecture](./architecture/mvc-architecture.md).

### Models

Models represent data entities and handle data validation, storage, and retrieval.

Example model usage:

```php
// Creating a new Books model
$booksModel = new \App\Models\Books();

// Getting book data
$book = $booksModel->getBookDetails($id);

// Validating book data
$errors = $booksModel->validateBookData($bookData);
```

### Views

Views handle data presentation without containing business logic.

Example view structure:

```php
<!-- App/Views/book_detail.php -->
<!DOCTYPE html>
<html>
<head>
    <title><?= htmlspecialchars($book['title']) ?></title>
</head>
<body>
    <h1><?= htmlspecialchars($book['title']) ?></h1>
    <p>By <?= htmlspecialchars($book['author']) ?></p>
    <!-- Display book details -->
</body>
</html>
```

### Controllers

Controllers handle HTTP requests, coordinate with services, and return responses.

Example controller method:

```php
public function getBook($id) {
    try {
        $book = $this->bookService->getBookById($id);
        return $this->response->respond(true, $book);
    } catch (BookNotFoundException $e) {
        return $this->response->respond(false, 'Book not found', 404);
    }
}
```

### Services

Services implement business logic and coordinate between models.

Example service method:

```php
public function getBookById($id) {
    // Validation
    if (!preg_match('/^[0-9a-f]{24}$/', $id)) {
        throw new InvalidArgumentException('Invalid book ID format');
    }
    
    // Get data from model
    $book = $this->bookModel->getBookDetails($id);
    
    if (!$book) {
        throw new BookNotFoundException('Book not found');
    }
    
    // Process data if needed
    $book['is_downloadable'] = $this->isBookDownloadable($book);
    
    return $book;
}
```

## Development Environment Setup

### Prerequisites

- PHP 8.0 or higher
- Composer
- Docker and Docker Compose
- MongoDB (or use the Docker Compose setup)

### Local Setup

1. Clone the repository
   ```bash
   git clone https://github.com/yourusername/E-Lib.git
   cd E-Lib
   ```

2. Install dependencies
   ```bash
   composer install
   ```

3. Start the Docker environment
   ```bash
   docker-compose up -d
   ```

4. Access the application at `http://localhost:8080`

## Coding Standards

E-Lib follows PSR-12 coding standards. Key conventions include:

- Class names: PascalCase (e.g., `BookController`)
- Method names: camelCase (e.g., `getBookById`)
- Property names: camelCase (e.g., `$bookService`)
- Constants: UPPER_SNAKE_CASE (e.g., `DEFAULT_LIMIT`)

### Code Documentation

All classes, methods, and properties should be documented using PHPDoc:

```php
/**
 * Retrieves a book by its ID
 *
 * @param string $id The book ID
 * @return array The book data
 * @throws BookNotFoundException If the book is not found
 */
public function getBookById($id) {
    // Method implementation
}
```

## Working with Docker

E-Lib uses Docker for development and deployment. The Docker setup includes:

- PHP-FPM container
- Nginx web server
- MongoDB database
- Optional services (like Redis)

### Common Docker Commands

```bash
# Start the Docker environment
docker-compose up -d

# View container logs
docker-compose logs -f

# Stop the Docker environment
docker-compose down

# Rebuild containers after Dockerfile changes
docker-compose build --no-cache
```

## Database Integration

E-Lib supports both MongoDB and a file-based JSON database as a fallback.

### MongoDB

The primary database is MongoDB, accessed through the `MongoDatabase` class.

Example MongoDB query:

```php
// In a model or service
$filter = ['status' => 'public'];
$books = $this->db->find('Books', $filter);
```

### JSON Database Fallback

If MongoDB is unavailable, the system falls back to a JSON-based file database.

## Authentication

E-Lib supports multiple authentication methods:

1. Standard JWT-based authentication
2. CAS single sign-on integration

### JWT Authentication

JWT authentication is handled by the `JwtAuthMiddleware` class, which validates tokens before allowing access to protected routes.

### User Sessions

User sessions are managed through the `SessionManager` class.

## File Handling

The `FileHelper` class handles file uploads, processing, and storage.

Key features include:

- File upload validation
- Thumbnail generation
- Document conversion
- File storage management

## API Reference

The E-Lib API is organized into endpoints by resource type:

### Books API

- `GET /api/books` - List all books
- `GET /api/books/{id}` - Get a specific book
- `POST /api/books` - Create a new book
- `PUT /api/books/{id}` - Update a book
- `DELETE /api/books/{id}` - Delete a book

### Users API

- `GET /api/users/{id}` - Get user details
- `POST /api/users` - Create a new user
- `PUT /api/users/{id}` - Update user details

For a complete API reference, see the [API Documentation](../api-docs.md).

## Testing

E-Lib uses PHPUnit for testing.

### Running Tests

```bash
# Run all tests
./vendor/bin/phpunit

# Run a specific test file
./vendor/bin/phpunit tests/specs/BooksModelTest.php
```

### Test Types

1. **Unit Tests**: Test individual components in isolation
2. **Integration Tests**: Test interactions between components
3. **End-to-End Tests**: Test complete user flows

## Troubleshooting

### Common Issues

1. **Database Connection Problems**
   - Check MongoDB connection string in `.env` file
   - Verify MongoDB service is running

2. **File Upload Issues**
   - Check file permissions in upload directories
   - Verify PHP memory limit and upload size settings

3. **Docker Issues**
   - Check Docker logs with `docker-compose logs -f`
   - Ensure ports are not in use by other services

### Debugging

The application uses error logging to `storage/logs/php_errors.log` and request logging to `storage/logs/requests.log`.

Enable debug mode in the `.env` file for more verbose output:

```
APP_DEBUG=true
```

## Contributing

See the [Contributing Guide](../CONTRIBUTING.md) for details on how to contribute to the E-Lib project.
