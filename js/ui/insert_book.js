import { handleFormSubmit } from '../logic/insert_book.js';

document.addEventListener("DOMContentLoaded", () => {
    const dropdown = document.getElementById("customDropdown");
    const dropdownOptions = document.getElementById("dropdownOptions");
    const select = document.getElementById("category");
    const condition = document.getElementById("condition");
    

    document.getElementById("submitForm").addEventListener("click", (event) => {
        event.preventDefault();
        handleFormSubmit();
    });

    condition.addEventListener("change", (event) => {
        const value = event.target.value;
        if (value === "undefined") {
            //disable the year input 
            document.getElementById("year").disabled = true;
        }else{
            document.getElementById("year").disabled = false;
        }
    });

    dropdown.addEventListener("click", () => {
        dropdownOptions.classList.toggle("hidden");
    });

    dropdownOptions.addEventListener("click", (event) => {
        const target = event.target;
        console.log(target);
        if (target.tagName === "LI") {
            const value = target.getAttribute("data-value");
            const option = Array.from(select.options).find(opt => opt.value === value);

            const selectedCount = Array.from(select.options).filter(opt => opt.selected).length;

            if (!option.selected && selectedCount >= 3) {
                target.classList.add("disabled"); // Add a visual indicator
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
                item.classList.add("dull"); // Add dull effect
            } else {
                item.classList.remove("dull"); // Remove dull effect
            }
        });
    };

    document.addEventListener("click", (event) => {
        if (!dropdown.parentElement.contains(event.target)) {
            dropdownOptions.classList.add("hidden");
        }
    });
});


document.addEventListener("DOMContentLoaded", () => {
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
   
