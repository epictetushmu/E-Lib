import { handleFormSubmit } from './logic/insert_book.js';
import { clearForm } from './logic/insert_book.js';


// Handle the form submission
document.getElementById("bookForm").addEventListener("submit", (event) => {
    event.preventDefault();

    const formData = {
        title: document.getElementById("title").value,
        author: document.getElementById("author").value,
        year: parseInt(document.getElementById("year").value, 10),
        copies: parseInt(document.getElementById("copies").value, 10),
        categories: Array.from(document.getElementById("category").selectedOptions)
                          .map(option => option.value)
                          .filter(value => value !== ""),
        description: document.getElementById("description").value,
        image: document.getElementById("cover").files[0] // Use `.files[0]` to get the file object
    };

    handleFormSubmit(formData);

    alert("Book added successfully!");
    clearForm();
});


document.getElementById("clearForm").addEventListener("click", clearForm);

