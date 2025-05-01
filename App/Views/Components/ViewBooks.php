<div class="container mt-4">
    <h2 class="mb-4">Manage Books</h2>
    
    <!-- Add Mass Upload Button -->
    <div class="d-flex justify-content-end mb-3">
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#massUploadModal">
            <i class="bi bi-cloud-arrow-up"></i> Mass Upload PDFs
        </button>
    </div>
    
    <div class="table-responsive">
        <table class="table table-striped align-middle">
            <thead class="table-dark">
                <tr>
                    <th class="text-center">Title</th>
                    <th class="text-center">Author</th>
                    <th class="text-center">Description</th>
                    <th class="text-center">ISBN</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Featured</th>
                    <th class="text-center" style="width: 180px;">Actions</th>
                </tr>
            </thead>
            <tbody id="booksTableBody">
                <!-- Dynamically injected rows -->
            </tbody>
        </table>
    </div>
</div>

<!-- Edit Book Modal -->
<div class="modal fade" id="editBookModal" tabindex="-1" aria-labelledby="editBookModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="editBookModalLabel">Edit Book</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="editBookFormContainer">
                <!-- Form will be dynamically inserted here -->
            </div>
        </div>
    </div>
</div>

<!-- Mass Upload Modal -->
<div class="modal fade" id="massUploadModal" tabindex="-1" aria-labelledby="massUploadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="massUploadModalLabel">Mass Upload PDFs</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="massUploadForm">
                    <!-- Drag and drop area -->
                    <div class="mb-4" id="dropZone">
                        <div class="border border-dashed border-2 rounded p-5 text-center" 
                             id="dropArea" 
                             style="border-style: dashed !important; min-height: 200px; background-color: #f8f9fa;">
                            <i class="bi bi-cloud-arrow-up fs-1 mb-3 text-muted"></i>
                            <h5>Drag & Drop PDF Files Here</h5>
                            <p class="text-muted">or</p>
                            <input type="file" id="pdfFiles" name="pdfFiles[]" accept="application/pdf" multiple style="display: none;">
                            <button type="button" id="browseButton" class="btn btn-outline-primary">Browse Files</button>
                        </div>
                    </div>

                    <!-- Selected files list -->
                    <div id="filesList" class="mb-4">
                        <h6>Selected Files <span id="fileCount" class="badge bg-secondary">0</span></h6>
                        <div id="filesContainer" class="list-group">
                            <!-- Selected files will be displayed here -->
                        </div>
                    </div>

                    <!-- Default values for all uploaded books -->
                    <div class="mb-4">
                        <h6 class="mb-3">Default Values for All Books</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="defaultAuthor" class="form-label">Default Author</label>
                                <input type="text" class="form-control" id="defaultAuthor" name="defaultAuthor" placeholder="Optional">
                            </div>
                            <div class="col-md-6">
                                <label for="defaultCategories" class="form-label">Default Categories</label>
                                <input type="text" class="form-control" id="defaultCategories" name="defaultCategories" placeholder="Comma-separated categories">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Downloadable</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="defaultDownloadable" name="defaultDownloadable" checked>
                                    <label class="form-check-label" for="defaultDownloadable">Allow download</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="defaultStatus" class="form-label">Default Status</label>
                                <select class="form-select" id="defaultStatus" name="defaultStatus">
                                    <option value="draft" selected>Draft</option>
                                    <option value="public">Public</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Progress and feedback -->
                    <div class="progress mb-3" style="display: none;" id="uploadProgressContainer">
                        <div id="uploadProgress" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                    </div>
                    <div id="uploadFeedback" class="alert alert-info" style="display: none;"></div>

                    <!-- Action buttons -->
                    <div class="d-flex justify-content-end mt-4">
                        <button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" id="uploadButton" class="btn btn-success">
                            <i class="bi bi-cloud-arrow-up me-1"></i> Upload All Files
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Include additional libraries -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/animate.css@4.1.1/animate.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    /* Additional styling for the modal */
    .modal-content {
        border-radius: 0.5rem;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    }
    
    .modal-header {
        border-radius: 0.5rem 0.5rem 0 0;
    }
    
    /* Animation for the modal */
    .modal.fade .modal-dialog {
        transition: transform 0.3s ease-out;
        transform: translateY(-50px);
    }
    
    .modal.show .modal-dialog {
        transform: translateY(0);
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', getBooks);

function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/[&<>"']/g, m => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    }[m]));
}

function getBooks() {
    const authToken = localStorage.getItem('authToken') || sessionStorage.getItem('authToken');
    axios.get('/api/v1/books', {
        headers: { Authorization: 'Bearer ' + authToken }
    })
    .then(response => {
        const books = response.data.data || [];
        const tableBody = document.getElementById('booksTableBody');
        tableBody.innerHTML = '';

        books.forEach(book => {
            const id = book._id?.$oid || book._id;
            const title = escapeHtml(book.title);
            const author = escapeHtml(book.author);
            const description = escapeHtml(book.description);
            const status = book.status || 'available';
            const categories = book.categories ? (Array.isArray(book.categories) ? book.categories.join(', ') : book.categories) : '';
            const featured = book.featured || false;
            const isbn = book.isbn || '';

            const displayRow = `
                <tr id="bookRow-${id}">
                    <td class="text-center">${title}</td>
                    <td class="text-center">${author}</td>
                    <td class="text-center">${description}</td>
                    <td class="text-center">${isbn}</td>
                    <td class="text-center">
                        <span class="badge ${status === 'public' ? 'bg-success' : 'bg-secondary'}">
                            ${status.charAt(0).toUpperCase() + status.slice(1)}
                        </span>
                    </td>
                    <td class="text-center">
                        <span class="badge ${featured ? 'bg-warning text-dark' : 'bg-light text-dark'}">
                            <i class="bi bi-star${featured ? '-fill' : ''}"></i> 
                            ${featured ? 'Featured' : 'Regular'}
                        </span>
                    </td>
                    <td class="text-center">
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-warning" onclick="editBook('${id}')">
                                <i class="bi bi-pencil-square"></i> Edit
                            </button>
                            <button class="btn btn-primary" onclick="previewBook('${id}')">
                                <i class="bi bi-file-earmark-pdf"></i> Preview
                            </button>
                            <button class="btn btn-danger" onclick="deleteBook('${id}')">
                                <i class="bi bi-trash3"></i> Delete
                            </button>
                        </div>
                    </td>
                </tr>
            `;

            tableBody.insertAdjacentHTML('beforeend', displayRow);
        });
    })
    .catch(error => {
        console.error('Error fetching books:', error);
        Swal.fire('Error', 'Failed to fetch books.', 'error');
    });
}

function editBook(bookId) {
    const authToken = localStorage.getItem('authToken') || sessionStorage.getItem('authToken');
    axios.get(`/api/v1/books/${bookId}`, {
        headers: { Authorization: 'Bearer ' + authToken }
    })
    .then(response => {
        const book = response.data.data;
        if (book) {
            const id = book._id?.$oid || book._id;
            const title = escapeHtml(book.title);
            const author = escapeHtml(book.author);
            const description = escapeHtml(book.description);
            const status = book.status || 'available';
            const categories = book.categories ? (Array.isArray(book.categories) ? book.categories.join(', ') : book.categories) : '';
            const featured = book.featured || false;
            const isbn = book.isbn || '';
            const downloadable = book.downloadable !== false; // Default to true if not set

            const editForm = `
                <form id="editForm-${id}" onsubmit="submitEdit(event, '${id}')">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="title-${id}" class="form-label">Title</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-book"></i></span>
                                <input type="text" class="form-control" id="title-${id}" name="title" value="${title}" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="author-${id}" class="form-label">Author</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control" id="author-${id}" name="author" value="${author}" required>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <label for="description-${id}" class="form-label">Description</label>
                            <textarea class="form-control" id="description-${id}" name="description" rows="2">${description}</textarea>
                        </div>
                        <div class="col-md-4">
                            <label for="status-${id}" class="form-label">Status</label>
                            <select class="form-select" id="status-${id}" name="status">
                                <option value="draft" ${status === 'draft' ? 'selected' : ''}>Draft</option>
                                <option value="public" ${status === 'public' ? 'selected' : ''}>Public</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="featured-${id}" class="form-label">Featured</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="featured-${id}" name="featured" value="true" ${book.featured ? 'checked' : ''}>
                                <label class="form-check-label" for="featured-${id}">
                                    Mark as Featured
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label for="isbn-${id}" class="form-label">ISBN</label>
                            <input type="text" class="form-control" id="isbn-${id}" name="isbn" value="${isbn}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Downloadable</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="downloadable" id="downloadableYes-${id}" value="true" ${downloadable ? 'checked' : ''}>
                                <label class="form-check-label" for="downloadableYes-${id}">Yes</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="downloadable" id="downloadableNo-${id}" value="false" ${!downloadable ? 'checked' : ''}>
                                <label class="form-check-label" for="downloadableNo-${id}">No</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label for="categories-${id}" class="form-label">Categories</label>
                            <input type="text" class="form-control" id="categories-${id}" name="categories"
                                value="${categories}" placeholder="Ex: Fiction, Fantasy, Adventure">
                            <div class="form-text">Separate categories with commas.</div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-4">
                        <button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Save Changes</button>
                    </div>
                </form>
            `;

            document.getElementById('editBookFormContainer').innerHTML = editForm;
            const editBookModal = new bootstrap.Modal(document.getElementById('editBookModal'));
            editBookModal.show();
        } else {
            Swal.fire('Error', 'Book not found.', 'error');
        }
    })
    .catch(error => {
        console.error('Error fetching book details:', error);
        Swal.fire('Error', 'Failed to fetch book details.', 'error');
    });
}

function submitEdit(event, bookId) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    const bookData = {};

    formData.forEach((value, key) => {
        bookData[key] = key === 'categories' ? value.split(',').map(v => v.trim()) : value;
    });

    const authToken = localStorage.getItem('authToken') || sessionStorage.getItem('authToken');
    axios.put(`/api/v1/books/${bookId}`, bookData, {
        headers: {
            'Authorization': 'Bearer ' + authToken,
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        if (response.data.status === 'success') {
            Swal.fire('Success', 'Book updated successfully.', 'success').then(() => {
                getBooks();
                const editBookModal = bootstrap.Modal.getInstance(document.getElementById('editBookModal'));
                editBookModal.hide();
            });
        } else {
            Swal.fire('Error', response.data.message || 'Update failed', 'error');
        }
    })
    .catch(err => {
        console.error('Error updating book:', err);
        Swal.fire('Error', 'An error occurred during update.', 'error');
    });
}

function deleteBook(bookId) {
    Swal.fire({
        title: 'Are you sure?',
        text: "This action can't be undone!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            const authToken = localStorage.getItem('authToken') || sessionStorage.getItem('authToken');
            axios.delete(`/api/v1/books/${bookId}`, {
                headers: { Authorization: 'Bearer ' + authToken }
            })
            .then(response => {
                if (response.data.status === 'success') {
                    Swal.fire('Deleted!', 'Book has been deleted.', 'success').then(() => getBooks());
                } else {
                    Swal.fire('Error', response.data.message || 'Delete failed', 'error');
                }
            })
            .catch(err => {
                console.error('Error deleting book:', err);
                Swal.fire('Error', 'An error occurred while deleting.', 'error');
            });
        }
    });
}

