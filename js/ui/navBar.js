import { search } from "../logic/search.js";

document.addEventListener("DOMContentLoaded", () => {
    document.getElementById("search").addEventListener("click", (event) => {
        event.preventDefault();
        search();

    });
}); 
