# E-Lib

Digital library management web application for Hellenic Mediterranean University (HMU)

## Overview

E-Lib is a PHP-based web application designed for managing and accessing a digital library collection. The application provides a comprehensive platform for storing, organizing, and reading PDF documents with features like user authentication, book searching, online reading, and administrative tools.

The application features a modular architecture with separate routes for web pages and API endpoints, allowing for a clean separation of concerns and easy extensibility.

## 🚀 Installation Guide

### Prerequisites

Before you begin, ensure you have the following installed on your system:

- **PHP 8.0 or higher** with the following extensions:
  - OpenSSL
  - MongoDB
  - JSON
  - Fileinfo
  - Ctype
  - PDO (if using SQL database in the future)
- **Composer** (PHP package manager)
- **MongoDB Server** (version 4.4 or later)
- **Web Server** (Apache/Nginx with mod_rewrite enabled)
- **Node.js** (for frontend assets compilation)

### Step 1: Clone the Repository

```bash
git clone https://github.com/yourusername/e-lib.git
cd e-lib
```

### Step 2: Install Dependencies

1. Install PHP dependencies:
   ```bash
   composer install
   ```

2. Install frontend dependencies (if applicable):
   ```bash
   npm install
   npm run build
   ```

### Step 3: Configure Environment

1. Copy the example environment file:
   ```bash
   cp .env.example .env
   ```

2. Generate application key:
   ```bash
   php -r "file_put_contents('.env', str_replace('your_jwt_secret_key_here', bin2hex(random_bytes(32)), file_get_contents('.env')));"
   ```

3. Edit the `.env` file with your configuration:
   ```env
   # Database (the only DB setting the app reads; the database name is always LibraryDb)
   MONGO_URI='mongodb://root:example@mongo:27017/?directConnection=true'
   
   # JWT Configuration
   JWT_SECRET_KEY=your_secure_jwt_secret
   
   # Application URL
   APP_URL=http://localhost:8000
   
   # Email Configuration (for notifications and support)
   MAIL_MAILER=smtp
   MAIL_HOST=smtp.mailtrap.io
   MAIL_PORT=2525
   MAIL_USERNAME=your_username
   MAIL_PASSWORD=your_password
   MAIL_ENCRYPTION=tls
   MAIL_FROM_ADDRESS=hello@example.com
   MAIL_FROM_NAME="${APP_NAME}"
   ```

### Step 4: Database Setup

1. Ensure MongoDB is running
2. Import sample data (if available):
   ```bash
   mongorestore --db e_lib database/dumps/e_lib
   ```
   Or create the database manually.


### Step 5: Run the Application

#### For Development:
```bash
php -S localhost:8000 -t public
```

#### For Production:
1. Configure your web server (Apache/Nginx) to point to the `public` directory
2. Set up proper SSL certificates
3. Configure your web server's document root to `/path/to/e-lib/public`

### Step 6: Access the Application

Open your browser and visit:
- Frontend: `http://localhost:8000`
- Admin Panel: `http://localhost:8000/admin` (if applicable)

Default admin credentials (if applicable):
- Email: admin@example.com
- Password: password

## 🔧 Troubleshooting

- **MongoDB Connection Issues**:
  - Ensure MongoDB service is running
  - Check connection string in `.env`
  - Verify database credentials and permissions

- **File Permissions**:
  - Ensure storage and bootstrap/cache directories are writable
  - Run `composer dump-autoload` if class not found errors occur

- **Environment Variables**:
  - Clear configuration cache: `php artisan config:clear`
  - Ensure `.env` file exists and is properly formatted

## 🔄 Updating

To update to the latest version:

```bash
git pull origin main
composer install
php artisan migrate
npm install
npm run build
```

## Key Features

- **Book Management**: Add, edit, search, and remove books from the library
- **Online PDF Reader**: Read documents directly in the browser
- **PDF Uploads**: Upload validation and thumbnail generation are PDF-only; other formats are rejected
- **Document Thumbnails**: Automatic thumbnail generation for PDF listings
- **Book Collections**: Save books to personal reading lists
- **User Reviews**: Rate and review books
- **Admin Dashboard**: Comprehensive administrative tools
- **Responsive Design**: Works on desktop and mobile devices
- **MongoDB Integration**: MongoDB is the sole datastore (no fallback)
- **Security Features**: JWT authentication, secure file handling, and more
- **Email System**: PHPMailer integration for email notifications and support
- **Support System**: Built-in help center with image upload support

