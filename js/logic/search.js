export async function search() {
    // logic for search
    console.log('search logic');
    const searchField = document.getElementById("bookToSearch");
    const search = searchField.value.trim();
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
                params: {title: search }
            });
            console.log(response.data);
            alert("Book found!");
        } catch (error) {
            alert("Error searching book. Please try again.");
            console.error(error);
        }
    }   

    searchBook();
}
