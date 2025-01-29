<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Book List</title>
</head>
<body>
    <h1>Available Books</h1>
    <ul>
        <?php foreach ($books as $book): ?>
            <li><a href="/book/<?= $book['id'] ?>"><?= $book['title'] ?></a></li>
        <?php endforeach; ?>
    </ul>
    <a href="/add-book">Add a new book</a>
</body>
</html>
