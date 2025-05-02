<!-- Documentation Component -->
<div class="container my-5">
    <h1 class="mb-4 text-center">E-Lib Documentation</h1>
    
    <section class="mb-5">
        <h2>About the Application</h2>
        <p>
            E-Lib is a digital library application designed to store, manage, and serve electronic books (PDFs) 
            to authorized users. The application provides a comprehensive set of features for both users and 
            administrators to interact with the digital library content.
        </p>
    </section>

    <section class="mb-5">
        <h2>Main Features</h2>
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header bg-primary text-white">
                        <i class="fas fa-user me-2"></i> User Features
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item"><i class="fas fa-search me-2"></i> Search books by title, author, or category</li>
                            <li class="list-group-item"><i class="fas fa-eye me-2"></i> Preview book contents online</li>
                            <li class="list-group-item"><i class="fas fa-download me-2"></i> Download books (when enabled)</li>
                            <li class="list-group-item"><i class="fas fa-bookmark me-2"></i> Save books to a personal reading list</li>
                            <li class="list-group-item"><i class="fas fa-star me-2"></i> Rate and review books</li>
                            <li class="list-group-item"><i class="fas fa-share-alt me-2"></i> Share book links</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header bg-success text-white">
                        <i class="fas fa-cog me-2"></i> Admin Features
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item"><i class="fas fa-book me-2"></i> Add and manage books</li>
                            <li class="list-group-item"><i class="fas fa-edit me-2"></i> Edit book metadata</li>
                            <li class="list-group-item"><i class="fas fa-trash-alt me-2"></i> Remove books from the library</li>
                            <li class="list-group-item"><i class="fas fa-toggle-on me-2"></i> Control book download permissions</li>
                            <li class="list-group-item"><i class="fas fa-chart-bar me-2"></i> View system logs</li>
                            <li class="list-group-item"><i class="fas fa-cloud-upload-alt me-2"></i> Mass upload of books</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="mb-5">
        <h2>Technical Implementation</h2>
        <div class="accordion" id="technicalAccordion">
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingOne">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                        Architecture
                    </button>
                </h2>
                <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#technicalAccordion">
                    <div class="accordion-body">
                        <p>E-Lib follows an MVC (Model-View-Controller) architecture pattern:</p>
                        <ul>
                            <li><strong>Models:</strong> Handle data manipulation and storage (Books, Users)</li>
                            <li><strong>Views:</strong> Present information to users</li>
                            <li><strong>Controllers:</strong> Process incoming requests and responses</li>
                            <li><strong>Services:</strong> Contain business logic and act as intermediaries between controllers and models</li>
                            <li><strong>Middleware:</strong> Process requests before they reach controllers</li>
                            <li><strong>Routers:</strong> Direct requests to appropriate controllers</li>
                        </ul>
                        <p class="mt-3">The application uses a middleware-based request processing pipeline that provides request filtering, validation, and processing capabilities before hitting controllers:</p>
                        <ul>
                            <li><strong>MiddlewareInterface:</strong> Defines the contract for all middleware components</li>
                            <li><strong>MiddlewareManager:</strong> Manages middleware stack execution in correct order</li>
                            <li><strong>BaseRouter:</strong> Orchestrates request handling through middleware to appropriate router (API or Page)</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingTwo">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                        Database
                    </button>
                </h2>
                <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#technicalAccordion">
                    <div class="accordion-body">
                        <p>E-Lib uses MongoDB as its primary database system with the following features:</p>
                        <ul>
                            <li>
                                <strong>Primary Database (MongoDB):</strong>
                                <ul>
                                    <li>Connected via MongoDB PHP Extension</li>
                                    <li>SSL/TLS encryption for secure connections</li>
                                    <li>Connection string managed through environment variables</li>
                                    <li>Support for MongoDB Atlas cloud deployment</li>
                                </ul>
                            </li>
                            <li>
                                <strong>Fallback Database (JSON):</strong>
                                <ul>
                                    <li>Automatic fallback when MongoDB is unavailable</li>
                                    <li>File-based storage with JSON serialization</li>
                                    <li>Implements same DatabaseInterface for seamless switching</li>
                                    <li>Maintains data persistence during connection issues</li>
                                </ul>
                            </li>
                            <li>
                                <strong>Connection Factory Pattern:</strong>
                                <ul>
                                    <li>MongoConnectionFactory for centralized connection management</li>
                                    <li>Automatic error handling and connection retries</li>
                                    <li>Certificate verification for secure connections</li>
                                </ul>
                            </li>
                            <li>
                                <strong>Database Collections:</strong>
                                <ul>
                                    <li><strong>Books:</strong> Stores book information (title, author, description, file paths)</li>
                                    <li><strong>Users:</strong> Stores user accounts and preferences</li>
                                    <li><strong>Reviews:</strong> Embedded within book documents for performance</li>
                                </ul>
                            </li>
                        </ul>
                        <p class="mt-3"><strong>Database Implementation:</strong></p>
                        <pre class="bg-light p-3 rounded"><code>// Database Interface ensures consistent API
