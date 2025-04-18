<?php
/**
 * Add Book Form Component
 * 
 * @param string $formAction Optional - URL where the form will POST data (default: '/api/v1/books/add')
 * @param array $categories Optional - List of available categories (default: hardcoded)
 */

$formAction = $formAction ?? '/api/v1/books/add';
$categories = $categories ?? [
    'Electronics',
    'Mathematics',
    'Programming',
    'Robotics',
    'Networking',
    'telecommunications', 
    'Physics',
    'Computer Science', 
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
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
            </div>

            <div class="mb-3">
                <label for="bookPdf" class="form-label">Book</label>
                <input type="file" class="form-control" id="bookPdf" name="bookPdf" accept="application/pdf" required>
                <small class="form-text text-muted">Upload a PDF file.</small>
            </div>

            <div class="text-center">
                <button type="submit" class="btn btn-primary">Insert</button>
                <button type="reset" class="btn btn-secondary" id="clearForm">Clear</button>
            </div>
        </form>
    </div>
</div>
