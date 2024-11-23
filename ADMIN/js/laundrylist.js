document.addEventListener('DOMContentLoaded', function() {
    const rowsPerPage = 7; // Maximum number of rows to display per page
    let currentPage = 1;
    let totalRows = 0;
    let data = []; // Array to hold the fetched data
    let filteredData = []; // Array to hold the filtered data (search + filter result)

    let searchQuery = ''; // Store the current search query
    let selectedStatus = 'All'; // Store the selected status filter

    // Fetch data from the server
    fetch('../php/laundrylist.php')
        .then(response => response.json())
        .then(fetchedData => {
            data = fetchedData; // Store fetched data
            applyFilters(); // Initially apply filters to show full data set
        })
        .catch(error => console.error('Error fetching data:', error));

    // Function to apply both search and filter together
    function applyFilters() {
        filteredData = data.filter(item => {
            // Check if the item matches the search query
            const matchesSearch = item.OrderID.toString().toLowerCase().includes(searchQuery) || 
                                  item.DATE.toLowerCase().includes(searchQuery) || 
                                  item.NAME.toLowerCase().includes(searchQuery);

            // Check if the item matches the selected status
            const matchesStatus = selectedStatus === 'All' || item.STATUS === selectedStatus;

            // Return true only if both conditions are met
            return matchesSearch && matchesStatus;
        });

        totalRows = filteredData.length; // Update total rows based on filtered data
        currentPage = 1; // Reset to first page when new filters are applied
        updateTable(); // Update the table with the filtered data
    }

// Function to update the table with the filtered data
function updateTable() {
    const tableBody = document.getElementById('laundryListTable');
    tableBody.innerHTML = ''; // Clear existing rows

    const startIndex = (currentPage - 1) * rowsPerPage;
    const endIndex = startIndex + rowsPerPage;
    const paginatedData = filteredData.slice(startIndex, endIndex); // Paginate filteredData

    if (paginatedData.length === 0) {
        const row = document.createElement('tr');
        row.innerHTML = `<td colspan="6">No data found</td>`; // Ensure colspan matches number of columns
        tableBody.appendChild(row);
    } else {
        paginatedData.forEach(item => {
            const row = document.createElement('tr');
            const statusClass = item.STATUS === 'Completed' ? 'completed' : 'pending';
            const statusIcon = item.STATUS === 'Completed' ? '<i class="fas fa-check-circle"></i>' : '<i class="fas fa-exclamation-circle"></i>';

            // Check for undefined payment status and handle it
            const paymentStatus = item.PAYMENT_STATUS || 'Not Available'; // Default text if undefined
            const paymentStatusClass = item.PAYMENT_STATUS === 'Paid' ? 'paid' : 'unpaid';
            const paymentStatusIcon = item.PAYMENT_STATUS === 'Paid' ? '<i class="fas fa-check-circle"></i>' : '<i class="fas fa-times-circle"></i>';

            row.innerHTML = `
                <td>${item.OrderID}</td>
                <td>${item.DATE}</td>
                <td>${item.NAME}</td>
                <td><span class="status ${paymentStatusClass}">${paymentStatus} ${paymentStatusIcon}</span></td>
                <td><span class="status ${statusClass}">${item.STATUS} ${statusIcon}</span></td>
            `;
            tableBody.appendChild(row);
            });
        }
        updatePagination(); // Update pagination after table update
    }

    // Update pagination controls
    function updatePagination() {
        document.getElementById('pageNumber').textContent = currentPage;

        // Enable/disable pagination buttons based on the current page and data length
        document.getElementById('prevPage').disabled = currentPage === 1;
        document.getElementById('firstPage').disabled = currentPage === 1;
        document.getElementById('nextPage').disabled = currentPage * rowsPerPage >= totalRows;
        document.getElementById('lastPage').disabled = currentPage * rowsPerPage >= totalRows;
    }

    // Pagination button events
    document.getElementById('prevPage').addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            updateTable(); // Use the filteredData for pagination
        }
    });

    document.getElementById('nextPage').addEventListener('click', () => {
        if (currentPage * rowsPerPage < totalRows) {
            currentPage++;
            updateTable(); // Use the filteredData for pagination
        }
    });

    document.getElementById('firstPage').addEventListener('click', () => {
        if (currentPage !== 1) {
            currentPage = 1;
            updateTable();
        }
    });

    document.getElementById('lastPage').addEventListener('click', () => {
        const lastPage = Math.ceil(totalRows / rowsPerPage);
        if (currentPage !== lastPage && lastPage > 0) {
            currentPage = lastPage;
            updateTable();
        }
    });

    // Filter functionality
    const filterSelect = document.querySelector('.status-filter');
    filterSelect.addEventListener('change', function() {
        selectedStatus = this.value; // Update the selected status filter
        searchQuery = ''; // Reset search query
        document.getElementById('search').value = ''; // Clear the search input field
        applyFilters(); // Apply both search and filter together
    });

    // Search functionality
    const searchInput = document.getElementById('search');
    searchInput.addEventListener('input', function() {
        searchQuery = searchInput.value.toLowerCase(); // Update the search query
        applyFilters(); // Apply both search and filter together
    });

    // Select All functionality
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('click', function() {
            const checkboxes = document.querySelectorAll('.select');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
    }

    // Notifications Modal Handling

    // Notifications Elements
    const notificationsIcon = document.getElementById('notificationsIcon');
    const notificationsModal = document.getElementById('notificationsModal');
    const closeModalBtn = document.getElementById('closeModal');

    // Function to open modal with animation
    function openModal() {
        notificationsModal.style.display = 'flex'; // Use 'flex' to align items centrally
        // Allow the browser to render the display change before adding the class
        setTimeout(() => {
            notificationsModal.classList.add('show');
        }, 10); // 10ms delay to ensure the transition
    }

    // Function to close modal with animation
    function closeModal() {
        notificationsModal.classList.remove('show');
        // Wait for the animation to finish before hiding
        setTimeout(() => {
            notificationsModal.style.display = 'none';
        }, 500); // Duration should match the CSS transition (0.5s)
    }

    // Event Listener to Open Modal
    notificationsIcon.addEventListener('click', openModal);

    // Event Listener to Close Modal via Close Button
    closeModalBtn.addEventListener('click', closeModal);

    // Event Listener to Close Modal by Clicking Outside the Modal Content
    window.addEventListener('click', function(event) {
        if (event.target === notificationsModal) {
            closeModal();
        }
    });
});
