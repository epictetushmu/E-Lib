<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Books | Epictetus Library</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/styles/add_book.css">
    <link rel="stylesheet" href="/styles/home.css"> 

</head>
<body class="d-flex flex-column min-vh-100">

    <?php
        include 'Partials/Header.php';
        include 'Components/ViewBooks.php';
        include 'Partials/Footer.php';
    ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>

document.addEventListener('DOMContentLoaded', function() {
    if (window.location.pathname.includes('/dashboard')) {
        checkAdminAccess();
    }
});

function checkAdminAccess() {
    // Check if user is admin
    const isAdmin = localStorage.getItem('isAdmin') === 'true' || 
                    sessionStorage.getItem('isAdmin') === 'true';
    
    // If not admin, redirect to home page
    if (!isAdmin) {
        console.log('Unauthorized access to admin dashboard detected');
        window.location.href = '/';
    }
}

</script>
  
</body>
</html>
