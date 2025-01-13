// import axios from 'axios';   

export async function handleFormSubmit() {  
    // event.preventDefault();

    const titleField = document.getElementById("title");
    const authorField = document.getElementById("author"); 
    const descriptionField = document.getElementById("description");
    const yearField = document.getElementById("year");
    const copiesField = document.getElementById("copies");
    const categoryField = document.getElementById("category");
    const conditionField = document.getElementById("condition");

    const titleError = document.getElementById("titleError");
    const authorError = document.getElementById("authorError"); 
    const descriptionError = document.getElementById("descriptionError");
    const yearError = document.getElementById("yearError");
    const copiesError = document.getElementById("copiesError");

    const title = titleField.value.trim();
    const author = authorField.value.trim(); 
    const description = descriptionField.value.trim();
    const year = yearField.value;
    const copies = copiesField.value;
    const category = categoryField.value;
    const condition = conditionField.value;
    console.log(title, author, description, year, copies, category, condition);
    let isValid = true;

    // if (!title || !/^[a-zA-Z0-9,-]+$/.test(title)) {
    //     titleError.textContent = "Title can only contain letters, numbers, commas, and hyphens.";
    //     titleField.style.border = "1px solid red";
    //     isValid = false;
    // } else {
    //     titleError.textContent = "";
    //     titleField.style.border = "";
    // }

    // if (description.length > 300) {
    //     descriptionError.textContent = "Description can't be more than 300 characters long.";
    //     descriptionField.style.border = "1px solid red";
    //     isValid = false;
    // } else {
    //     descriptionError.textContent = "";
    //     descriptionField.style.border = "";
    // }

    // if (year < 0 || year > new Date().getFullYear()) {
    //     yearError.textContent = "Year of publication must be between 0 and the current year.";
    //     yearField.style.border = "1px solid red";
    //     isValid = false;
    // } else {
    //     yearError.textContent = "";
    //     yearField.style.border = "";
    // }

    // if (copies < 0) {
    //     copiesError.textContent = "Number of copies must be a positive number.";
    //     copiesField.style.border = "1px solid red";
    //     isValid = false;
    // } else {
    //     copiesError.textContent = "";
    //     copiesField.style.border = "";
    // }

    if (isValid) {
        const api = axios.create({
            baseURL: 'http://localhost/WebUni/api/Books.php',  // Replace with your actual API URL
            headers: {
                'Content-Type': 'application/json',
            },
        });

        // Submit form 
        const addBook = async () => {
            try {
                const response = await api.post('?api=addBook', {
                    title: title,
                    author: author, 
                    description: description,
                    year: year,
                    copies: copies,
                    category: category,
                    condition: condition
                });
                console.log("Form submitted successfully with data:", response.data);
                // alert("Book added successfully!");
                // document.getElementById("bookForm").reset();
            } catch (error) {
                console.error('Error adding book:', error.response);
                alert("Error adding book. Please try again.");
            }
        };

        addBook();
    }
}

export function clearForm(event) {
    // event.preventDefault();
    document.getElementById("bookForm").reset();
    const fields = document.querySelectorAll("#bookForm input, #bookForm textarea, #bookForm select");
    fields.forEach(field => {
        field.style.border = "";
    });
    const errors = document.querySelectorAll("#bookForm .error");
    errors.forEach(error => {
        error.textContent = "";
    });
    console.log("Form cleared");
}
