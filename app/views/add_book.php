<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Book | Epictetus Library</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/E-Lib/app/styles/add_book.css">
    <style>
        .custom-select-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
        }

        .custom-select-display {
            border: 1px solid #ccc;
            padding: 10px;
            cursor: pointer;
            background: #fff;
            border-radius: 5px;
        }

        .custom-dropdown {
            position: absolute;
            width: 100%;
            background: #fff;
            border: 1px solid #ccc;
            border-radius: 5px;
            list-style: none;
            padding: 0;
            margin: 0;
            display: none;
            z-index: 10;
        }

        .custom-dropdown li {
            padding: 10px;
            cursor: pointer;
        }
        
        .custom-dropdown li:hover {
            background: #f0f0f0;
        }

        .hidden {
            display: none;
        }

        .selected {
            font-weight: bold;
            background: #d1e7dd;
        }

        .dull {
            opacity: 0.5;
        }

        .tooltip {
            position: absolute;
            background: #333;
            color: #fff;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            display: none;
        }

        .tooltip.visible {
            display: block;
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">
    
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="/E-Lib/">
                <i class="fas fa-book-open me-2"></i>Epictetus Library
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="/E-Lib/">Home</a></li>
                    <li class="nav-item"><a class="nav-link active" href="/E-Lib/add-book">Add Book</a></li>
                    <li class="nav-item"><a class="nav-link" href="/E-Lib/search_results">Search</a></li>
                </ul>
                <form class="d-flex" id="searchForm">
                    <div class="input-group">
                        <input type="search" id="search" class="form-control" placeholder="Search titles..." aria-label="Search">
                        <button type="submit" class="btn btn-success"><i class="fas fa-search"></i></button>
                    </div>
                </form>
            </div>
        </div>
    </nav>

    <!-- Add Book Form Section -->
    <div class="container mt-5">
        <h2 class="text-center fw-bold">Add a New Book</h2>
        <div class="card p-4 shadow mt-4">
            <form id="bookForm">
                <div class="mb-3">
                    <label for="title" class="form-label">Book Title</label>
                    <input type="text" class="form-control" id="title" required>
                </div>
                <div class="mb-3">
                    <label for="author" class="form-label">Author</label>
                    <input type="text" class="form-control" id="author">
                </div>
                <div class="mb-3">
                    <label for="category" class="form-label">Category</label>
                    <div class="custom-select-wrapper" data-description="Select one or more categories">
                        <div id="customDropdown" class="custom-select-display">Select categories</div>
                        <ul id="dropdownOptions" class="custom-dropdown hidden">
                            <li data-value="Literature">Literature</li>
                            <li data-value="Science Fiction">Science Fiction</li>
                            <li data-value="Non-Fiction">Non-Fiction</li>
                            <li data-value="Fantasy">Fantasy</li>
                        </ul>
                        <select id="category" class="hidden" multiple>
                            <option value="Literature">Literature</option>
                            <option value="Science Fiction">Science Fiction</option>
                            <option value="Non-Fiction">Non-Fiction</option>
                            <option value="Fantasy">Fantasy</option>
                        </select>
                    </div>
                    <div class="tooltip hidden">Select one or more categories</div>
                </div>
                <div class="mb-3">
                    <label for="year" class="form-label">Publication Year</label>
                    <input type="number" class="form-control" id="year">
                </div>
                <div class="mb-3">
                    <label for="condition" class="form-label">Condition</label>
                    <select class="form-select" id="condition">
                        <option value="New">New</option>
                        <option value="Good">Good</option>
                        <option value="Fair">Fair</option>
                        <option value="Poor">Poor</option>
                        <option value="undefined">Unknown</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="copies" class="form-label">Number of Copies</label>
                    <input type="number" class="form-control" id="copies" required>
                </div>
                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" rows="3"></textarea>
                </div>
                <div class="mb-3">
                    <label for="cover" class="form-label">Cover Image</label>
                    <input type="file" class="form-control" id="cover">
                </div>
                <div class="text-center">
                    <button type="submit"  class="btn btn-primary">Insert</button>
                    <button type="button" class="btn btn-secondary" id="clearForm">Clear</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <footer class="py-4 mt-auto bg-dark text-light text-center">
        <div class="container">
            <p>&copy; 2025 Epictetus Library. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
                const condition = document.getElementById("condition").value;
                const copies = document.getElementById("copies").value;
                const description = document.getElementById("description").value;
                const cover = document.getElementById("cover").files[0];

                const formData = new FormData();
                formData.append("title", title);
                formData.append("author", author);
                formData.append("categories", JSON.stringify(categories));
                formData.append("year", year);
                formData.append("condition", condition);
                formData.append("copies", copies);
                formData.append("description", description);
                formData.append("cover", cover);

                axios.post("/E-Lib/api/add_book", formData)
                    .then(response => {
                        alert("Book added successfully!");
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
</body>
</html>
