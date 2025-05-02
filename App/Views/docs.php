<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Lib Documentation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/styles/book_details.css">
    <link rel="stylesheet" href="/styles/home.css"> 

</head>
<body class="d-flex flex-column min-vh-100">
    <?php 
        include 'Partials/Header.php';
        include 'Components/Docs.php';
        include 'Partials/Footer.php';
    ?>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Initialize accordion elements -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Ensure all Bootstrap components are properly initialized
            var accordionItems = document.querySelectorAll('.accordion-item');
            if (accordionItems) {
                console.log('Accordion items found:', accordionItems.length);
            }
            
            // For debugging purposes
            var technicalAccordion = document.getElementById('technicalAccordion');
            if (technicalAccordion) {
                console.log('Technical accordion found');
            } else {
                console.error('Technical accordion not found in DOM');
            }
        });
    </script>
</body>
</html>