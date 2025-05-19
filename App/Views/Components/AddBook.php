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
                <div class="input-group">
                    <input type="text" class="form-control" id="isbnRaw" placeholder="Enter ISBN-10 or ISBN-13" style="display: none;">
                    <input type="text" class="form-control" id="isbnFormatted" name="isbn" placeholder="Enter ISBN-10 or ISBN-13">
                </div>
                <div id="isbnHelp" class="form-text">
                    <span id="isbnValidation" class="text-danger"></span>
                </div>
            </div>            

            <div class="mb-3">
                <label for="bookFile" class="form-label">Book File</label>
                <input type="file" class="form-control" id="bookFile" name="bookFile" 
                       accept=".pdf,.pptx,.ppt,.epub,.mobi,.azw,.azw3,.djvu,.doc,.docx" required>
                <small class="form-text text-muted">
                    Supported formats: PDF, PowerPoint (PPT/PPTX), E-books (EPUB, MOBI, AZW), DJVU, and Word documents (DOC/DOCX)
                </small>
            </div>
            
            <!-- Downloadable option -->
            <div class="mb-3">
                <label class="form-label">Downloadable</label>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="downloadable" id="downloadableYes" value="true" checked>
                    <label class="form-check-label" for="downloadableYes">Yes</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="downloadable" id="downloadableNo" value="false">
                    <label class="form-check-label" for="downloadableNo">No</label>
                </div>
                <small class="form-text text-muted">Whether users can download this book's PDF.</small>
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
        document.getElementById("isbnValidation").textContent = "";
    });

    // ISBN validation and formatting
    const isbnFormatted = document.getElementById("isbnFormatted");
    const isbnValidation = document.getElementById("isbnValidation");
    
    // Store raw value to use for validation
    let rawIsbn = "";
    
    isbnFormatted.addEventListener("input", function(e) {
        const cursorPosition = this.selectionStart;
        
        // Get input value and clean it
        let value = this.value.replace(/[^0-9X]/gi, '');
        
        // Force uppercase X (for ISBN-10)
        value = value.replace(/x/g, 'X');
        
        // Update raw ISBN for validation
        rawIsbn = value;
        
        // Limit length
        if (value.length > 13) {
            value = value.slice(0, 13);
            rawIsbn = value;
        }
        
        // Format for display
        let formatted = formatISBN(value);
        
        // Only update the field value if it's different (to avoid cursor issues)
        if (this.value !== formatted) {
            this.value = formatted;
            
            // Try to maintain cursor position after formatting
            // This is approximate since formatting changes the string length
            let newPosition = cursorPosition;
            // Add adjustment for hyphen positions
            if (value.length >= 1 && cursorPosition > 1) newPosition++;
            if (value.length >= 4 && cursorPosition > 4) newPosition++;
            if (value.length >= 7 && cursorPosition > 7) newPosition++;
            if (value.length >= 12 && cursorPosition > 12) newPosition++;
            
            this.setSelectionRange(newPosition, newPosition);
        }
        
        // Validate ISBN
        let validationMessage = validateISBN(rawIsbn);
        isbnValidation.textContent = validationMessage;
    });
    
    // Format ISBN with hyphens based on standard rules
    function formatISBN(isbn) {
        if (!isbn) return '';
        
        if (isbn.length <= 1) return isbn;
        
        if (isbn.length <= 4) {
            // Partial ISBN-10/13: X-...
            return isbn.substring(0, 1) + 
                  (isbn.length > 1 ? '-' + isbn.substring(1) : '');
        }
        
        if (isbn.length <= 7) {
            // Partial ISBN-10/13: X-XXX-...
            return isbn.substring(0, 1) + '-' + 
                   isbn.substring(1, 4) + 
                  (isbn.length > 4 ? '-' + isbn.substring(4) : '');
        }
        
        if (isbn.length <= 10) {
            if (isbn.length === 10) {
                // Complete ISBN-10: X-XXX-XXXXX-X
                return isbn.substring(0, 1) + '-' + 
                       isbn.substring(1, 4) + '-' + 
                       isbn.substring(4, 9) + '-' + 
                       isbn.substring(9, 10);
            } else {
                // Partial ISBN-10: X-XXX-XXXXX...
                return isbn.substring(0, 1) + '-' + 
                       isbn.substring(1, 4) + '-' + 
                       isbn.substring(4);
            }
        } else {
            if (isbn.length === 13) {
                // Complete ISBN-13: XXX-X-XXX-XXXXX-X
                return isbn.substring(0, 3) + '-' + 
                       isbn.substring(3, 4) + '-' + 
                       isbn.substring(4, 7) + '-' + 
                       isbn.substring(7, 12) + '-' + 
                       isbn.substring(12, 13);
            } else {
                // Partial ISBN-13: XXX-X-XXX-XXXXX...
                return isbn.substring(0, 3) + '-' + 
                       isbn.substring(3, 4) + '-' + 
                       isbn.substring(4, 7) + '-' + 
                       isbn.substring(7);
            }
        }
    }
    
    // Basic ISBN validation
    function validateISBN(isbn) {
        if (!isbn) return '';
        
        // For incomplete ISBNs, just show a message about expected length
        if (isbn.length < 10) {
            return 'Continue entering digits (ISBN-10: 10 digits, ISBN-13: 13 digits)';
        }
        
        if (isbn.length !== 10 && isbn.length !== 13) {
            return 'ISBN must be 10 or 13 characters long';
        }
        
        if (isbn.length === 10) {
            // Only last character can be 'X' in ISBN-10
            if (/[X]/i.test(isbn.substring(0, 9))) {
                return 'Only the last character of ISBN-10 can be X';
            }
            
            // Validate ISBN-10 checksum
            let sum = 0;
            for (let i = 0; i < 9; i++) {
                sum += parseInt(isbn.charAt(i)) * (10 - i);
            }
            
            let checkDigit = 11 - (sum % 11);
            if (checkDigit === 11) checkDigit = 0;
            if (checkDigit === 10) checkDigit = 'X';
            
            const lastChar = isbn.charAt(9).toUpperCase();
            if (lastChar !== checkDigit.toString()) {
                return 'Invalid ISBN-10 checksum';
            }
            
            return 'Valid ISBN-10';
        }
        
        if (isbn.length === 13) {
            // ISBN-13 cannot have 'X'
            if (/[X]/i.test(isbn)) {
                return 'ISBN-13 cannot contain X';
            }
            
            // Validate ISBN-13 checksum
            let sum = 0;
            for (let i = 0; i < 12; i++) {
                sum += parseInt(isbn.charAt(i)) * (i % 2 === 0 ? 1 : 3);
            }
            
            let checkDigit = 10 - (sum % 10);
            if (checkDigit === 10) checkDigit = 0;
            
            if (parseInt(isbn.charAt(12)) !== checkDigit) {
                return 'Invalid ISBN-13 checksum';
            }
            
            return 'Valid ISBN-13';
        }
        
        return '';
    }

    form.addEventListener("submit", (event) => {
        // When submitting, extract the raw ISBN (without hyphens)
        event.preventDefault();

        const title = document.getElementById("title").value;
        const author = document.getElementById("author").value;
        const selectedCategories = Array.from(document.getElementById("category").selectedOptions).map(option => option.value);
        const year = yearInput.value;
        const description = document.getElementById("description").value;
        
        // Get the raw ISBN without hyphens for storage
        const isbn = rawIsbn; 
        
        const bookPdf = document.getElementById("bookFile").files[0];
        const downloadable = document.querySelector('input[name="downloadable"]:checked').value;
        const token = localStorage.getItem("authToken") || sessionStorage.getItem("authToken");
        
        // Validate ISBN before submission
        const isbnError = validateISBN(isbn);
        if (isbn && (isbnError !== 'Valid ISBN-10' && isbnError !== 'Valid ISBN-13')) {
            alert("Please enter a valid ISBN before submitting.");
            return;
        }

        const formData = new FormData();
        formData.append("title", title);
        formData.append("author", author);
        formData.append("categories", JSON.stringify(selectedCategories));
        formData.append("year", year);
        formData.append("isbn", isbn); 
        formData.append("description", description);
        formData.append("bookFile", bookFile);
        formData.append("downloadable", downloadable);

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
