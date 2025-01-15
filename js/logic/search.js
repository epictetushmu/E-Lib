export async function search() {
    // logic for search
    console.log('search logic');
    const searchField = document.getElementById("bookToSearch");
    const search = searchField.value.trim();
    const resultsContainer = document.getElementById("searchResults");

    console.log(search);
    const api = axios.create({
        baseURL: 'http://localhost/WebUni/api/Books.php',  // Replace with your actual API URL
        headers: {
            'Content-Type': 'application/json',
        }
    })

    const searchBook = async () => {
        try {
            const response = await api.get(`?api=searchBooks`, {
                params: { title: search }
            });

            // Clear previous results
            resultsContainer.innerHTML = '';

            // Check if any books were found
            if (response.data.data.length === 0) {
                resultsContainer.innerHTML = '<p>No books found.</p>';
                return;
            }

            // Display the search results
            response.data.data.forEach(book => {
                const bookElement = document.createElement('div');
                bookElement.classList.add('book-result');
                bookElement.innerHTML = `
                <div class="col-md-4">
                    <div class="card">
                        <img src="book-cover.jpg" class="card-img-top" alt="Book Cover">
                        <div class="card-body">
                            <h5 class="card-title">${book.title}</h5>
                            <h6 class="card-subtitle mb-2 text-muted">Author: ${book.author}</h6>
                            <p class="card-text"><strong>Year:</strong> ${book.publication_year}</p>
                            <p class="card-text"><strong>Condition:</strong> ${book.condition}</p>
                            <p class="card-text"><strong>Copies:</strong> ${book.number_of_copies}</p>
                            <p class="card-text"><strong>Description:</strong> ${book.description}</p>
                            <a href="book_detail.html?bookId=${book.id}" class="btn btn-primary">View Details</a>
                        </div>
                    </div>
                </div>
                `;
                resultsContainer.appendChild(bookElement);
            });

        } catch (error) {
            alert("Error searching book. Please try again.");
            console.error(error);
        }
    };


    searchBook();
}
