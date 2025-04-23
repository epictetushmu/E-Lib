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
