<div class="row mt-5">
    <div class="col-12">
        <h3 class="mb-4">Reviews</h3>

        <!-- Display Reviews -->
        <div id="reviewsContainer">
            <div class="text-center py-3" id="reviews-loading">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading reviews...</span>
                </div>
                <p class="mt-2">Loading reviews...</p>
            </div>
        </div>

        <!-- Review Form - Will be shown/hidden via JavaScript based on auth token -->
        <div class="card mb-4" id="reviewFormContainer" style="display: none;">
            <div class="card-body">
                <h5 class="card-title">Add Your Review</h5>
                <form id="reviewForm">
                    <!-- The bookId will be set by JavaScript -->
                    <input type="hidden" id="bookId" value="">
                    <div class="mb-3">
                        <label class="form-label">Rating</label>
                        <div class="star-rating" id="ratingStars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="far fa-star" data-rating="<?= $i ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <input type="hidden" id="rating" value="0">
                    </div>
                    <div class="mb-3">
                        <label for="comment" class="form-label">Comment</label>
                        <textarea class="form-control" id="comment" rows="3" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Submit Review</button>
                </form>
            </div>
        </div>

        <!-- Message for guests -->
        <div id="guestReviewMessage" class="alert alert-info" style="display: none;">
            <i class="fas fa-info-circle me-2"></i>
            Please <a href="/login" class="alert-link">login</a> to leave a review.
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check if user is logged in
    const pathParts = window.location.pathname.split('/');
    const bookId = pathParts[pathParts.length - 1];

    const token = localStorage.getItem('authToken') || sessionStorage.getItem('authToken');
    const reviewFormContainer = document.getElementById('reviewFormContainer');
    const guestReviewMessage = document.getElementById('guestReviewMessage');
    
    if (token) {
        // Show form for logged-in users
        reviewFormContainer.style.display = 'block';
        if (guestReviewMessage) guestReviewMessage.style.display = 'none';
    } else {
        // Show message for guests
        reviewFormContainer.style.display = 'none';
        if (guestReviewMessage) guestReviewMessage.style.display = 'block';
    }

    // Setup star rating system
    const stars = document.querySelectorAll('#ratingStars i');
    const ratingInput = document.getElementById('rating');
    
    stars.forEach(star => {
        star.addEventListener('click', () => {
            const rating = parseInt(star.getAttribute('data-rating'));
            ratingInput.value = rating;
            
            // Update stars display
            stars.forEach((s, index) => {
                if (index < rating) {
                    s.classList.remove('far');
                    s.classList.add('fas');
                } else {
                    s.classList.remove('fas');
                    s.classList.add('far');
                }
            });
        });
        
        star.addEventListener('mouseover', () => {
            const rating = parseInt(star.getAttribute('data-rating'));
            
            // Temp highlight stars
            stars.forEach((s, index) => {
                if (index < rating) {
                    s.classList.add('text-warning');
                } else {
                    s.classList.remove('text-warning');
                }
            });
        });
        
        star.addEventListener('mouseout', () => {
            stars.forEach(s => s.classList.remove('text-warning'));
        });
    });
               

    // Review form submission handler
    const reviewForm = document.getElementById('reviewForm');
    if (reviewForm) {
        reviewForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get form data
            const rating = document.getElementById('rating').value;
            const comment = document.getElementById('comment').value;
            const token = localStorage.getItem('authToken') || sessionStorage.getItem('authToken');
            if (!token) {
                alert('You need to be logged in to submit a review.');
                return;
            }

            // Validate form
            if (rating === '0') {
                alert('Please select a rating');
                return;
            }
            
            if (!comment.trim()) {
                alert('Please enter a comment');
                return;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
            
            // Send data to server
            axios.post(`/api/v1/reviews`, {
                book_id: bookId,    
                rating: parseInt(rating),
                comment: comment
            },
            {
                headers: {
                    "Authorization": `Bearer ${token}`  
                }
            })
            .then(response => {
                if (response.data.status === 'success') {
                    // Reset form
                    document.getElementById('rating').value = '0';
                    document.getElementById('comment').value = '';
                    
                    // Reset stars display
                    const stars = document.querySelectorAll('#ratingStars i');
                    stars.forEach(s => {
                        s.classList.remove('fas', 'text-warning');
                        s.classList.add('far');
                    });
                    
                    // Show success message
                    alert('Your review has been submitted successfully!');
                    
                    // Refresh reviews without page reload
                    fetchReviews(bookId);
                } else {
                    alert(response.data.message || 'Error submitting review');
                }
            })
            .catch(error => {
                console.error('Review submission error:', error);
                alert('Error submitting review. Please try again.');
            })
            .finally(() => {
                // Reset button
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            });
        });
    }

    // Function to fetch and update reviews
    if (bookId) {
        fetchReviews(bookId);
    }
    function fetchReviews(bookId) {
        
        axios.get(`/api/v1/reviews/${bookId}`, {headers: {
            "Authorization": `Bearer ${localStorage.getItem('authToken') || sessionStorage.getItem('authToken')}`
        }})
            .then(response => {
                
                if (response.data.status === 'success') {
                    // FIX: Access the nested array of reviews
                    const reviews = response.data.data;
                    
                    const container = document.getElementById('reviewsContainer');
                    
                    // Check specifically for empty arrays, including JSON representation of empty array
                    if (!reviews || reviews.length === 0 || JSON.stringify(reviews) === '[]') {
                        container.innerHTML = '<div class="alert alert-info">No reviews yet. Be the first to review this book!</div>';
                        return;
                    }
                    
                    container.innerHTML = '';
                    reviews.forEach(review => {
                        // Handle various possible data structures
                        const username = review.username || review.user_name || (review.user && review.user.username) || 'Anonymous';
                        const rating = parseInt(review.rating || 0);
                        const comment = review.comment || review.text || review.content || 'No comment provided';
                        const createdAt = review.created_at || review.createdAt || review.date || new Date().toISOString();
                        
                        // Generate stars HTML
                        let starsHtml = '';
                        for (let i = 1; i <= 5; i++) {
                            starsHtml += `<i class="fa${i <= rating ? 's' : 'r'} fa-star"></i>`;
                        }
                        
                        const reviewCard = document.createElement('div');
                        reviewCard.classList.add('card', 'review-card', 'mb-3');
                        reviewCard.innerHTML = `
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="card-subtitle mb-0">${username}</h6>
                                    <div class="text-warning">${starsHtml}</div>
                                </div>
                                <p class="card-text">${comment}</p>
                                <div class="text-muted small">
                                    ${new Date(createdAt).toLocaleDateString('en-US', {
                                        year: 'numeric', 
                                        month: 'long', 
                                        day: 'numeric'
                                    })}
                                </div>
                            </div>
                        `;
                        container.appendChild(reviewCard);
                    });
                } else {
                    alert('Error fetching reviews');
                }
            })
    }
});
</script>
