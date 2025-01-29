<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $book['title'] ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <h1><?= $book['title'] ?></h1>
        <p><?= $book['description'] ?></p>
        <p><strong>Author:</strong> <?= $book['author'] ?></p>
        <p><strong>Published:</strong> <?= $book['published_date'] ?></p>
    </div>
</body>
</html>