function previewBook(bookId) {
    const previewUrl = `/read/${bookId}`;
    window.open(previewUrl, '_blank');
}

// Mass Upload Feature
document.addEventListener('DOMContentLoaded', function() {
    const dropArea = document.getElementById('dropArea');
    const fileInput = document.getElementById('pdfFiles');
    const browseButton = document.getElementById('browseButton');
    const uploadButton = document.getElementById('uploadButton');
    const filesContainer = document.getElementById('filesContainer');
    const fileCountBadge = document.getElementById('fileCount');
    const uploadProgress = document.getElementById('uploadProgress');
    const uploadProgressContainer = document.getElementById('uploadProgressContainer');
    const uploadFeedback = document.getElementById('uploadFeedback');
    
    // Counter for successful and failed uploads
    let uploadStats = {
        success: 0,
        failed: 0,
        total: 0
    };
    
    // File selection through button
    browseButton.addEventListener('click', () => {
        fileInput.click();
    });
    
    // Handle file selection changes
    fileInput.addEventListener('change', handleFileSelection);
    
    // Drag and Drop Event Listeners
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    ['dragenter', 'dragover'].forEach(eventName => {
        dropArea.addEventListener(eventName, highlight, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, unhighlight, false);
    });
    
    function highlight() {
        dropArea.classList.add('bg-light');
        dropArea.classList.add('border-primary');
    }
    
    function unhighlight() {
        dropArea.classList.remove('bg-light');
        dropArea.classList.remove('border-primary');
    }
    
    // Handle file drop
    dropArea.addEventListener('drop', handleDrop, false);
    
    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = [...dt.files].filter(file => file.type === 'application/pdf');
        
        if (files.length === 0) {
            Swal.fire('Invalid Files', 'Please select PDF files only.', 'warning');
            return;
        }
        
        addFilesToList(files);
    }
    
    function handleFileSelection(e) {
        const files = [...e.target.files];
        if (files.length === 0) return;
        
        addFilesToList(files);
    }
    
    function addFilesToList(files) {
        // Add files to the list with title input fields
        files.forEach(file => {
            // Generate filename without extension to use as default title
            const fileName = file.name;
            const defaultTitle = fileName.replace(/\.pdf$/i, '').replace(/[_-]/g, ' ');
            
            const fileItem = document.createElement('div');
            fileItem.className = 'list-group-item animate__animated animate__fadeIn';
            fileItem.dataset.fileName = fileName;
            
            fileItem.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">
                        <i class="bi bi-file-earmark-pdf text-danger me-2"></i>
                        ${escapeHtml(fileName)}
                    </h6>
                    <button type="button" class="btn btn-sm btn-outline-danger remove-file">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="row g-2">
                    <div class="col-md-8">
                        <input type="text" class="form-control form-control-sm file-title" 
                               placeholder="Book Title" value="${escapeHtml(defaultTitle)}" required>
                    </div>
                    <div class="col-md-4">
                        <input type="text" class="form-control form-control-sm file-author" 
                               placeholder="Author (optional)">
                    </div>
                </div>
            `;
            
            // Store the File object in the DOM element
            fileItem._file = file;
            
            filesContainer.appendChild(fileItem);
            
            // Add event listener to remove button
            fileItem.querySelector('.remove-file').addEventListener('click', () => {
                fileItem.classList.add('animate__fadeOut');
                setTimeout(() => {
                    filesContainer.removeChild(fileItem);
                    updateFileCount();
                }, 300);
            });
        });
        
        updateFileCount();
    }
    
    function updateFileCount() {
        const count = filesContainer.children.length;
        fileCountBadge.textContent = count;
        
        // Toggle upload button state
        uploadButton.disabled = count === 0;
    }
    
    // Handle upload process
    uploadButton.addEventListener('click', async () => {
        const fileItems = [...filesContainer.children];
        if (fileItems.length === 0) {
            Swal.fire('No Files', 'Please select at least one PDF file to upload.', 'warning');
            return;
        }
        
        // Validate that all files have titles
        const invalidFiles = fileItems.filter(item => !item.querySelector('.file-title').value.trim());
        if (invalidFiles.length > 0) {
            Swal.fire('Missing Titles', 'Please provide titles for all files.', 'warning');
            invalidFiles.forEach(item => item.querySelector('.file-title').classList.add('is-invalid'));
            return;
        }
        
        // Get default values
        const defaultValues = {
            author: document.getElementById('defaultAuthor').value,
            categories: document.getElementById('defaultCategories').value.split(',').map(c => c.trim()).filter(c => c),
            downloadable: document.getElementById('defaultDownloadable').checked,
            status: document.getElementById('defaultStatus').value
        };
        
        // Reset upload statistics
        uploadStats = {
            success: 0,
            failed: 0,
            total: fileItems.length
        };
        
        // Show progress bar
        uploadProgressContainer.style.display = 'block';
        uploadProgress.style.width = '0%';
        uploadProgress.textContent = '0%';
        uploadFeedback.style.display = 'block';
        uploadFeedback.className = 'alert alert-info';
        uploadFeedback.innerHTML = '<i class="bi bi-arrow-repeat spin me-2"></i> Starting upload...';
        
        // Disable upload button during operation
        uploadButton.disabled = true;
        
        // Process files one by one for better error handling
        for (let i = 0; i < fileItems.length; i++) {
            const item = fileItems[i];
            const file = item._file;
            const title = item.querySelector('.file-title').value.trim();
            const author = item.querySelector('.file-author').value.trim() || defaultValues.author;
            
            // Update progress
            const progressPercent = Math.round(((i) / fileItems.length) * 100);
            uploadProgress.style.width = `${progressPercent}%`;
            uploadProgress.textContent = `${progressPercent}%`;
            uploadFeedback.innerHTML = `<i class="bi bi-arrow-repeat spin me-2"></i> Uploading ${i+1} of ${fileItems.length}: <strong>${escapeHtml(title)}</strong>`;
            
            try {
                await uploadSingleFile(file, {
                    title, 
                    author,
                    categories: defaultValues.categories,
                    downloadable: defaultValues.downloadable,
                    status: defaultValues.status
                });
                
                // Mark as success
                uploadStats.success++;
                item.classList.add('list-group-item-success');
            } catch (error) {
                console.error('Upload error:', error);
                uploadStats.failed++;
                item.classList.add('list-group-item-danger');
            }
        }
        
        // Complete the progress
        uploadProgress.style.width = '100%';
        uploadProgress.textContent = '100%';
        
        // Show final status
        if (uploadStats.failed === 0) {
            uploadFeedback.className = 'alert alert-success';
            uploadFeedback.innerHTML = `<i class="bi bi-check-circle me-2"></i> All ${uploadStats.success} books were uploaded successfully!`;
        } else {
            uploadFeedback.className = 'alert alert-warning';
            uploadFeedback.innerHTML = `<i class="bi bi-exclamation-triangle me-2"></i> ${uploadStats.success} succeeded, ${uploadStats.failed} failed. Check console for errors.`;
        }
        
        // Re-enable upload button
        uploadButton.disabled = false;
        
        // Refresh the book list
        getBooks();
    });
    
    async function uploadSingleFile(file, bookData) {
        return new Promise((resolve, reject) => {
            const formData = new FormData();
            
            // Add book metadata
            formData.append('title', bookData.title);
            formData.append('author', bookData.author || '');
            formData.append('categories', JSON.stringify(bookData.categories || []));
            formData.append('description', `Uploaded via mass upload feature`);
            formData.append('downloadable', bookData.downloadable ? 'true' : 'false');
            formData.append('status', bookData.status || 'draft');
            
            // Add the PDF file
            formData.append('bookPdf', file);
            
            // Get auth token
            const authToken = localStorage.getItem('authToken') || sessionStorage.getItem('authToken');
            
            // Send the request
            axios.post('/api/v1/books', formData, {
                headers: {
                    'Authorization': `Bearer ${authToken}`,
                    'Content-Type': 'multipart/form-data'
                }
            })
            .then(response => {
                if (response.data.status === 'success') {
                    resolve(response.data);
                } else {
                    reject(new Error(response.data.message || 'Upload failed'));
                }
            })
            .catch(error => {
                reject(error);
            });
        });
    }
});

// CSS for spinning animation
document.head.insertAdjacentHTML('beforeend', `
    <style>
        .spin {
            animation: spin 2s linear infinite;
            display: inline-block;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .border-dashed {
            border-style: dashed !important;
        }
        
        .animate__animated {
            animation-duration: 0.5s;
        }
        
        .animate__fadeIn {
            animation-name: fadeIn;
        }
        
        .animate__fadeOut {
            animation-name: fadeOut;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes fadeOut {
            from { opacity: 1; transform: translateY(0); }
            to { opacity: 0; transform: translateY(10px); }
        }
    </style>
`);
</script>
