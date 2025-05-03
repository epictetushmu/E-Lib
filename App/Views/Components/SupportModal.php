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
                            <form id="quickSupportForm">
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
                                    <label for="supportMessage" class="form-label">Message</label>
                                    <textarea class="form-control" id="supportMessage" name="message" rows="3" required></textarea>
                                </div>
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
    
    if (supportForm) {
        supportForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get form data
            const supportData = {
                name: document.getElementById('supportName').value.trim(),
                email: document.getElementById('supportEmail').value.trim(),
                message: document.getElementById('supportMessage').value.trim()
            };
            
            // Validate inputs
            if (!supportData.name || !supportData.email || !supportData.message) {
                showFormStatus('Please fill in all fields.', 'danger');
                return;
            }
            
            // Show loading state
            const submitBtn = supportForm.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Sending...';
            
            // Clear previous status
            statusAlert.classList.add('d-none');
            
            // Send the request to the API
            axios.post('/api/v1/support', supportData)
                .then(response => {
                    if (response.data && response.data.success) {
                        // Show success message
                        showFormStatus('Your message has been sent successfully. We will contact you soon.', 'success');
                        supportForm.reset();
                        
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