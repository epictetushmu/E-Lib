<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($book['title'] ?? 'Book Details') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/styles/book_details.css">
</head>
<body class="d-flex flex-column min-vh-100">
    <?php 
        include 'Partials/Header.php';
        include 'Components/BookDetails.php';
        include 'Partials/Footer.php';
    ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', () => {
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
            
            // Review submission
            const reviewForm = document.getElementById('reviewForm');
            if (reviewForm) {
                reviewForm.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    
                    const bookId = document.getElementById('bookId').value;
                    const rating = document.getElementById('rating').value;
                    const comment = document.getElementById('comment').value;
                    
                    if (rating === '0') {
                        alert('Please select a rating');
                        return;
                    }
                    
                    try {
                        const response = await axios.post('/api/v1/review', {
                            book_id: bookId,
                            rating: rating,
                            comment: comment
                        });
                        
                        if (response.data.status === 'success') {
                            alert('Review submitted successfully');
                            location.reload();
                        } else {
                            alert(response.data.message || 'Failed to submit review');
                        }
                    } catch (error) {
                        console.error('Error submitting review:', error);
                        alert('An error occurred while submitting your review');
                    }
                });
            }
            
            // Borrow book functionality
            const borrowBtn = document.getElementById('borrowBtn');
            if (borrowBtn) {
                borrowBtn.addEventListener('click', async () => {
                    const bookId = document.getElementById('bookId').value;
                    
                    try {
                        const response = await axios.post('/api/v1/borrow', {
                            book_id: bookId
                        });
                        
                        if (response.data.status === 'success') {
                            alert('Book borrowed successfully');
                            borrowBtn.classList.add('disabled');
                            borrowBtn.textContent = 'Borrowed';
                        } else {
                            alert(response.data.message || 'Failed to borrow book');
                        }
                    } catch (error) {
                        console.error('Error borrowing book:', error);
                        alert('An error occurred while borrowing the book');
                    }
                });
            }
            
            // Save to list functionality
            const saveBtn = document.getElementById('saveBtn');
            if (saveBtn) {
                saveBtn.addEventListener('click', async () => {
                    const bookId = document.getElementById('bookId').value;
                    
                    try {
                        const response = await axios.post('/api/v1/save-book', {
                            book_id: bookId
                        });
                        
                        if (response.data.status === 'success') {
                            alert('Book saved to your list');
                            saveBtn.textContent = 'Saved to List';
                            saveBtn.disabled = true;
                        } else {
                            alert(response.data.message || 'Failed to save book');
                        }
                    } catch (error) {
                        console.error('Error saving book:', error);
                        alert('An error occurred while saving the book');
                    }
                });
            }
        });
    </script>
</body>
</html>
