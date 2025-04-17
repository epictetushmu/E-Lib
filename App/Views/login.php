<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <link rel="stylesheet" href="/styles/userForm.css"> 
</head>
<body class="d-flex flex-column min-vh-100">
    <?php 
        include 'Partials/Header.php'; 
        include 'Components/LoginForm.php';
        include 'Partials/Footer.php';
    ?>
</body>
</html>