<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Book | Epictetus Library</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/styles/add_book.css">
</head>
<body class="d-flex flex-column min-vh-100">

    <?php
        include 'Partials/Header.php';
        include 'Components/AddBook.php';
        include 'Partials/Footer.php';
    ?>
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

                axios.post("/api/v1/books", formData)
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