interface DatabaseInterface {
    public function find(string $collection, array $filter): array;
    public function insert(string $collection, array $data): array;
    public function update(string $collection, array $filter, array $data): array;
    public function delete(string $collection, array $filter): array;
}

// Implementation automatically falls back to JSON if MongoDB is unavailable
$db = MongoConnectionFactory::create('mongo', [
    'fallback' => true,  // Enable automatic fallback to JsonDatabase
]);</code></pre>
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingThree">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                        Authentication & Security
                    </button>
                </h2>
                <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#technicalAccordion">
                    <div class="accordion-body">
                        <p>E-Lib implements multiple authentication mechanisms and security features:</p>
                        
                        <h5 class="mt-4">Authentication Methods</h5>
                        <ul>
                            <li>
                                <strong>Session-based Authentication:</strong>
                                <ul>
                                    <li>PHP session management for web interface users</li>
                                    <li>Session data stored securely with appropriate timeouts</li>
                                    <li>Managed by SessionManager class with secure defaults</li>
                                    <li>Protected against session fixation and hijacking attacks</li>
                                </ul>
                            </li>
                            <li>
                                <strong>JWT Authentication:</strong>
                                <ul>
                                    <li>JSON Web Tokens for API authentication</li>
                                    <li>Tokens include user ID, roles, and expiration time</li>
                                    <li>HMAC-SHA256 signature algorithm with secret key</li>
                                    <li>Token validation via JwtHelper class</li>
                                    <li>Configured token expiration periods (default: 1 hour)</li>
                                </ul>
                            </li>
                            <li>
                                <strong>CAS Authentication:</strong>
                                <ul>
                                    <li>Single Sign-On integration for institutional users</li>
                                    <li>Validation against external CAS server</li>
                                    <li>Automatic user provisioning from CAS attributes</li>
                                </ul>
                            </li>
                        </ul>
                        
                        <h5 class="mt-4">Security Implementation</h5>
                        <ul>
                            <li>
                                <strong>Middleware Security:</strong>
                                <ul>
                                    <li>AuthMiddleware protects web routes requiring authentication</li>
                                    <li>JwtAuthMiddleware validates API tokens for protected endpoints</li>
                                    <li>LoggingMiddleware records all access attempts for audit</li>
                                </ul>
                            </li>
                            <li>
                                <strong>Password Security:</strong>
                                <ul>
                                    <li>Passwords hashed using PHP's password_hash() with bcrypt</li>
                                    <li>Automatic salt generation for each password</li>
                                    <li>Password verification with constant-time comparison</li>
                                </ul>
                            </li>
                            <li>
                                <strong>Security Headers:</strong>
                                <ul>
                                    <li>Content-Security-Policy to prevent XSS attacks</li>
                                    <li>X-Frame-Options to prevent clickjacking</li>
                                    <li>X-XSS-Protection for additional browser protection</li>
                                    <li>Strict-Transport-Security for HTTPS enforcement</li>
                                </ul>
                            </li>
                        </ul>
                        
                        <p class="mt-3"><strong>JWT Authentication Implementation:</strong></p>
                        <pre class="bg-light p-3 rounded"><code>// JwtAuthMiddleware protects API endpoints
