<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Epictetus Library - Home of Knowledge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/styles/home.css"> 
    
</head>
<body class="d-flex flex-column min-vh-100">

    <?php 
        include 'Partials/Header.php';
        include 'Components/Hero.php';
        if (!$isLoggedIn): ?>
            <div id="loginPopup" style="display:none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
                background-color: rgba(0,0,0,0.5); z-index: 1050;">
                <?php include 'Components/LoginForm.php'; ?>
            </div>
        <?php endif; 
        include 'Components/Featured.php';
        include 'Partials/Footer.php';
    ?>

<!-- Dependencies -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

    <script>
    let hasScrolled = false;

    window.addEventListener('scroll', function () {
        if (!hasScrolled && window.scrollY > 50) {
            hasScrolled = true;

            // Only show popup if it's included in DOM
            const popup = document.getElementById('loginPopup');
            if (popup) {
                popup.style.display = 'flex'; // or block based on your modal style
            }
            this.sessionStorage.setItem('hasScrolled', 'true');
        }
    });

    // Optional: close popup
    function closeLoginPopup() {
        document.getElementById('loginPopup').style.display = 'none';
    }
</script>
</body>
</html>