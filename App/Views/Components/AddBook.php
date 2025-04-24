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
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script>
        document.addEventListener("DOMContentLoaded", () => {
            const dropdown = document.getElementById("customDropdown");
            const dropdownOptions = document.getElementById("dropdownOptions");
            const select = document.getElementById("category");
            const condition = document.getElementById("condition");
            const submitForm = document.getElementById("bookForm");
            const clearForm = document.getElementById("clearForm");

            clearForm.addEventListener("click", () => {
                submitForm.reset();
                updateDisplay();
                updateOptionStyles();
            });

            submitForm.addEventListener("submit", (event) => {
                event.preventDefault();

                const title = document.getElementById("title").value;
                const author = document.getElementById("author").value;
                const categories = Array.from(select.selectedOptions).map(option => option.value);
                const year = document.getElementById("year").value;
                const description = document.getElementById("description").value;
                const bookPdf = document.getElementById("bookPdf").files[0];

                const formData = new FormData();
                formData.append("title", title);
                formData.append("author", author);
                formData.append("categories", JSON.stringify(categories));
                formData.append("year", year);
                formData.append("description", description);
                formData.append("bookPdf", bookPdf);

                axios.post("/api/v1/books", formData)
                    .then(response => {
                        submitForm.reset();
                        updateDisplay();
                        updateOptionStyles();
                    })
                    .catch(error => {
                        alert("An error occurred. Please try again.");
                    });
            });

            condition.addEventListener("change", (event) => {
                const value = event.target.value;
                if (value === "undefined") {
                    document.getElementById("year").disabled = true;
                } else {
                    document.getElementById("year").disabled = false;
                }
            });

            dropdown.addEventListener("click", () => {
                dropdownOptions.classList.toggle("hidden");
            });

            dropdownOptions.addEventListener("click", (event) => {
                const target = event.target;
                if (target.tagName === "LI") {
                    const value = target.getAttribute("data-value");
                    const option = Array.from(select.options).find(opt => opt.value === value);

                    const selectedCount = Array.from(select.options).filter(opt => opt.selected).length;

                    if (!option.selected && selectedCount >= 3) {
                        target.classList.add("disabled");
                        return;
                    }

                    option.selected = !option.selected;

                    updateDisplay();
                    updateOptionStyles();
                }
            });

            const updateDisplay = () => {
                const selected = Array.from(select.selectedOptions).map(option => option.text).join(", ");
                dropdown.textContent = selected || "Select categories";
            };

            const updateOptionStyles = () => {
                const selectedCount = Array.from(select.options).filter(opt => opt.selected).length;
                Array.from(dropdownOptions.children).forEach(item => {
                    const value = item.getAttribute("data-value");
                    const option = Array.from(select.options).find(opt => opt.value === value);

                    if (!option.selected && selectedCount >= 3) {
                        item.classList.add("dull");
                    } else {
                        item.classList.remove("dull");
                    }
                });
            };

            document.addEventListener("click", (event) => {
                if (!dropdown.parentElement.contains(event.target)) {
                    dropdownOptions.classList.add("hidden");
                }
            });

            const elementsWithDescriptions = document.querySelectorAll("input, textarea, .custom-select-wrapper");

            elementsWithDescriptions.forEach(element => {
                element.addEventListener("mouseenter", showTooltip);
                element.addEventListener("mouseleave", hideTooltip);
            });

            function showTooltip(event) {
                const target = event.target;
                const tooltip = target.parentElement.querySelector(".tooltip");

                if (tooltip) {
                    const rect = target.getBoundingClientRect();
                    tooltip.style.top = `${window.scrollY + rect.top - tooltip.offsetHeight - 10}px`;
                    tooltip.style.left = `${window.scrollX + rect.left}px`;

                    tooltip.classList.add("visible");
                }
            }

            function hideTooltip(event) {
                const target = event.target;
                const tooltip = target.parentElement.querySelector(".tooltip");

                if (tooltip) {
                    tooltip.classList.remove("visible");
                }
            }
        });
</script>