class JwtAuthMiddleware implements MiddlewareInterface {
    public function process(array $request, callable $next) {
        // Check if path requires authentication
        foreach ($this->protectedPaths as $protectedPath) {
            if (strpos($path, $protectedPath) === 0) {
                // Extract and validate token
                $authHeader = getallheaders()['Authorization'] ?? null;
                $token = str_replace('Bearer ', '', $authHeader);
                $decoded = JwtHelper::validateToken($token);
                
                // Add user data to request if valid
                $request['user'] = (array) $decoded;
            }
        }
        return $next($request);
    }
}</code></pre>
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingFour">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour" aria-expanded="false" aria-controls="collapseFour">
                        File Management
                    </button>
                </h2>
                <div id="collapseFour" class="accordion-collapse collapse" aria-labelledby="headingFour" data-bs-parent="#technicalAccordion">
                    <div class="accordion-body">
                        <p>E-Lib's file management system handles book PDFs and related media:</p>
                        
                        <h5 class="mt-4">File Storage Architecture</h5>
                        <ul>
                            <li>
                                <strong>File Storage Structure:</strong>
                                <ul>
                                    <li>Book PDFs stored in protected directory outside webroot</li>
                                    <li>Thumbnails stored in public directory with restricted file types</li>
                                    <li>Directory permissions set to appropriate access levels</li>
                                    <li>Files referenced by database records with secure paths</li>
                                </ul>
                            </li>
                            <li>
                                <strong>Upload Processing:</strong>
                                <ul>
                                    <li>File validation for MIME type, size, and content</li>
                                    <li>Automatic sanitization of filenames to prevent traversal attacks</li>
                                    <li>Secure random filename generation to prevent conflicts</li>
                                    <li>Virus scanning integration capability</li>
                                </ul>
                            </li>
                            <li>
                                <strong>Thumbnail Generation:</strong>
                                <ul>
                                    <li>Automatic thumbnail generation from first PDF page</li>
                                    <li>Image optimization and resizing for performance</li>
                                    <li>Support for JPEG, PNG, and WebP formats</li>
                                </ul>
                            </li>
                        </ul>
                        
                        <h5 class="mt-4">Access Control</h5>
                        <ul>
                            <li>
                                <strong>Content Delivery:</strong>
                                <ul>
                                    <li>Books served through authenticated PHP endpoints</li>
                                    <li>Direct file URL access prevented with .htaccess rules</li>
                                    <li>Content-Disposition headers for download control</li>
                                    <li>Streaming support for large PDF files</li>
                                </ul>
                            </li>
                            <li>
                                <strong>Permission System:</strong>
                                <ul>
                                    <li>Per-book download permission control</li>
                                    <li>Authentication requirement for protected content</li>
                                    <li>Rate limiting to prevent abuse</li>
                                    <li>Download tracking and statistics</li>
                                </ul>
                            </li>
                        </ul>
                        
                        <p class="mt-3"><strong>File Upload Implementation:</strong></p>
                        <pre class="bg-light p-3 rounded"><code>// File upload in BookController
