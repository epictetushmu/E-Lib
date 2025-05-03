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

<style>
    /* CSS for spinning animation */
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

<script>
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
    
    function escapeHtml(text) {
        if (!text) return '';
        return text.replace(/[&<>"']/g, m => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
        }[m]));
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
        uploadFeedback.innerHTML = '<i class="bi bi-arrow-repeat spin me-2"></i> Preparing files for upload...';
        
        // Disable upload button during operation
        uploadButton.disabled = true;
        
        try {
            // Create FormData for mass upload
            const formData = new FormData();
            
            // Add default values
            formData.append('defaultAuthor', defaultValues.author);
            formData.append('defaultCategories', JSON.stringify(defaultValues.categories));
            formData.append('defaultStatus', defaultValues.status);
            formData.append('defaultDownloadable', defaultValues.downloadable ? 'true' : 'false');
            
            // Add each file and its metadata
            fileItems.forEach((item, index) => {
                const file = item._file;
                const title = item.querySelector('.file-title').value.trim();
                const author = item.querySelector('.file-author').value.trim();
                
                // Add file to FormData with indexed name
                formData.append(`books[name][]`, file.name);
                formData.append(`books[type][]`, file.type);
                formData.append(`books[tmp_name][]`, file.tmp_name);
                formData.append(`books[error][]`, '0');
                formData.append(`books[size][]`, file.size);
                
                // Append the actual file
                formData.append(`books[]`, file);
                
                // Add metadata for this specific file
                if (title || author) {
                    const metadata = {
                        title: title,
                        author: author || defaultValues.author
                    };
                    
                    formData.append(`metadata_${index}`, JSON.stringify(metadata));
                }
            });
            
            // Update progress
            uploadFeedback.innerHTML = '<i class="bi bi-arrow-repeat spin me-2"></i> Uploading files...';
            
            // Get auth token
            const authToken = localStorage.getItem('authToken') || sessionStorage.getItem('authToken');
            
            // Send request to the mass upload endpoint
            const response = await axios.post('/api/v1/books/mass-upload', formData, {
                headers: {
                    'Authorization': `Bearer ${authToken}`,
                    'Content-Type': 'multipart/form-data'
                },
                onUploadProgress: progressEvent => {
                    const percentCompleted = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                    uploadProgress.style.width = `${percentCompleted}%`;
                    uploadProgress.textContent = `${percentCompleted}%`;
                }
            });
            
            // Process response
            if (response.data.status === "success") {
                const results = response.data.data?.results || {};
                
                // Update statistics
                uploadStats.success = results.success?.length || 0;
                uploadStats.failed = results.failed?.length || 0;
                
                // Mark UI items as success or failure
                if (results.success) {
                    results.success.forEach(item => {
                        const element = Array.from(fileItems).find(el => 
                            el._file.name === item.filename || 
                            el.querySelector('.file-title').value === item.title
                        );
                        if (element) element.classList.add('list-group-item-success');
                    });
                }
                
                if (results.failed) {
                    results.failed.forEach(item => {
                        const element = Array.from(fileItems).find(el => el._file.name === item.filename);
                        if (element) {
                            element.classList.add('list-group-item-danger');
                            const reasonDiv = document.createElement('div');
                            reasonDiv.className = 'small text-danger mt-1';
                            reasonDiv.textContent = item.reason;
                            element.appendChild(reasonDiv);
                        }
                    });
                }
                
                // Show final status
                uploadProgress.style.width = '100%';
                uploadProgress.textContent = '100%';
                
                if (uploadStats.failed === 0) {
                    uploadFeedback.className = 'alert alert-success';
                    uploadFeedback.innerHTML = `<i class="bi bi-check-circle me-2"></i> All ${uploadStats.success} books were uploaded successfully!`;
                    
                    // Close the modal automatically after 2 seconds
                    setTimeout(() => {
                        const modalElement = document.getElementById('massUploadModal');
                        const modalInstance = bootstrap.Modal.getInstance(modalElement);
                        
                        if (modalInstance) {
                            // Properly hide and dispose of the modal
                            modalInstance.hide();
                            
                            // Remove backdrop and reset body styles after the modal transition
                            modalElement.addEventListener('hidden.bs.modal', function () {
                                // Remove any lingering backdrop
                                const backdrop = document.querySelector('.modal-backdrop');
                                if (backdrop) {
                                    backdrop.remove();
                                }
                                
                                // Reset body styles
                                document.body.classList.remove('modal-open');
                                document.body.style.paddingRight = '';
                                document.body.style.overflow = '';
                                
                                // Clear the form data
                                filesContainer.innerHTML = '';
                                updateFileCount();
                                uploadProgressContainer.style.display = 'none';
                                uploadFeedback.style.display = 'none';
                            }, { once: true });
                        }
                    }, 2000);
                } else {
                    uploadFeedback.className = 'alert alert-warning';
                    uploadFeedback.innerHTML = `<i class="bi bi-exclamation-triangle me-2"></i> ${uploadStats.success} succeeded, ${uploadStats.failed} failed.`;
                }
            } else {
                uploadFeedback.className = 'alert alert-danger';
                uploadFeedback.innerHTML = `<i class="bi bi-x-circle me-2"></i> Upload failed: ${response.data.message || 'Unknown error'}`;
            }
        } catch (error) {
            console.error('Mass upload error:', error);
            uploadFeedback.className = 'alert alert-danger';
            uploadFeedback.innerHTML = `<i class="bi bi-x-circle me-2"></i> Upload failed: ${error.response?.data?.message || error.message || 'Network error'}`;
        } finally {
            // Re-enable upload button
            uploadButton.disabled = false;
            
            // Refresh the book list
            if (typeof getBooks === 'function') {
                getBooks();
            }
        }
    });
});
</script>
