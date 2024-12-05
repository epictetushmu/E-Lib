// Function to validate submit form 

//  Import the handleFormSubmit function
export function handleFormSubmit(formData) {  
    if (!formData.title || !/^[a-zA-Z0-9,-]+$/.test(title)) {
        // titleError.textContent = "Title can only contain letters, numbers, and commas.";
        // titleField.style.border = "1px solid red";
        console.log("Title can only contain letters, numbers, and commas.");
    }
    else if (formData.description.length > 300) {
        // descriptionError.textContent = "Description cant be more than 300 characters long.";
        // descriptionField.style.border = "1px solid red";
        console.log("Description cant be more than 300 characters long.");
    }
    else if (formData.yearOfPublication < 0 || yearOfPublication > new Date().getFullYear()) {
        // yearError.textContent = "Year of publication must be between 0 and current year.";
        // yearField.style.border = "1px solid red";
        console.log("Year of publication must be between 0 and current year.");
    }
    else if (formData.copies < 0) {
        // copiesError.textContent = "Number of copies must be a positive number.";
        // copiesField.style.border = "1px solid red";
        console.log("Number of copies must be a positive number.");
    }
    else if (formData.category.length() > 3 ) {  
        // categoryError.textContent = "Categories must be less than 3.";
        // categoryField.style.border = "1px solid red";
        console.log("Categories must be less than 3.");
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

    }

}

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

function clearForm() {
    document.getElementById("bookForm").reset();
}
