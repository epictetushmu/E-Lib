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

<!-- Include the EditBookModal component -->
<?php include __DIR__ . '/EditBookModal.php'; ?>

<!-- Include the MassUpload component -->
<?php include __DIR__ . '/MassUpload.php'; ?>

<!-- Include additional libraries -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/animate.css@4.1.1/animate.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
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
                        <!-- Single status toggle button with fixed width -->
                        <button type="button" class="btn btn-sm ${status === 'public' ? 'btn-success' : 'btn-secondary'}" 
                                style="min-width: 100px;"
                                onclick="simpleStatusChange('${id}', '${status === 'public' ? 'draft' : 'public'}')">
                            <i class="bi bi-${status === 'public' ? 'globe' : 'file-earmark'}"></i> 
                            ${status === 'public' ? 'Public' : 'Draft'}
                        </button>
                    </td>
                    <td class="text-center">
                        <button class="btn btn-sm ${featured ? 'btn-warning' : 'btn-outline-warning'} featured-toggle"
                                style="min-width: 100px;"
                                onclick="simpleFeatureToggle('${id}', ${!featured})">
                            <i class="bi bi-star${featured ? '-fill' : ''}"></i> 
                            ${featured ? 'Featured' : 'Regular'}
                        </button>
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

// Simple direct function for changing status
function simpleStatusChange(bookId, newStatus) {
    // Get the status button
    const statusButton = document.querySelector(`#bookRow-${bookId} td:nth-child(5) button`);
    
    // Show loading state
    const originalText = statusButton.innerHTML;
    statusButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
    statusButton.disabled = true;
    
    // Get auth token
    const authToken = localStorage.getItem('authToken') || sessionStorage.getItem('authToken');
    
    // Use the browser's fetch API for simplicity
    fetch(`/api/v1/books/${bookId}`, {
        method: 'PUT',
        headers: {
            'Authorization': `Bearer ${authToken}`,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ status: newStatus })
    })
    .then(response => response.json())
    .then(data => {
        console.log("Status update response:", data);
        
        if (data.status === 'success') {
            // Update button appearance based on the new status
            if (newStatus === 'public') {
                statusButton.className = 'btn btn-sm btn-success';
                statusButton.innerHTML = '<i class="bi bi-globe"></i> Public';
                // Update onclick for next click
                statusButton.setAttribute('onclick', `simpleStatusChange('${bookId}', 'draft')`);
            } else {
                statusButton.className = 'btn btn-sm btn-secondary';
                statusButton.innerHTML = '<i class="bi bi-file-earmark"></i> Draft';
                // Update onclick for next click
                statusButton.setAttribute('onclick', `simpleStatusChange('${bookId}', 'public')`);
            }
            
            // Show toast notification
            const toast = document.createElement('div');
            toast.className = 'position-fixed bottom-0 end-0 p-3';
            toast.style.zIndex = '11';
            toast.innerHTML = `
                <div class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="bi bi-check-circle me-2"></i>
                            Status updated to ${newStatus}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            `;
            document.body.appendChild(toast);
            const bsToast = new bootstrap.Toast(toast.querySelector('.toast'));
            bsToast.show();
            
            // Remove toast after it's hidden
            toast.addEventListener('hidden.bs.toast', function() {
                toast.remove();
            });
        } else {
            // Restore original button text
            statusButton.innerHTML = originalText;
            Swal.fire('Error', data.message || 'Failed to update status', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        statusButton.innerHTML = originalText;
        Swal.fire('Error', `Error updating status: ${error.message}`, 'error');
    })
    .finally(() => {
        statusButton.disabled = false;
    });
}

// Simple direct function for toggling featured status
function simpleFeatureToggle(bookId, setFeatured) {
    // Get the featured button
    const button = document.querySelector(`#bookRow-${bookId} td:nth-child(6) button`);
    
    // Show loading state
    const originalText = button.innerHTML;
    button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
    button.disabled = true;
    
    // Get auth token
    const authToken = localStorage.getItem('authToken') || sessionStorage.getItem('authToken');
    
    // Use fetch API for simplicity
    fetch(`/api/v1/books/${bookId}`, {
        method: 'PUT',
        headers: {
            'Authorization': `Bearer ${authToken}`,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ featured: setFeatured })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            // Update button appearance
            if (setFeatured) {
                button.className = 'btn btn-sm btn-warning featured-toggle';
                button.innerHTML = '<i class="bi bi-star-fill"></i> Featured';
                // Update onclick for next click
                button.setAttribute('onclick', `simpleFeatureToggle('${bookId}', false)`);
            } else {
                button.className = 'btn btn-sm btn-outline-warning featured-toggle';
                button.innerHTML = '<i class="bi bi-star"></i> Regular';
                // Update onclick for next click
                button.setAttribute('onclick', `simpleFeatureToggle('${bookId}', true)`);
            }
        } else {
            button.innerHTML = originalText;
            alert(`Error: ${data.message || 'Failed to update featured status'}`);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        button.innerHTML = originalText;
        alert(`Error: ${error.message}`);
    })
    .finally(() => {
        button.disabled = false;
    });
}
</script>