public function addBook() {
    // Validate file
    $validator = new FileValidator();
    if (!$validator->validate($_FILES['bookPdf'])) {
        return ResponseHandler::respond(false, 'Invalid file');
    }
    
    // Generate secure filename and path
    $filename = bin2hex(random_bytes(16)) . '.pdf';
    $path = UPLOAD_DIR . '/' . $filename;
    
    // Save file securely
    if (move_uploaded_file($_FILES['bookPdf']['tmp_name'], $path)) {
        // Generate thumbnail
        $thumbPath = $this->pdfHelper->createThumbnail($path);
        
        // Create database record
        $bookData = [
            'title' => $_POST['title'],
            'path' => $path,
            'thumbnail' => $thumbPath,
            'downloadable' => $_POST['downloadable'] === 'true',
        ];
        
        $result = $this->bookService->addBook($bookData);
        return ResponseHandler::respond(true, 'Book added', $result);
    }
}</code></pre>
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h2 class="accordion-header" id="headingFive">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFive" aria-expanded="false" aria-controls="collapseFive">
                        API Endpoints
                    </button>
                </h2>
                <div id="collapseFive" class="accordion-collapse collapse" aria-labelledby="headingFive" data-bs-parent="#technicalAccordion">
                    <div class="accordion-body">
                        <p>E-Lib provides a comprehensive API with these main endpoints:</p>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Endpoint</th>
                                    <th>Method</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>/api/v1/books</td>
                                    <td>GET</td>
                                    <td>Get all books</td>
                                </tr>
                                <tr>
                                    <td>/api/v1/books</td>
                                    <td>POST</td>
                                    <td>Add a new book</td>
                                </tr>
                                <tr>
                                    <td>/api/v1/featured-books</td>
                                    <td>GET</td>
                                    <td>Get featured books</td>
                                </tr>
                                <tr>
                                    <td>/api/v1/reviews/:id</td>
                                    <td>GET</td>
                                    <td>Get reviews for a book</td>
                                </tr>
                                <tr>
                                    <td>/api/v1/reviews</td>
                                    <td>POST</td>
                                    <td>Submit a book review</td>
                                </tr>
                                <tr>
                                    <td>/api/v1/save-book</td>
                                    <td>POST</td>
                                    <td>Save a book to user's reading list</td>
                                </tr>
                                <tr>
                                    <td>/api/v1/remove-book</td>
                                    <td>POST</td>
                                    <td>Remove a book from user's reading list</td>
                                </tr>
                                <tr>
                                    <td>/api/v1/saved-books</td>
                                    <td>GET</td>
                                    <td>Get user's saved books</td>
                                </tr>
                                <tr>
                                    <td>/api/v1/login</td>
                                    <td>POST</td>
                                    <td>User login</td>
                                </tr>
                                <tr>
                                    <td>/api/v1/signup</td>
                                    <td>POST</td>
                                    <td>User registration</td>
                                </tr>
                                <tr>
                                    <td>/api/v1/logout</td>
                                    <td>POST</td>
                                    <td>User logout</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="mb-5">
        <h2>File Types Support</h2>
        <p>E-Lib primarily supports PDF files for book content, but also handles various related file types:</p>
        <div class="row">
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-header bg-info text-white">
                        Document Formats
                    </div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">PDF (.pdf)</li>
                        <li class="list-group-item">EPUB (.epub)</li>
                        <li class="list-group-item">Microsoft Word (.doc, .docx)</li>
                        <li class="list-group-item">Text files (.txt)</li>
                    </ul>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-header bg-info text-white">
                        Image Formats
                    </div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">JPEG (.jpg, .jpeg)</li>
                        <li class="list-group-item">PNG (.png)</li>
                        <li class="list-group-item">SVG (.svg)</li>
                        <li class="list-group-item">WebP (.webp)</li>
                    </ul>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-header bg-info text-white">
                        Metadata Formats
                    </div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">JSON (.json)</li>
                        <li class="list-group-item">XML (.xml)</li>
                        <li class="list-group-item">YAML (.yaml, .yml)</li>
                        <li class="list-group-item">CSV (.csv)</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <section class="mb-5">
        <h2>System Requirements</h2>
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        Server Requirements
                    </div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item"><strong>PHP:</strong> Version 7.4 or higher</li>
                        <li class="list-group-item"><strong>MongoDB:</strong> Version 4.0 or higher</li>
                        <li class="list-group-item"><strong>Web Server:</strong> Apache or Nginx</li>
                        <li class="list-group-item"><strong>Storage:</strong> Sufficient space for PDF storage</li>
                        <li class="list-group-item"><strong>PHP Extensions:</strong> mongodb, gd, fileinfo</li>
                    </ul>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        Client Requirements
                    </div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item"><strong>Browser:</strong> Modern web browser (Chrome, Firefox, Safari, Edge)</li>
                        <li class="list-group-item"><strong>JavaScript:</strong> Enabled</li>
                        <li class="list-group-item"><strong>PDF Viewer:</strong> Browser-native or plugin</li>
                        <li class="list-group-item"><strong>Cookies:</strong> Enabled for authentication</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <section class="mb-5">
        <h2>Development Information</h2>
        <div class="card">
            <div class="card-body">
                <p>E-Lib is developed using the following technologies and libraries:</p>
                <div class="row">
                    <div class="col-md-4">
                        <h5>Backend</h5>
                        <ul>
                            <li>PHP (Custom MVC framework)</li>
                            <li>MongoDB PHP extension</li>
                            <li>JWT for API authentication</li>
                            <li>GuzzleHTTP for HTTP requests</li>
                            <li>Monolog for logging</li>
                        </ul>
                    </div>
                    <div class="col-md-4">
                        <h5>Frontend</h5>
                        <ul>
                            <li>HTML5 & CSS3</li>
                            <li>JavaScript (ES6+)</li>
                            <li>Bootstrap 5</li>
                            <li>Font Awesome icons</li>
                            <li>Axios for AJAX requests</li>
                        </ul>
                    </div>
                    <div class="col-md-4">
                        <h5>Development Tools</h5>
                        <ul>
                            <li>Composer for dependency management</li>
                            <li>Git for version control</li>
                            <li>Docker for containerization</li>
                            <li>PHPUnit for testing</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="mb-5">
        <h2>User Guides</h2>
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">For Standard Users</h5>
                    </div>
                    <div class="card-body">
                        <ol>
                            <li><strong>Registration:</strong> Create an account using the Sign Up form</li>
                            <li><strong>Login:</strong> Use your email and password to log in</li>
                            <li><strong>Finding Books:</strong> Browse featured books or use the search function</li>
                            <li><strong>Reading Books:</strong> Click on "Online Preview" to read in browser</li>
                            <li><strong>Downloading:</strong> Use the Download button (when available)</li>
                            <li><strong>Saving Books:</strong> Click "Save to Reading List" to bookmark a book</li>
                            <li><strong>Writing Reviews:</strong> Rate and comment on books you've read</li>
                        </ol>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">For Administrators</h5>
                    </div>
                    <div class="card-body">
                        <ol>
                            <li><strong>Admin Access:</strong> Login with an admin account</li>
                            <li><strong>Adding Books:</strong> Use the "Add Book" form to upload new books</li>
                            <li><strong>Managing Content:</strong> Edit or delete books as needed</li>
                            <li><strong>Bulk Upload:</strong> Use mass upload feature for multiple books</li>
                            <li><strong>Setting Permissions:</strong> Control which books can be downloaded</li>
                            <li><strong>Featuring Books:</strong> Mark books as featured to highlight them</li>
                            <li><strong>Monitoring System:</strong> Check logs for errors or suspicious activity</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>