<?php
/**
 * Add Book Form Component
 * 
 * @param string $formAction Optional - URL where the form will POST data (default: '/api/v1/books/add')
 * @param array $categories Optional - List of available categories (default: hardcoded)
 */

$formAction = $formAction ?? '/api/v1/books/add';
$categories = $categories ?? [
    'Literature',
    'Science Fiction',
    'Non-Fiction',
    'Fantasy'
];
?>

<div class="container mt-5">
    <h2 class="text-center fw-bold">Add a New Book</h2>

    <div class="card p-4 shadow mt-4">
        <form id="bookForm" method="POST" action="<?= htmlspecialchars($formAction) ?>" enctype="multipart/form-data">
            <?php if (function_exists('csrf_field')): ?>
                <?= csrf_field() ?>
            <?php endif; ?>

            <div class="mb-3">
                <label for="title" class="form-label">Book Title</label>
                <input type="text" class="form-control" id="title" name="title" required>
            </div>

            <div class="mb-3">
                <label for="author" class="form-label">Author</label>
                <input type="text" class="form-control" id="author" name="author">
            </div>

            <div class="mb-3">
                <label for="category" class="form-label">Category</label>
                <select class="form-select" id="category" name="category[]" multiple>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                    <?php endforeach; ?>
                </select>
                <small class="form-text text-muted">Hold Ctrl (Cmd on Mac) to select multiple categories.</small>
            </div>

            <div class="mb-3">
                <label for="year" class="form-label">Publication Year</label>
                <input type="number" class="form-control" id="year" name="year" min="0" max="<?= date('Y') ?>">
            </div>

            <div class="mb-3">
                <label for="condition" class="form-label">Condition</label>
                <select class="form-select" id="condition" name="condition">
                    <option value="New">New</option>
                    <option value="Good">Good</option>
                    <option value="Fair">Fair</option>
                    <option value="Poor">Poor</option>
                    <option value="undefined">Unknown</option>
                </select>
            </div>

            <div class="mb-3">
                <label for="copies" class="form-label">Number of Copies</label>
                <input type="number" class="form-control" id="copies" name="copies" min="1" required>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
            </div>

            <div class="mb-3">
                <label for="cover" class="form-label">Cover Image</label>
                <input type="file" class="form-control" id="cover" name="cover">
            </div>

            <div class="text-center">
                <button type="submit" class="btn btn-primary">Insert</button>
                <button type="reset" class="btn btn-secondary" id="clearForm">Clear</button>
            </div>
        </form>
    </div>
</div>
