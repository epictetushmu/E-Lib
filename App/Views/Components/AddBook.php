<div class="container mt-5">
    <h2 class="text-center fw-bold">Add a New Book</h2>

    <div class="card p-4 shadow mt-4">
        <form id="bookForm" method="POST" action="" enctype="multipart/form-data">
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
                    <option value="Electronics">Electronics</option>
                    <option value="Mathematics">Mathematics</option>
                    <option value="Programming">Programming</option>
                    <option value="Robotics">Robotics</option>
                    <option value="Networking">Networking</option>
                    <option value="Telecommunications">Telecommunications</option>
                    <option value="Physics">Physics</option>
                    <option value="Computer Science">Computer Science</option>
                </select>
                <small class="form-text text-muted">Hold Ctrl (Cmd on Mac) to select multiple categories.</small>
            </div>

            <div class="mb-3">
                <label for="year" class="form-label">Publication Year</label>
                <input type="number" class="form-control" id="year" name="year" min="0">
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
            </div>
            
            <div class="mb-3">
                <label for="isbn" class="form-label">ISBN</label>
                <textarea type="number" class="form-control" id="isbn" name="isbn" rows="3"></textarea>
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
    const form = document.getElementById("bookForm");

    const yearInput = document.getElementById("year");
    yearInput.max = new Date().getFullYear();

    const clearForm = document.getElementById("clearForm");
    clearForm.addEventListener("click", () => {
        form.reset();
    });

    form.addEventListener("submit", (event) => {
        event.preventDefault();

        const title = document.getElementById("title").value;
        const author = document.getElementById("author").value;
        const selectedCategories = Array.from(document.getElementById("category").selectedOptions).map(option => option.value);
        const year = yearInput.value;
        const description = document.getElementById("description").value;
        const isbn = document.getElementById("isbn").value;
        const bookPdf = document.getElementById("bookPdf").files[0];
        const token = localStorage.getItem("authToken") || sessionStorage.getItem("authToken");

        const formData = new FormData();
        formData.append("title", title);
        formData.append("author", author);
        formData.append("categories", JSON.stringify(selectedCategories));
        formData.append("year", year);
        formData.append("isbn", isbn); 
        formData.append("description", description);
        formData.append("bookPdf", bookPdf);

        axios.post("/api/v1/books", formData, {
            headers: {
                "Authorization": `Bearer ${token}`
            }
        })
        .then(response => {
            alert("Book added successfully!");
            form.reset();
        })
        .catch(error => {
            alert("An error occurred. Please try again.");
        });
    });
});
</script>
