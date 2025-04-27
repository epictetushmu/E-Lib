<?php
/**
 * Advanced Search Form Component
 * 
 * @param array $filters Current filter values (title, author, category)
 * @param array $categories List of available categories
 * @param string $formId Optional - ID for the form (default: 'advancedSearchForm')
 * @param string $searchUrl Optional - URL to submit the search to (default: '/search')
 */

// Set default values for parameters
$filters = $filters ?? [];
$formId = $formId ?? 'advancedSearchForm';
$searchUrl = $searchUrl ?? '/search_results';
$uniqueId = uniqid('search_'); // Generate a unique ID prefix to avoid conflicts
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

<div class="card mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0">Advanced Search</h5>
    </div>
    <div class="card-body">
        <form id="<?= htmlspecialchars($formId) ?>" method="GET" action="<?= htmlspecialchars($searchUrl) ?>">
            <!-- CSRF protection if needed -->
            <?php if (function_exists('csrf_field')): ?>
                <?= csrf_field() ?>
            <?php else: ?>
                <!-- CSRF token not available -->
            <?php endif; ?>
            
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="<?= $uniqueId ?>_title" class="form-label">Title</label>
                    <input type="text" class="form-control" id="<?= $uniqueId ?>_title" name="title" value="<?= htmlspecialchars($filters['title'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label for="<?= $uniqueId ?>_author" class="form-label">Author</label>
                    <input type="text" class="form-control" id="<?= $uniqueId ?>_author" name="author" value="<?= htmlspecialchars($filters['author'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label for="<?= $uniqueId ?>_category" class="form-label">Category</label>
                    <select class="form-select" id="<?= $uniqueId ?>_category" name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <?php 
                            // Handle both string categories and object/array categories
                            $categoryId = is_array($category) ? ($category['id'] ?? $category) : $category;
                            $categoryName = is_array($category) ? ($category['name'] ?? $category) : $category;
                            $isSelected = ($filters['category'] ?? '') == $categoryId;
                            ?>
                            <option value="<?= htmlspecialchars($categoryId) ?>" <?= $isSelected ? 'selected' : '' ?>>
                                <?= htmlspecialchars($categoryName) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 text-center">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <button type="reset" class="btn btn-outline-secondary">Reset</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    // Use the unique form ID to avoid conflicts
    const form = document.getElementById('<?= htmlspecialchars($formId) ?>');
    
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const title = document.getElementById('<?= $uniqueId ?>_title').value.trim();
            const author = document.getElementById('<?= $uniqueId ?>_author').value.trim();
            const category = document.getElementById('<?= $uniqueId ?>_category').value;
            
            let url = '<?= htmlspecialchars($searchUrl) ?>?';
            let params = [];
            
            if (title) params.push(`title=${encodeURIComponent(title)}`);
            if (author) params.push(`author=${encodeURIComponent(author)}`);
            if (category) params.push(`category=${encodeURIComponent(category)}`);
            
            window.location.href = url + params.join('&');
        });
    }
})();
document.querySelector('button[type="reset"]').addEventListener('click', function(e) {
    // Reset search query URL
    window.location.href = '<?= htmlspecialchars($searchUrl) ?>';
});

</script>