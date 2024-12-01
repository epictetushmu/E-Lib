// Function to validate submit form 
function handleFormSubmit(formData) {

    if (!formData.title || !/^[a-zA-Z0-9,-]+$/.test(title)) {
        // titleError.textContent = "Title can only contain letters, numbers, and commas.";
        // titleField.style.border = "1px solid red";
        // isValid = false;
        console.log("Title can only contain letters, numbers, and commas.");
    }
    else if (formData.description.length > 300) {
        // descriptionError.textContent = "Description cant be more than 300 characters long.";
        // descriptionField.style.border = "1px solid red";
        // isValid = false;
    }
    else if (formData.yearOfPublication < 0 || yearOfPublication > new Date().getFullYear()) {
        // yearError.textContent = "Year of publication must be between 0 and current year.";
        // yearField.style.border = "1px solid red";
        // isValid = false;
    }
    else if (formData.copies < 0) {
        // copiesError.textContent = "Number of copies must be a positive number.";
        // copiesField.style.border = "1px solid red";
        // isValid = false;
    }
    else if (formData.category.length() > 3 ) {  
        // categoryError.textContent = "Categories must be less than 3.";
        // categoryField.style.border = "1px solid red";
        // isValid = false;
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

    }
} 