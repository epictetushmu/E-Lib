
export function handleFormSubmit(formData) {  
    if (/[^a-zA-Z0-9,-]+$/.test(formData.title)) {
        titleError.textContent = "Title can only contain letters, numbers, and commas.";
        titleField.style.border = "1px solid red";
        console.log("Title can only contain letters, numbers, and commas.");
    }
    else if (formData.description.length > 300) {
        descriptionError.textContent = "Description cant be more than 300 characters long.";
        descriptionField.style.border = "1px solid red";
        console.log("Description cant be more than 300 characters long.");
    }
    else if (formData.year < 0 || year > new Date().getFullYear()) {
        yearError.textContent = "Year of publication must be between 0 and current year.";
        yearField.style.border = "1px solid red";
        console.log("Year of publication must be between 0 and current year.");
    }
    else if (formData.copies < 0) {
        copiesError.textContent = "Number of copies must be a positive number.";
        copiesField.style.border = "1px solid red";
        console.log("Number of copies must be a positive number.");
    }
    else{
        //Submit form 
    //   let request = axios.post('../php/AddBook.php', {
    //       title: title,
    //       description: description,
    //       yearOfPublication: yearOfPublication,
    //       copies: copies,
    //       categories: categories, 
    //       image: image
    //   }).then(response => {
    //       console.log("Book added successfully");    
    //   }).catch(error => {
    //       console.error(error);
    //   });
        console.log("Form submitted successfully with data:", formData);
        alert("Book added successfully!");
        clearForm();
    }

}

export function clearForm(event) {
    event.preventDefault();
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
