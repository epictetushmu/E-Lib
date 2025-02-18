<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($book['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .book-cover {
            max-height: 400px;
            object-fit: cover;
            object-position: top;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .book-details {
            margin-top: 20px;
        }
        .container {
            max-width: 900px;
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-4">
                <img src="<?= htmlspecialchars($book['cover']) ?>" alt="<?= htmlspecialchars($book['title']) ?> cover" class="img-fluid book-cover">
            </div>
            <div class="col-md-8 book-details">
                <h1 class="fw-bold"><?= htmlspecialchars($book['title']) ?></h1>
                <p class="text-muted">"<?= htmlspecialchars($book['description']) ?>"</p>
                <p><strong>Author:</strong> <?= htmlspecialchars($book['author']) ?></p>
                <p><strong>Published:</strong> <?= htmlspecialchars($book['published_date']) ?></p>
                <a href="/E-Lib/" class="btn btn-primary mt-3">
                    <i class="fas fa-arrow-left me-2"></i>Back to Home
                </a>
            </div>
        </div>
    </div>
    <footer class="mt-auto py-3 bg-light text-center">
        <p class="mb-0">&copy; 2025 Epictetus Library. All rights reserved.</p>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