## Project Structure

The project is organized into several directories and files:

- **App/**: Contains the core application logic.
  - **Controllers/**: Application controllers that handle user requests
    - `BookController.php`: Manages book-related operations
    - `UserController.php`: Handles user authentication and profile management
    - `DbController.php`: Database operations controller
  - **Models/**: Data models
    - `Books.php`: Book data model
    - `Users.php`: User data model
  - **Router/**: Houses routing classes
    - `ApiRouter.php`: Manages API routing
    - `PageRouter.php`: Serves the built SPA shell (`public/dist/index.html`) and the CAS SSO callback
    - `BaseRouter.php`: Base class for routing functionality
  - **Services/**: Business logic services
    - `BookService.php`: Book-related functionality
    - `UserService.php`: User-related functionality
    - `CasService.php`: CAS authentication service (validates a ticket, bridges to a JWT)
    - `EmailService.php`: Email functionality for notifications and support
  - **Includes/**: Contains additional functionality
    - `JwtHelper.php`: JWT token generation and validation
    - `ResponseHandler.php`: API response formatting
    - `Environment.php`: Manages environment variables
    - `SessionManager.php`: Manages user sessions
  - **Middleware/**: Request processing middleware
    - `JwtAuthMiddleware.php`: JWT token validation (the sole auth gate for protected API routes)
    - `LoggingMiddleware.php`: Request logging
    - `MiddlewareInterface.php`: Interface for middleware components
    - `MiddlewareManager.php`: Manages middleware execution
  - **Helpers/**: Helper classes
    - `FileHelper.php`: Document processing and thumbnail generation

- **frontend/**: Vue 3 + Vite + TypeScript SPA (the entire UI). `npm run build` outputs to `public/dist/`, which `PageRouter` serves. See `docs/architecture/vue-spa-migration-plan.md`.

- **public/**: Contains publicly accessible files
  - **assets/**: Static assets (images, fonts, uploads)
    - **uploads/**: User-uploaded content
      - **documents/**: Uploaded book files
      - **thumbnails/**: Document thumbnails and placeholder images
  - **dist/**: Built frontend (generated by `npm run build`; not committed)
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
- **Database**: MongoDB (sole datastore, no fallback)
- **Frontend**: HTML5, CSS3, JavaScript, Bootstrap 5
- **Authentication**: JWT tokens, Session-based auth, CAS integration
- **Containerization**: Docker, docker-compose
- **Web Server**: Apache
- **Document Processing**:
  - ImageMagick (or `pdftoppm`) for PDF thumbnail generation
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

`docker-compose.yml` is the production stack; `docker-compose.dev.yml` layers dev overrides on top
(bind-mounted code, debug on, a throwaway local MongoDB, app published on port 8081).

1. **Setup Environment**:

   ```bash
   cp .env.example .env   # keeps COMPOSE_FILE=docker-compose.yml:docker-compose.dev.yml, so compose picks up the dev overrides
   cd frontend && npm install && npm run build && cd ..   # the bind-mount hides the image's own SPA build
   ```

2. **Build and Start**:

   ```bash
   docker compose up -d --build
   ```

3. **Access the Application**: `http://localhost:8081` (or `https://localhost` through Caddy).

To test against the throwaway database instead of Atlas, set `MONGO_URI=mongodb://root:example@mongo:27017` in `.env`.

### Production Deployment

Production runs the same images with `docker-compose.yml` alone (no dev overrides): Caddy terminates
HTTPS and proxies to the app; uploaded books and thumbnails live in the `uploads` volume, so rebuilds
and redeploys never touch them.

1. On the server: install Docker with the compose plugin.
2. Locally: `cp .env.example .env.production`, fill in production values (`APP_URL`, `DOMAIN`,
   `MONGO_URI`, `JWT_SECRET_KEY`, ...) and **remove the `COMPOSE_FILE` line**.
3. Deploy (syncs the code, builds, restarts, checks the app answers):

   ```bash
   DEPLOY_HOST=user@your-server DEPLOY_DIR=/srv/e-lib ./deploy.sh
   ```

4. **One-time: import existing uploads.** Book records in MongoDB point at files under
   `/assets/uploads/...`, so copy your existing `public/assets/uploads/` into the volume once:

   ```bash
   rsync -az public/assets/uploads/ user@your-server:/tmp/elib-uploads/
   ssh user@your-server 'cd /srv/e-lib \
     && docker compose cp /tmp/elib-uploads/. app:/var/www/html/public/assets/uploads/ \
     && docker compose exec -T app chown -R www-data:www-data /var/www/html/public/assets/uploads \
     && rm -rf /tmp/elib-uploads'
   ```

### Content Search ("search inside books")

Readers can search the *text* of every PDF, in Greek or English, by words or by meaning; results link
straight to the matching page in the reader. Two extra containers power it, both running on your own
server — no external AI service, no API keys, no per-request cost:

- `embedder` — Ollama serving the `bge-m3` model (~2GB RAM; downloads 1.2GB on first start).
- `worker` — the app image running `scripts/index-worker.php`, which indexes books in the background.
  Existing books are indexed automatically; the admin dashboard shows books that aren't searchable yet.

**Production:** after the first deploy, create the Atlas Search indexes once (they need Atlas; the free
M0 tier works):

```bash
ssh user@your-server 'cd /srv/e-lib && docker compose exec -T app php scripts/db-setup.php'
```

and make sure the server's `.env` (`.env.production`) has `COMPOSE_PROFILES=search`, or the indexing
worker and the model never start:

```bash
COMPOSE_PROFILES=search
```

Server sizing: ~4GB RAM total (app + LibreOffice + embedder). Indexing a 300-page book takes a few minutes
on CPU; to index faster, run more workers: `docker compose up -d --scale worker=3`.

**On/off:** the two containers only run with `COMPOSE_PROFILES=search` in `.env`, then
`docker compose up -d`. Set it on the server. Leave it empty on a laptop that can't spare ~2GB RAM: the
app reads the same setting, so with it off the "Inside books" search is hidden and everything else works.
To turn them off again: empty the variable and `docker compose stop worker embedder`.

**Local:** before turning it on, point `MONGO_URI` at the local `mongo` container (see above) — the
worker writes search data to whichever database `MONGO_URI` names — then run
`php scripts/db-setup.php` against it once.



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
2. **Adding Books**: Use the "Upload PDF" page to upload new books, one at a time or several at once
3. **Managing Content**: Edit or delete books as needed
4. **Bulk Upload**: The same "Upload PDF" page accepts multiple files at once
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

E-Lib currently handles PDF documents only:

### Supported File Types
- **PDF**: Full support with thumbnail generation and online reading

Uploads with any other extension are rejected at validation time (`FileHelper::$supportedTypes = ['pdf']`). Word, PowerPoint, EPUB, MOBI/AZW, and DJVU are not currently supported — there is no conversion or cover-extraction path for them in the codebase.

### Document Processing Features
- **Automatic Thumbnail Generation**: Creates thumbnails for PDF files using ImageMagick (`Imagick`) or `pdftoppm` (Poppler) as a fallback
- **Format Detection**: Extension-based detection, restricted to `.pdf`
- **Secure Storage**: Documents are stored with randomized filenames
- **Permission Control**: Admin-configurable download permissions

### Implementation Details
The document processing is handled primarily by the `FileHelper` class which:
- Detects file type based on extension (PDF only)
- Extracts thumbnails via ImageMagick or `pdftoppm`
- Handles file uploads with extension-allowlist validation
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

### Implementation
The document viewing system is implemented through:

- **DocumentViewer.php**: A central component that:
  - Loads the PDF viewer component
  - Handles authentication and permissions
  - Manages the UI framework for the viewer

- **Format-specific viewers**:
  - PdfViewer.php: Handles PDF documents
  - Additional viewers could be added for other formats in the future, but none exist today

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
