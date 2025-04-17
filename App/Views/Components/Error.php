<?php
// Example usage: include 'components/error.php';
// Optionally set $errorCode and $errorMessage before including
$errorCode = $errorCode ?? 404;
$errorMessage = $errorMessage ?? 'Oops! The page you are looking for does not exist.';
?>

<div class="container text-center py-5">
    <div class="error-container">
        <h1 class="display-1 fw-bold text-danger"><?= htmlspecialchars($errorCode) ?></h1>
        <p class="fs-3 text-muted">Error</p>
        <p class="fs-4 text-muted">Something went wrong!</p>
        <p class="fs-4 text-muted"><?= htmlspecialchars($errorMessage) ?></p>
        <a href="/" class="btn btn-primary mt-3">
            <i class="fas fa-home me-2"></i>Return to Home
        </a>
    </div>
</div>
