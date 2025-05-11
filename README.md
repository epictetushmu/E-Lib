# E-Lib

Digital library management web application for Hellenic Mediterranean University (HMU)

## Overview

E-Lib is a PHP-based web application designed for managing and accessing a digital library collection. The application provides a comprehensive platform for storing, organizing, and reading PDF documents with features like user authentication, book searching, online reading, and administrative tools.

The application features a modular architecture with separate routes for web pages and API endpoints, allowing for a clean separation of concerns and easy extensibility.

## Key Features

- **Book Management**: Add, edit, search, and remove books from the library
- **Online PDF Reader**: Read documents directly in the browser
- **Multi-format Support**: Support for PDF, EPUB, Word, PowerPoint, MOBI, AZW, and DJVU formats
- **Document Thumbnails**: Automatic thumbnail generation for document listings
- **Book Collections**: Save books to personal reading lists
- **User Reviews**: Rate and review books
- **Admin Dashboard**: Comprehensive administrative tools
- **Responsive Design**: Works on desktop and mobile devices
- **MongoDB Integration**: Primary storage with JSON fallback
- **Security Features**: JWT authentication, secure file handling, and more
- **Email System**: PHPMailer integration for email notifications and support
- **Support System**: Built-in help center with image upload support

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
    - `EmailService.php`: Email functionality for notifications and support
  - **Includes/**: Contains additional functionality
    - `JwtHelper.php`: JWT token generation and validation
    - `ResponseHandler.php`: API response formatting
    - `Environment.php`: Manages environment variables
    - `SessionManager.php`: Manages user sessions
  - **Middleware/**: Request processing middleware
    - `AuthMiddleware.php`: Authentication validation
    - `JwtAuthMiddleware.php`: JWT token validation
    - `LoggingMiddleware.php`: Request logging
    - `MiddlewareInterface.php`: Interface for middleware components
    - `MiddlewareManager.php`: Manages middleware execution
  - **Helpers/**: Helper classes
    - `FileHelper.php`: Document processing and thumbnail generation
  - **Views/**: Contains the application's view templates
    - `Components/`: Reusable UI components
      - `AddBook.php`: Book upload form
      - `SupportModal.php`: Help center interface with image upload support
      - `Docs.php`: Documentation component
    - `Partials/`: Partial templates like headers and footers

- **public/**: Contains publicly accessible files
  - **assets/**: Static assets (JS, images, fonts, uploads)
    - **uploads/**: User-uploaded content
      - **documents/**: Uploaded book files
      - **thumbnails/**: Document thumbnails and placeholder images
  - **styles/**: CSS files for styling the application
  - `index.php`: The entry point of the application

- **storage/**: Application storage
  - **logs/**: Application logs
    - `php_errors.log`: PHP error logs
    - `requests.log`: Request logs

- **cache/**: Cache storage for improved performance

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
- **Document Processing**:
  - ImageMagick for PDF thumbnail generation
  - LibreOffice for document conversion
  - ZipArchive for EPUB handling
- **Email**: PHPMailer with SMTP support
- **Dependencies**: Guzzle HTTP client, Firebase JWT

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
   - `MAIL_HOST`: SMTP server host for sending emails
   - `MAIL_PORT`: SMTP server port (typically 587 for TLS)
   - `MAIL_USERNAME`: SMTP account username
   - `MAIL_PASSWORD`: SMTP account password
   - `MAIL_ENCRYPTION`: Email encryption method (tls/ssl)
   - `MAIL_FROM_ADDRESS`: Default sender email address
   - `MAIL_FROM_NAME`: Default sender name
   - `SUPPORT_EMAIL`: Email address for receiving support requests

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

## Document Processing

E-Lib includes robust document handling capabilities:

### Supported File Types
- **PDF**: Full support with thumbnail generation and online reading
- **Word Documents**: .doc and .docx files with conversion to PDF for preview
- **PowerPoint**: .ppt and .pptx files with thumbnail generation
- **EPUB**: Electronic publication format with cover extraction
- **MOBI/AZW**: Kindle formats with basic support
- **DJVU**: Document format optimized for scanned documents

### Document Processing Features
- **Automatic Thumbnail Generation**: Creates thumbnails for documents using:
  - ImageMagick for PDF files
  - LibreOffice for Word document conversion
  - ZipArchive for EPUB cover extraction
  - Default placeholder images for unsupported formats
- **Format Detection**: Automatic detection of file types
- **Secure Storage**: Documents are stored with randomized filenames
- **Permission Control**: Admin-configurable download permissions

### Implementation Details
The document processing is handled primarily by the `FileHelper` class which:
- Detects file types based on extensions
- Extracts thumbnails using the appropriate method for each file type
- Handles file uploads with proper validation
- Manages file storage with optimized paths for both Docker and local environments

## Document Viewers

E-Lib includes specialized viewers for different document formats to provide a seamless reading experience directly in the browser:

### PDF Viewer
- Built with PDF.js for client-side rendering
- Features:
  - Lazy loading of pages for performance optimization
  - Page navigation controls
  - Zoom functionality
  - Responsive design that works on mobile devices
  - JWT authentication for secure document access
  - High-quality rendering with adjustable scale

### Word Document Viewer
- Built with Mammoth.js for DOCX parsing
- Features:
  - Renders Word documents directly in the browser
  - Preserves document formatting and styles
  - Fallback to download option when rendering is not possible
  - Compatible with .doc and .docx formats

### Generic Document Handler
- For other document formats (PowerPoint, EPUB, DJVU, etc.)
- Provides download options when browser viewing is not available
- Clear format-specific messaging and icons

### Implementation
The document viewing system is implemented through:

- **DocumentViewer.php**: A central component that:
  - Detects the document type
  - Loads the appropriate viewer component
  - Handles authentication and permissions
  - Manages the UI framework for all viewers

- **Format-specific viewers**:
  - PdfViewer.php: Handles PDF documents
  - WordViewer.php: Handles Word documents
  - Additional viewers can be added for other formats

### Document Security
- JWT token-based authentication for document access
- Server-side permission checks before serving documents
- Configurable download permissions that can be set per document
- Protection against direct URL access to document files

## Support System

E-Lib includes a comprehensive support system to assist users:

### Help Center Modal

The application features an interactive Help Center accessible from any page:

- **FAQ Section**: Common questions and answers for quick user reference
- **Documentation Links**: Direct access to detailed documentation pages
- **Rich Support Request Form**: Advanced form with the following features:
  - Rich text editing capabilities
  - Image embedding directly in support requests
  - Support for pasting images (clipboard integration)
  - File attachment functionality for screenshots or documents
  - Client-side validation for immediate user feedback
  - AJAX submission for a seamless user experience

### Implementation Details

The Support System is implemented through:

- **SupportModal.php**: A reusable component that can be included on any page
- **REST API Endpoint**: Handles support request submissions with attachments
- **EmailService Integration**: Routes support requests to the support team's email inbox
- **Image Processing**: Support for multiple image formats and automatic resizing

### User Support Flow

1. User accesses the Help Center through the support icon
2. User checks FAQ for immediate answers
3. If needed, user submits a detailed support request with optional images
4. Request is validated and sent to support staff
5. Confirmation is displayed to the user

## Email Configuration

The application uses PHPMailer to handle all email functionality. To configure email:

1. Set up your SMTP server information in the `.env` file:
   ```
   MAIL_HOST=smtp.example.com
   MAIL_PORT=587
   MAIL_USERNAME=your_username
   MAIL_PASSWORD=your_password
   MAIL_ENCRYPTION=tls
   MAIL_FROM_ADDRESS=support@epictetuslibrary.com
   MAIL_FROM_NAME="Epictetus Library"
   SUPPORT_EMAIL=support@epictetuslibrary.org
   ```

2. Email functionality is available through the `EmailService` class which provides:
   - Support request emails
   - General email sending capability
   - HTML-formatted emails

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
