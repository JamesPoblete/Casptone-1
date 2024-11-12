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
            row.innerHTML = `<td colspan="5">No data found</td>`;
            tableBody.appendChild(row);
        } else {
            paginatedData.forEach(item => {
                const row = document.createElement('tr');
                let statusClass = '';
                let statusIcon = '';

                // Set status class and icon based on item status
                if (item.STATUS === 'Completed') {
                    statusClass = 'completed';
                    statusIcon = '<i class="fas fa-check-circle"></i>'; // Green check icon for completed
                } else if (item.STATUS === 'Pending') {
                    statusClass = 'pending';
                    statusIcon = '<i class="fas fa-exclamation-circle"></i>'; // Yellow exclamation icon for pending
                }

                row.innerHTML = `
                    <td><input type="checkbox" class="select"></td>
                    <td>${item.OrderID}</td>
                    <td>${item.DATE}</td>
                    <td>${item.NAME}</td>
                    <td><span class="status ${statusClass}">${item.STATUS} ${statusIcon}</span></td>
                `;
                tableBody.appendChild(row);
            });
        }
        updatePagination(); // Update pagination after table update
    }

    // Function to update pagination controls
    function updatePagination() {
        document.getElementById('pageNumber').textContent = currentPage;

        // Enable/disable pagination buttons based on the current page and data length
        document.getElementById('prevPage').disabled = currentPage === 1;
        document.getElementById('nextPage').disabled = currentPage * rowsPerPage >= totalRows;
    }

    // Previous page button event
    document.getElementById('prevPage').addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            updateTable(); // Use the filteredData for pagination
        }
    });

    // Next page button event
    document.getElementById('nextPage').addEventListener('click', () => {
        if (currentPage * rowsPerPage < totalRows) {
            currentPage++;
            updateTable(); // Use the filteredData for pagination
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
    document.getElementById('selectAll').addEventListener('click', function() {
        const checkboxes = document.querySelectorAll('.select');
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
    });
});


