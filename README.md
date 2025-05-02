# E-Lib

Digital library management web application for Hellenic Mediterranean University (HMU)

## Overview

E-Lib is a PHP-based web application designed for managing and accessing a digital library collection. The application provides a comprehensive platform for storing, organizing, and reading PDF documents with features like user authentication, book searching, online reading, and administrative tools.

The application features a modular architecture with separate routes for web pages and API endpoints, allowing for a clean separation of concerns and easy extensibility.

## Key Features

- **Book Management**: Add, edit, search, and remove books from the library
- **Online PDF Reader**: Read documents directly in the browser
- **Book Collections**: Save books to personal reading lists
- **User Reviews**: Rate and review books
- **Admin Dashboard**: Comprehensive administrative tools
- **Responsive Design**: Works on desktop and mobile devices
- **MongoDB Integration**: Primary storage with JSON fallback
- **Security Features**: JWT authentication, secure file handling, and more

## Project Structure

The project is organized into several directories and files:

- **App/**: Contains the core application logic.
  - **Controllers/**: Application controllers that handle user requests
    - `BookController.php`: Manages book-related operations
    - `UserController.php`: Handles user authentication and profile management
    - `PageController.php`: Renders web pages
    - `DbController.php`: Database operations controller
  - **Models/**: Data models
    - `Books.php`: Book data model
    - `Users.php`: User data model
  - **Router/**: Houses routing classes
    - `ApiRouter.php`: Manages API routing
    - `PageRouter.php`: Handles web page routing
    - `BaseRouter.php`: Base class for routing functionality
  - **Services/**: Business logic services
    - `BookService.php`: Book-related functionality
    - `UserService.php`: User-related functionality
    - `CasService.php`: CAS authentication service
  - **Includes/**: Contains additional functionality
    - `JwtHelper.php`: JWT token generation and validation
    - `ResponseHandler.php`: API response formatting
    - `Environment.php`: Manages environment variables
    - `SessionManager.php`: Manages user sessions
  - **Middleware/**: Request processing middleware
    - `AuthMiddleware.php`: Authentication validation
    - `JwtAuthMiddleware.php`: JWT token validation
    - `LoggingMiddleware.php`: Request logging
  - **Helpers/**: Helper classes
    - `PdfHelper.php`: PDF thumbnail generation and processing
  - **Views/**: Contains the application's view templates
    - `Components/`: Reusable UI components
    - `Partials/`: Partial templates like headers and footers

- **public/**: Contains publicly accessible files
  - **assets/**: Static assets (JS, images, fonts, uploads)
  - **styles/**: CSS files for styling the application
  - `index.php`: The entry point of the application

- **storage/**: Application storage
  - **logs/**: Application logs
    - `php_errors.log`: PHP error logs
    - `requests.log`: Request logs

- **cache/**: Cache storage

- **certificates/**: SSL certificates and credentials
  - `mongodb-ca.pem`: MongoDB certificate

- **vendor/**: Composer dependencies

## Tech Stack

- **Backend**: PHP 8.2, Custom MVC framework
- **Database**: MongoDB with JSON fallback
- **Frontend**: HTML5, CSS3, JavaScript, Bootstrap 5
- **Authentication**: JWT tokens, Session-based auth, CAS integration
- **Containerization**: Docker, docker-compose
- **Web Server**: Apache
- **PDF Processing**: ImageMagick

## Getting Started

To get started with the E-Lib project:

### Environment Setup

1. **Create Environment File**:

   ```bash
   cp .env.example .env
   ```

   Edit the `.env` file with your specific configuration values.

2. **Required Environment Variables**:
   - `APP_ENV`: Application environment (development, production)
   - `API_BASE_URL`: Base URL for API endpoints
   - `CAS_SERVER_URL`: CAS server URL for authentication
   - `JWT_SECRET_KEY`: Secret key for JWT token generation and validation
   - `MONGO_URI`: MongoDB connection string
   - `MONGO_PASSWORD`: MongoDB password
   - `MONGO_CERT_FILE`: Path to MongoDB certificate file
   - `DATABASE_NAME`: Name of the MongoDB database

### Local Development with Docker

1. **Clone the Repository**:

   ```bash
   git clone https://github.com/epictetushmu/E-Lib.git
   cd E-Lib
   ```

2. **Setup Environment**:

   ```bash
   cp .env.example .env
   ```

3. **Build and Start the Docker Environment**:

   ```bash
   docker-compose up -d
   ```

4. **Access the Application**:
   Open your web browser and navigate to `http://localhost:8080`.

### Without Docker

1. **Requirements**:
   - PHP 8.2+
   - MongoDB 4.0+
   - Apache/Nginx
   - Composer

2. **Install Dependencies**:

   ```bash
   composer install
   ```

3. **Configure Web Server**:
   Point your web server to the `public` directory as the document root.

4. **Set Up File Permissions**:

   ```bash
   chmod -R 755 public/
   chmod -R 777 public/assets/uploads/
   chmod -R 777 storage/logs/
   ```

## Usage

### User Guide

1. **Registration**: Create an account using the Sign Up form
2. **Login**: Use your email and password to log in
3. **Finding Books**: Browse featured books or use the search function
4. **Reading Books**: Click on "Online Preview" to read in browser
5. **Downloading**: Use the Download button (when available)
6. **Saving Books**: Click "Save to Reading List" to bookmark a book
7. **Writing Reviews**: Rate and comment on books you've read

### Administrator Guide

1. **Admin Access**: Login with an admin account
2. **Adding Books**: Use the "Add Book" form to upload new books
3. **Managing Content**: Edit or delete books as needed
4. **Bulk Upload**: Use mass upload feature for multiple books
5. **Setting Permissions**: Control which books can be downloaded
6. **Featuring Books**: Mark books as featured to highlight them
7. **Monitoring System**: Check logs for errors or suspicious activity

## Documentation

Comprehensive documentation is available within the application at `/docs`. This includes:

- Technical implementation details
- API endpoints
- Database structure
- Authentication flows
- File management

## Support

Having trouble using the library? Our support team is here to help!

- **Email Support**:
[support@epictetuslibrary.org](mailto:support@epictetuslibrary.org)

- **Help Center**: Available in the application
- **Issue Tracker**: [GitHub Issues](https://github.com/epictetushmu/E-Lib/issues)

## Contributing
To contribute to this project, please follow these steps:

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## License

This project is licensed under the MIT License. See the LICENSE file for details.

## Credits

Developed by the Department of Electrical & Computer Engineering, Hellenic Mediterranean University.
