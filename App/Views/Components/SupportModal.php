<?php
/**
 * Support Modal Component
 * A reusable help center modal that can be included in any page
 */
?>

<!-- Support Modal -->
<div class="modal fade" id="supportModal" tabindex="-1" aria-labelledby="supportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="supportModalLabel"><i class="fas fa-hands-helping me-2"></i>Help Center</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="container-fluid">
                    <!-- Documentation Link -->
                    <div class="row mb-4">
                        <div class="col-12 text-center">
                            <div class="alert alert-info">
                                <i class="fas fa-book me-2"></i>
                                <strong>Need detailed instructions?</strong> Visit our 
                                <a href="/docs" class="alert-link">Documentation Page</a> 
                                for comprehensive guides and tutorials.
                            </div>
                        </div>
                    </div>
                    
                    <!-- FAQ Section -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h5 class="border-bottom pb-2">Frequently Asked Questions</h5>
                            
                            <div class="accordion" id="faqAccordion">
                                <!-- FAQ Item 1 -->
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="faqHeading1">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse1" aria-expanded="false" aria-controls="faqCollapse1">
                                            How do I download books?
                                        </button>
                                    </h2>
                                    <div id="faqCollapse1" class="accordion-collapse collapse" aria-labelledby="faqHeading1" data-bs-parent="#faqAccordion">
                                        <div class="accordion-body">
                                            To download a book, you need to be logged in. Once logged in, navigate to the book detail page and click on the "Download" button. The PDF will be downloaded to your device automatically.
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- FAQ Item 2 -->
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="faqHeading2">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse2" aria-expanded="false" aria-controls="faqCollapse2">
                                            How can I upload my own books?
                                        </button>
                                    </h2>
                                    <div id="faqCollapse2" class="accordion-collapse collapse" aria-labelledby="faqHeading2" data-bs-parent="#faqAccordion">
                                        <div class="accordion-body">
                                            To upload books, you need administrator privileges. If you have appropriate access, navigate to the Book Management section and use either the "Add Book" or "Mass Upload" feature to upload PDFs to the library.
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- FAQ Item 3 -->
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="faqHeading3">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse3" aria-expanded="false" aria-controls="faqCollapse3">
                                            I forgot my password. What do I do?
                                        </button>
                                    </h2>
                                    <div id="faqCollapse3" class="accordion-collapse collapse" aria-labelledby="faqHeading3" data-bs-parent="#faqAccordion">
                                        <div class="accordion-body">
                                            On the login page, click on the "Forgot Password" link and follow the instructions to reset your password. You will receive an email with a reset link. If you don't receive an email, contact our support team.
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- FAQ Item 4 -->
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="faqHeading4">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse4" aria-expanded="false" aria-controls="faqCollapse4">
                                            Can I read books online without downloading?
                                        </button>
                                    </h2>
                                    <div id="faqCollapse4" class="accordion-collapse collapse" aria-labelledby="faqHeading4" data-bs-parent="#faqAccordion">
                                        <div class="accordion-body">
                                            Yes! For most books, you can click on the "Read Online" button to access our built-in PDF viewer without downloading the file to your device.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contact Support Section -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <h5 class="border-bottom pb-2">Contact Support</h5>
                            <p>Our support team is available Monday through Friday, 9AM to 5PM (UTC+2).</p>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-envelope me-2"></i>Email: <a href="menounosnikitas@gmail.com">support@epictetuslibrary.org</a></li>
                                <li><i class="fas fa-comment-alt me-2"></i>Response time: Usually within 24 hours</li>
                            </ul>
                        </div>
                        
                        <!-- Quick Support Form -->
                        <div class="col-md-6">
                            <h5 class="border-bottom pb-2">Quick Support Request</h5>
                            <form id="quickSupportForm" enctype="multipart/form-data">
                                <div id="supportFormStatus" class="alert d-none mb-3"></div>
                                <div class="mb-3">
                                    <label for="supportName" class="form-label">Name</label>
                                    <input type="text" class="form-control" id="supportName" name="name" required>
                                </div>
                                <div class="mb-3">
                                    <label for="supportEmail" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="supportEmail" name="email" required>
                                </div>
                                <div class="mb-3">
                                    <label for="supportMessageContainer" class="form-label">Message</label>
                                    <div class="position-relative">
                                        <!-- Hidden textarea for form submission - without "required" attribute -->
                                        <textarea class="form-control d-none" id="supportMessage" name="message" rows="5"></textarea>
                                        
                                        <!-- Visible rich content editor -->
                                        <div id="supportMessageContainer" class="form-control" style="min-height:120px; max-height:300px; overflow-y:auto" contenteditable="true"></div>
                                        
                                        <div class="text-muted small mt-1">You can paste images directly into the message field (Ctrl+V / Cmd+V)</div>
                                        <button type="button" id="attachImageBtn" class="btn btn-sm btn-outline-secondary position-absolute" style="bottom: 25px; right: 10px;" title="Attach image">
                                            <i class="fas fa-image"></i>
                                        </button>
                                    </div>
                                </div>
                                <input type="file" id="hiddenImageInput" accept="image/*" style="display: none;" multiple>
                                <button type="submit" class="btn btn-warning">Submit Request</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Support Form Submission Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const supportForm = document.getElementById('quickSupportForm');
    const statusAlert = document.getElementById('supportFormStatus');
    const messageContainer = document.getElementById('supportMessageContainer');
    const hiddenMessageInput = document.getElementById('supportMessage');
    const hiddenImageInput = document.getElementById('hiddenImageInput');
    const attachImageBtn = document.getElementById('attachImageBtn');
    
    // Track embedded images
    const embeddedImages = new Map();
    let imageCounter = 0;
    
    // Form submission - with explicit validation
    if (supportForm && messageContainer && hiddenMessageInput) {
        supportForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Copy the content (without images) to the hidden textarea for submission
            hiddenMessageInput.value = extractTextContent(messageContainer);
            
            // Perform manual validation
            const name = supportForm.querySelector('#supportName').value.trim();
            const email = supportForm.querySelector('#supportEmail').value.trim();
            const message = hiddenMessageInput.value.trim();
            
            // Validate each field
            let isValid = true;
            
            if (!name) {
                showFormStatus('Please enter your name.', 'danger');
                isValid = false;
            } else if (!email) {
                showFormStatus('Please enter your email address.', 'danger');
                isValid = false;
            } else if (!isValidEmail(email)) {
                showFormStatus('Please enter a valid email address.', 'danger');
                isValid = false;
            } else if (!message) {
                showFormStatus('Please enter a message.', 'danger');
                messageContainer.focus();
                isValid = false;
            }
            
            if (!isValid) {
                return;
            }
            
            // If validation passes, continue with submission
            submitSupportForm();
        });
    }
    
    // Email validation helper
    function isValidEmail(email) {
        const re = /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
        return re.test(String(email).toLowerCase());
    }
    
    // Extract plain text from HTML content (for server-side processing)
    function extractTextContent(container) {
        let text = '';
        const tempContainer = container.cloneNode(true);
        
        const images = tempContainer.querySelectorAll('img.embedded-message-image');
        images.forEach((img, index) => {
            const marker = document.createTextNode(`[Image #${img.dataset.imageId.split('_')[2]}]\n`);
            img.parentNode.replaceChild(marker, img);
        });
        
        text = tempContainer.textContent;
        return text;
    }
    
    // Handle paste events to capture images
    if (messageContainer) {
        messageContainer.addEventListener('paste', function(e) {
            const items = (e.clipboardData || e.originalEvent.clipboardData).items;
            let foundImage = false;
            
            for (let i = 0; i < items.length; i++) {
                if (items[i].type.indexOf('image') === 0) {
                    e.preventDefault();
                    foundImage = true;
                    
                    const blob = items[i].getAsFile();
                    
                    if (blob.size > 5 * 1024 * 1024) {
                        showFormStatus('The image exceeds the 5MB size limit.', 'warning');
                        return;
                    }
                    
                    if (embeddedImages.size >= 5) {
                        showFormStatus('Maximum 5 images can be embedded in a single message.', 'warning');
                        return;
                    }
                    
                    processImageFile(blob);
                }
            }
            
            if (!foundImage) {
                // Clean up pasted content - only allow plain text
                setTimeout(() => {
                    // Optional: clean up pasted HTML if needed
                }, 0);
            }
        });
        
        // Focus the editable div
        messageContainer.focus();
    }
    
    // File input click handler
    if (attachImageBtn && hiddenImageInput) {
        attachImageBtn.addEventListener('click', function() {
            hiddenImageInput.click();
        });
        
        hiddenImageInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                // Validate file count for one selection
                if (this.files.length > 3) {
                    showFormStatus('Please select a maximum of 3 files at once.', 'warning');
                    this.value = '';
                    return;
                }
                
                // Process each file
                Array.from(this.files).forEach(file => {
                    // Validate file size
                    if (file.size > 5 * 1024 * 1024) {
                        showFormStatus('One or more files exceed the 5MB limit.', 'warning');
                        return;
                    }
                    
                    // Validate total embedded images
                    if (embeddedImages.size >= 5) {
                        showFormStatus('Maximum 5 images can be embedded in a single message.', 'warning');
                        return;
                    }
                    
                    processImageFile(file);
                });
                
                // Reset the input
                this.value = '';
            }
        });
    }
    
    // Process an image file and embed it in the message container
    function processImageFile(file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const imageId = 'embedded_img_' + (++imageCounter);
            embeddedImages.set(imageId, file);
            
            // Create image element
            const img = document.createElement('img');
            img.src = e.target.result;
            img.className = 'embedded-message-image img-fluid mb-2';
            img.style.maxHeight = '200px';
            img.style.maxWidth = '100%';
            img.style.display = 'block';
            img.dataset.imageId = imageId;
            
            // Create delete button
            const deleteBtn = document.createElement('button');
            deleteBtn.type = 'button';
            deleteBtn.className = 'btn btn-sm btn-danger mb-3';
            deleteBtn.innerHTML = '<i class="fas fa-times"></i> Remove';
            deleteBtn.style.display = 'block';
            deleteBtn.dataset.imageId = imageId;
            
            // Add click handler for delete button
            deleteBtn.addEventListener('click', function() {
                const imageId = this.dataset.imageId;
                embeddedImages.delete(imageId);
                
                // Find and remove the image and button
                const imageWrapper = this.parentNode;
                if (imageWrapper) {
                    imageWrapper.remove();
                }
            });
            
            // Create wrapper for image and delete button
            const wrapper = document.createElement('div');
            wrapper.className = 'embedded-image-wrapper';
            wrapper.appendChild(img);
            wrapper.appendChild(deleteBtn);
            
            // Insert at cursor position or at the end if no selection
            insertAtCursor(wrapper);
            
            // Focus back on the editor
            messageContainer.focus();
        };
        reader.readAsDataURL(file);
    }
    
    // Insert HTML element at cursor position
    function insertAtCursor(element) {
        const selection = window.getSelection();
        const range = selection.getRangeAt(0);
        range.deleteContents();
        range.insertNode(element);
        
        // Move cursor after the inserted element
        range.setStartAfter(element);
        range.setEndAfter(element);
        selection.removeAllRanges();
        selection.addRange(range);
    }
    
    // Submit the form data to the server
    function submitSupportForm() {
        // Get form data
        const formData = new FormData(supportForm);
        
        // Add embedded images to form data
        embeddedImages.forEach((file, imageId) => {
            formData.append('embedded_images[]', file, file.name || 'image.jpg');
        });
        
        // Show loading state
        const submitBtn = supportForm.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Sending...';
        
        // Clear previous status
        statusAlert.classList.add('d-none');
        
        // Send the request to the API
        axios.post('/api/v1/support', formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        })
            .then(response => {
                if (response.data && response.data.status === 'success') {
                    // Show success message
                    showFormStatus('Your message has been sent successfully. We will contact you soon.', 'success');
                    supportForm.reset();
                    messageContainer.innerHTML = '';
                    embeddedImages.clear();
                    
                    // Close modal after 2 seconds
                    setTimeout(() => {
                        const supportModal = bootstrap.Modal.getInstance(document.getElementById('supportModal'));
                        if (supportModal) {
                            supportModal.hide();
                        }
                    }, 2000);
                } else {
                    // Handle server-side validation errors
                    showFormStatus(response.data.message || 'An error occurred while sending your message.', 'danger');
                }
            })
            .catch(error => {
                console.error('Error submitting support request:', error);
                showFormStatus('There was an error sending your message. Please try again later.', 'danger');
            })
            .finally(() => {
                // Restore button state
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
    }
    
    // Helper function to display status messages
    function showFormStatus(message, type) {
        statusAlert.textContent = message;
        statusAlert.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning');
        statusAlert.classList.add('alert-' + type);
    }
});
</script>