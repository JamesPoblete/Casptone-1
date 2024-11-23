// laundrylist.js

document.addEventListener('DOMContentLoaded', function() {
    // ----- Delete Selected Entries Handling -----
    const deleteSelectedEntriesBtn = document.getElementById('deleteSelectedEntries');

    // Function to handle deletion of selected entries
    deleteSelectedEntriesBtn.addEventListener('click', function() {
        // Get all checked checkboxes
        const checkedBoxes = document.querySelectorAll('.select:checked');
        if (checkedBoxes.length === 0) {
            toastr.warning('Please select at least one laundry entry to delete.');
            return;
        }

        // Collect OrderIDs of selected entries
        const selectedOrderIDs = Array.from(checkedBoxes).map(cb => cb.closest('tr').children[1].textContent.trim());

        // Confirmation dialog
        const confirmDelete = confirm(`Are you sure you want to delete ${selectedOrderIDs.length} selected entr${selectedOrderIDs.length > 1 ? 'ies' : 'y'}? This action cannot be undone.`);
        if (!confirmDelete) {
            return;
        }

        // Show loader
        const loader = document.getElementById('loader');
        if (loader) {
            loader.style.display = 'block';
        }

        // Prepare data to send
        const formData = new FormData();
        selectedOrderIDs.forEach(id => formData.append('orderIDs[]', id));

        // Send AJAX request to deleteLaundry.php
        fetch('../php/deleteLaundry.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(responseData => {
            // Hide loader
            if (loader) {
                loader.style.display = 'none';
            }

            if (responseData.success) {
                toastr.success(`<i class="fas fa-check-circle"></i> Successfully deleted ${selectedOrderIDs.length} entr${selectedOrderIDs.length > 1 ? 'ies' : 'y'}.`);
                fetchDataAndApplyFilters(); // Refresh the table
            } else {
                toastr.error(`<i class="fas fa-exclamation-triangle"></i> Failed to delete entries: ${responseData.message}`);
            }
        })
        .catch(error => {
            // Hide loader
            if (loader) {
                loader.style.display = 'none';
            }

            console.error('Error deleting entries:', error);
            toastr.error('<i class="fas fa-exclamation-triangle"></i> An error occurred while deleting the entries.');
        });
    });

    // ----- Data Table Handling -----
    const rowsPerPage = 7; // Maximum number of rows to display per page
    let currentPage = 1;
    let totalRows = 0;
    let data = []; // Array to hold the fetched data
    let filteredData = []; // Array to hold the filtered data (search + filter result)

    let searchQuery = ''; // Store the current search query
    let selectedStatus = 'All'; // Store the selected status filter

    // Fetch data and apply filters
    fetchDataAndApplyFilters();

    // Function to fetch data and apply filters
    function fetchDataAndApplyFilters() {
        fetch('../php/laundrylist.php')
            .then(response => response.json())
            .then(fetchedData => {
                data = fetchedData; // Store fetched data
                applyFilters(); // Initially apply filters to show full data set
            })
            .catch(error => console.error('Error fetching data:', error));
    }

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
            row.innerHTML = `<td colspan="6">No data found</td>`;
            tableBody.appendChild(row);
        } else {
            paginatedData.forEach(item => {
                const row = document.createElement('tr');
                row.innerHTML = `
                <td><input type="checkbox" class="select"></td>
                <td>${item.OrderID}</td>
                <td>${new Date(item.DATE).toLocaleDateString()}</td>
                <td>${item.NAME}</td>
                <td>${item.PICKUP_TIME || 'No time set'}</td>
                <td>
                    <select class="payment-status-dropdown ${item.PAYMENT_STATUS.toLowerCase()}" data-order-id="${item.OrderID}">
                        <option value="Unpaid" ${item.PAYMENT_STATUS === 'Unpaid' ? 'selected' : ''}>Unpaid</option>
                        <option value="Paid" ${item.PAYMENT_STATUS === 'Paid' ? 'selected' : ''}>Paid</option>
                    </select>
                </td>
                <td>
                    <select class="status-dropdown ${item.STATUS.toLowerCase()}" data-order-id="${item.OrderID}" data-original-status="${item.STATUS}">
                        <option value="Pending" ${item.STATUS === 'Pending' ? 'selected' : ''}>🔴 Pending</option>
                        <option value="Completed" ${item.STATUS === 'Completed' ? 'selected' : ''}>✔ Completed</option>
                    </select>
                </td>
            `;
            
                tableBody.appendChild(row);
            });
        }
        updatePagination(); // Update pagination after table update
        // No need to re-attach status listeners here
    }

// Function to calculate total sales (excluding unpaid)
function calculateTotalSales(data) {
    console.log("Data received for sales calculation:", data);  // Debugging log

    // Ensure we're using the most recent data
    const paidData = data.filter(item => item.PAYMENT_STATUS === 'Paid');
    console.log("Filtered Paid Data:", paidData);  // Debugging log

    // Calculate total sales from paid entries
    const totalSales = paidData.reduce((sum, item) => sum + parseFloat(item.TOTAL || 0), 0);

    console.log(`Total Sales (Paid Only): ₱${totalSales.toFixed(2)}`);  // Debugging log
    return totalSales;
}


// Function to update sales display
function updateSalesDisplay() {
    const totalSales = calculateTotalSales(filteredData); // Make sure to pass the filtered data
    document.getElementById('totalSalesDisplay').textContent = `₱${totalSales.toFixed(2)}`;
}


    

    function handlePaymentStatusChange(event) {
        const dropdown = event.target;
        const orderID = dropdown.getAttribute('data-order-id');
        const newPaymentStatus = dropdown.value; // Should be 'Paid' or 'Unpaid'
    
        // Debugging log to check the value being sent
        console.log(`Updating Payment Status for OrderID: ${orderID}, New Status: ${newPaymentStatus}`);
    
        if (!confirm(`Change payment status of Order ID ${orderID} to "${newPaymentStatus}"?`)) {
            return;
        }
    
        const formData = new FormData();
        formData.append('OrderID', orderID);
        formData.append('PaymentStatus', newPaymentStatus);
    
        fetch('../php/updatePaymentStatus.php', {
            method: 'POST',
            body: formData,
        })
            .then(response => response.json())
            .then(responseData => {
                if (responseData.success) {
                    // Update the class dynamically
                    dropdown.classList.remove('paid', 'unpaid'); // Remove existing classes
                    dropdown.classList.add(newPaymentStatus.toLowerCase()); // Add the new class
                    toastr.success(`Payment status for Order ID ${orderID} updated to "${newPaymentStatus}".`);
                } else {
                    toastr.error(`Failed to update payment status: ${responseData.message}`);
                }
            })
            .catch(error => {
                console.error('Error updating payment status:', error);
                toastr.error('An error occurred while updating the payment status.');
            });
    }
    
    
    
    
    function attachPaymentStatusListeners() {
        const tableBody = document.getElementById('laundryListTable');
        tableBody.addEventListener('change', function (event) {
            if (event.target && event.target.classList.contains('payment-status-dropdown')) {
                handlePaymentStatusChange(event);
            }
        });
    }
    
    // Attach payment status listeners once
    attachPaymentStatusListeners();

    // Update pagination controls
    function updatePagination() {
        document.getElementById('pageNumber').textContent = currentPage;

        // Enable/disable pagination buttons based on the current page and data length
        document.getElementById('prevPage').disabled = currentPage === 1;
        document.getElementById('firstPage').disabled = currentPage === 1;
        document.getElementById('nextPage').disabled = currentPage * rowsPerPage >= totalRows;
        document.getElementById('lastPage').disabled = currentPage * rowsPerPage >= totalRows;
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

    // First page button event
    document.getElementById('firstPage').addEventListener('click', () => {
        if (currentPage !== 1) {
            currentPage = 1;
            updateTable();
        }
    });

    // Last page button event
    document.getElementById('lastPage').addEventListener('click', () => {
        const lastPage = Math.ceil(totalRows / rowsPerPage);
        if (currentPage !== lastPage) {
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

    // Search functionality with Debounce
    const searchInput = document.getElementById('search');
    let debounceTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(debounceTimeout);
        debounceTimeout = setTimeout(() => {
            searchQuery = searchInput.value.toLowerCase(); // Update the search query
            applyFilters(); // Apply both search and filter together
        }, 300); // Adjust the delay as needed
    });

    // ----- Modal Handling -----
    // Get modal elements
    const modal = document.getElementById("laundryModal");
    const btn = document.querySelector(".add-laundry-btn");
    const span = document.getElementsByClassName("close")[0];
    const laundryForm = document.getElementById('laundryForm');

    // Variable to hold the interval ID for updating the date
    let dateUpdateInterval = null;

    // Function to update the date field to the current date based on local timezone
    function updateDate() {
        const today = new Date().toLocaleDateString('en-CA'); // Formats date as YYYY-MM-DD
        const dateInput = document.getElementById('date');
        if (dateInput) {
            dateInput.value = today;
            console.log(`Date updated to: ${today}`); // Debugging log
        } else {
            console.error("Date input field not found!");
        }
    }

    // Function to disable all detergent additional inputs
    function disableDetergentAdditional() {
        const detergentAdditionalInputs = document.querySelectorAll('.detergent-additional');
        detergentAdditionalInputs.forEach(input => {
            input.disabled = true;
            input.value = '';
            input.required = false;
        });
    }

    // Function to disable all fabric detergent additional inputs
    function disableFabricDetergentAdditional() {
        const fabricDetergentAdditionalInputs = document.querySelectorAll('.fabric-detergent-additional');
        fabricDetergentAdditionalInputs.forEach(input => {
            input.disabled = true;
            input.value = '';
            input.required = false;
        });
    }

    // Function to fetch the next OrderID
    function fetchNextOrderID() {
        console.log('fetchNextOrderID() called'); // Debugging log
        fetch('../php/getLastOrderID.php')
            .then(response => {
                console.log('Received response:', response);
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    const nextOrderID = data.lastOrderID; // Removed +1 as PHP already adds it
                    const orderIDInput = document.getElementById('orderID'); // Corrected id
                    if (orderIDInput) {
                        orderIDInput.value = nextOrderID;
                        console.log(`OrderID set to: ${nextOrderID}`);
                    } else {
                        console.error('OrderID input field not found in the DOM.');
                    }
                } else {
                    console.error('Failed to fetch last OrderID:', data.message);
                    const orderIDInput = document.getElementById('orderID'); // Corrected id
                    if (orderIDInput) {
                        orderIDInput.value = 'Error';
                    }
                    alert('Unable to fetch the next Order ID. Please try again later.');
                }
            })
            .catch(error => {
                console.error('Error fetching OrderID:', error);
                const orderIDInput = document.getElementById('orderID'); // Corrected id
                if (orderIDInput) {
                    orderIDInput.value = 'Error';
                }
                alert('An unexpected error occurred while fetching the Order ID.');
            });
    }

    // Function to set PickUp Time to current time + 30 minutes in UTC+8
    function setPickUpTime() {
        // Create a new Date object in UTC+8
        const now = new Date();

        // Calculate UTC time in milliseconds
        const utcTime = now.getTime() + (now.getTimezoneOffset() * 60000);

        // Define UTC+8 timezone offset in milliseconds
        const utcPlus8 = 8 * 60 * 60 * 1000;

        // Create a new Date object adjusted to UTC+8
        const targetTime = new Date(utcTime + utcPlus8);

        // Add 30 minutes
        targetTime.setMinutes(targetTime.getMinutes() + 30);

        // Format time as HH:MM
        const hours = targetTime.getHours().toString().padStart(2, '0');
        const minutes = targetTime.getMinutes().toString().padStart(2, '0');
        const formattedTime = `${hours}:${minutes}`;

        // Set the value of PickUp Time input
        const pickupTimeInput = document.getElementById('pickupTime');
        if (pickupTimeInput) {
            pickupTimeInput.value = formattedTime;
            console.log(`PickUp Time set to: ${formattedTime}`);
        } else {
            console.error("PickUp Time input field not found!");
        }
    }

    // Function to fetch and set OrderID and PickUp Time
    function initializeModal() {
        fetchNextOrderID(); // Fetch and set the next OrderID
        setPickUpTime(); // Set the PickUp Time to current time + 30 minutes
        updateDate(); // Set the date field to today's date
        calculateTotal(); // Initialize total
        fetchDetergents(); // Fetch and populate detergents
        fetchFabricDetergents(); // Fetch and populate fabric detergents
    }

    // Function to fetch Detergents from the server
    function fetchDetergents() {
        fetch('../php/getProducts.php?type=Detergent')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    populateDetergentOptions(data.data, 'DETERGENT_TYPE', 'detergentAdditional');
                } else {
                    toastr.error('Failed to fetch Detergent products.');
                }
            })
            .catch(error => {
                console.error('Error fetching Detergent products:', error);
                toastr.error('An error occurred while fetching Detergent products.');
            });
    }

    // Function to fetch Fabric Detergents from the server
    function fetchFabricDetergents() {
        fetch('../php/getProducts.php?type=Fabric%20Detergent')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    populateDetergentOptions(data.data, 'FABRIC_DETERGENT_TYPE', 'fabricDetergentAdditional');
                } else {
                    toastr.error('Failed to fetch Fabric Detergent products.');
                }
            })
            .catch(error => {
                console.error('Error fetching Fabric Detergent products:', error);
                toastr.error('An error occurred while fetching Fabric Detergent products.');
            });
    }

    // Function to populate Detergent options dynamically
    function populateDetergentOptions(products, nameAttribute, additionalInputId) {
        const containerId = nameAttribute === 'DETERGENT_TYPE' ? 'detergentOptions' : 'fabricDetergentOptions';
        const container = document.getElementById(containerId);
        container.innerHTML = ''; // Clear existing options

        products.forEach(product => {
            const label = document.createElement('label');
            label.classList.add('radio-label'); // Add class for CSS styling
            label.innerHTML = `
                <input type="radio" name="${nameAttribute}" value="${product.ProductName}" class="${nameAttribute === 'DETERGENT_TYPE' ? 'detergent-radio' : 'fabric-detergent-radio'}" data-input-id="${additionalInputId}">
                ${product.ProductName}
            `;
            container.appendChild(label);
        });
    }

    // Open the modal
    btn.addEventListener('click', function() {
        console.log('Opening modal...');
        modal.style.display = "block";
        initializeModal();

        // Clear any existing interval to prevent multiple intervals
        if (dateUpdateInterval) {
            clearInterval(dateUpdateInterval);
            console.log("Existing date update interval cleared.");
        }

        // Set interval to update date every minute (60000 milliseconds) for testing
        dateUpdateInterval = setInterval(updateDate, 60000); // Update every minute
        console.log("Date update interval set to every minute for testing.");
    });

    // Close the modal when the user clicks on <span> (x)
    span.addEventListener('click', function() {
        closeModal();
    });

    // Close the modal when the user clicks outside of the modal
    window.addEventListener('click', function(event) {
        if (event.target == modal) {
            closeModal();
        }
    });

    // Function to close the modal and reset form
    function closeModal() {
        modal.style.display = "none";
        laundryForm.reset(); // Reset the form

        // Reset the date field to today's date
        updateDate();

        // Disable additional inputs
        disableDetergentAdditional();
        disableFabricDetergentAdditional();

        // Reset total
        document.getElementById('total').value = '';

        // Reset load display
        document.getElementById('load').value = '';

        // Clear the date update interval
        if (dateUpdateInterval) {
            clearInterval(dateUpdateInterval);
            dateUpdateInterval = null;
            console.log("Date update interval cleared upon modal close.");
        }
    }

    // ----- Article Checkbox Handling -----
    // Function to toggle input fields based on checkbox state (Articles)
    document.querySelectorAll('.article-checkbox').forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            var inputId = this.getAttribute('data-input-id');
            var inputField = document.getElementById(inputId);
            if (this.checked) {
                inputField.disabled = false;
                // Only require weight inputs if the article is 'Clothes'
                inputField.required = (inputId === 'clothesWeight') ? true : false;
            } else {
                inputField.disabled = true;
                inputField.value = ''; // Clear the input value when checkbox is unchecked
                inputField.required = false;
            }
            calculateTotal(); // Recalculate total on change
        });
    });

    // ----- Detergent Radio Button Handling -----
    // Handle enabling/disabling of detergent 'Additional' inputs for DETERGENT_TYPE
    document.addEventListener('change', function(event) {
        if (event.target.classList.contains('detergent-radio')) {
            disableDetergentAdditional(); // Disable all detergent additional inputs first

            // Enable the corresponding additional input
            var inputId = event.target.getAttribute('data-input-id');
            var inputField = document.getElementById(inputId);
            if (event.target.checked) {
                inputField.disabled = false;
                inputField.required = false;
            }
            calculateTotal(); // Recalculate total on change

            // ----- Stock Check for Detergent -----
            checkDetergentStock(event.target.value, 'DETERGENT');
        }

        if (event.target.classList.contains('fabric-detergent-radio')) {
            disableFabricDetergentAdditional(); // Disable all fabric detergent additional inputs first

            // Enable the corresponding additional input
            var inputId = event.target.getAttribute('data-input-id');
            var inputField = document.getElementById(inputId);
            if (event.target.checked) {
                inputField.disabled = false;
                inputField.required = false;
            }
            calculateTotal(); // Recalculate total on change

            // ----- Stock Check for Fabric Detergent -----
            checkDetergentStock(event.target.value, 'FABRIC_DETERGENT');
        }
    });

    /**
     * Function to check detergent stock when a detergent radio button is selected.
     * Displays an error message if out of stock and unchecks the radio button.
     * @param {string} detergentType - The selected detergent type.
     * @param {string} detergentCategory - 'DETERGENT' or 'FABRIC_DETERGENT'.
     */
    async function checkDetergentStock(detergentType, detergentCategory) {
        try {
            const response = await fetch('../php/checkDetergentStock.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `detergent_type=${encodeURIComponent(detergentType)}&required_count=1`
            });

            const data = await response.json();

            if (!data.success) {
                toastr.error(`Error checking stock for "${detergentType}": ${data.message}`);
                // Uncheck the radio button
                const radioButtons = document.querySelectorAll(`input[name="${detergentCategory}_TYPE"]`);
                radioButtons.forEach(radio => {
                    if (radio.value === detergentType) {
                        radio.checked = false;
                    }
                });
                return;
            }

            if (data.stock <= 0) {
                toastr.error(`"${detergentType}" is out of stock.`);
                // Uncheck the radio button
                const radioButtons = document.querySelectorAll(`input[name="${detergentCategory}_TYPE"]`);
                radioButtons.forEach(radio => {
                    if (radio.value === detergentType) {
                        radio.checked = false;
                    }
                });
            }
        } catch (error) {
            console.error(`Error checking stock for "${detergentType}":`, error);
            toastr.error('An error occurred while checking detergent stock.');
            // Optionally, uncheck the radio button
            const radioButtons = document.querySelectorAll(`input[name="${detergentCategory}_TYPE"]`);
            radioButtons.forEach(radio => {
                if (radio.value === detergentType) {
                    radio.checked = false;
                }
            });
        }
    }

    // ----- Total Calculation -----
    function calculateTotal() {
        let total = 0;
        let load = 0;

        // Get clothes weight
        const clothesWeight = parseFloat(document.getElementById('clothesWeight').value) || 0;

        // Get selected service
        const serviceRadios = document.getElementsByName('SERVICE');
        let selectedService = '';
        for (const radio of serviceRadios) {
            if (radio.checked) {
                selectedService = radio.value;
                break;
            }
        }

        // Calculate load and service cost based on service type
        if (selectedService === 'Wash-Dry-Fold') {
            // For Wash-Dry-Fold: 0.00 - 4.99 kg = 155, 5.00 - 7.00 kg = 175 per load
            const { loadCount, serviceCost } = calculateServiceCostWashDryFold(clothesWeight);
            load = loadCount;
            var serviceCostTotal = serviceCost;
        } else {
            // For Wash Only and Dry Only: Fixed price per load (85), load based on ceil division by 7
            load = Math.ceil(clothesWeight / 7);
            var serviceCostTotal = load * 85;
        }

        // Update load display (readonly input)
        const loadInput = document.getElementById('load');
        if (loadInput) {
            loadInput.value = load;
        }

        // Get article quantities
        const comforterSingle = parseInt(document.getElementById('comforterSinglePieces').value) || 0;
        const comforterDouble = parseInt(document.getElementById('comforterDoublePieces').value) || 0;
        const bedsheet = parseInt(document.getElementById('bedsheetsWeight').value) || 0;
        const othersInput = document.getElementById('othersInput');
        const others = othersInput && othersInput.value ? parseInt(othersInput.value) || 0 : 0;

        // Calculate articles cost
        // Assuming 'others' are charged per load
        const articlesCost = (comforterSingle * 155 + comforterDouble * 170 + bedsheet * 200) + (others * 155 * load);

        // Get detergent additional
        const detergentAdditionalInputs = document.querySelectorAll('.detergent-additional');
        let totalDetergentAdditional = 0;
        detergentAdditionalInputs.forEach(input => {
            totalDetergentAdditional += parseInt(input.value) || 0;
        });

        // Get fabric detergent additional
        const fabricDetergentAdditionalInputs = document.querySelectorAll('.fabric-detergent-additional');
        let totalFabricDetergentAdditional = 0;
        fabricDetergentAdditionalInputs.forEach(input => {
            totalFabricDetergentAdditional += parseInt(input.value) || 0;
        });

        // Calculate detergents cost
        const detergentsCost = (totalDetergentAdditional + totalFabricDetergentAdditional) * 15;

        // Sum up total
        total = serviceCostTotal + articlesCost + detergentsCost;

        // Update the total field
        const totalInput = document.getElementById('total');
        if (totalInput) {
            totalInput.value = total.toFixed(2);
            console.log(`Total calculated: ${total.toFixed(2)}`);
        } else {
            console.error("Total input field not found!");
        }
    }

    // Function to calculate service cost and load count for Wash-Dry-Fold based on clothes weight
    function calculateServiceCostWashDryFold(clothesKg) {
        let serviceCost = 0;
        let loadCount = 0;
        let remainingKg = clothesKg;

        while (remainingKg > 0) {
            if (remainingKg < 5) {
                // 0.00 - 4.99 kg = 155
                serviceCost += 155;
                loadCount += 1;
                remainingKg -= remainingKg; // Subtract the remaining Kg
            } else if (remainingKg <= 7) {
                // 5.00 - 7.00 kg = 175
                serviceCost += 175;
                loadCount += 1;
                remainingKg -= remainingKg; // Subtract the remaining Kg
            } else {
                // More than 7 kg: assign as many full 7 kg loads as possible
                serviceCost += 175;
                loadCount += 1;
                remainingKg -= 7;
            }
        }

        return { loadCount, serviceCost };
    }

    // Add event listeners to input fields to recalculate total when values change
    // Including 'pickupTime' in case any logic depends on it
    document.querySelectorAll('#clothesWeight, #comforterSinglePieces, #comforterDoublePieces, #bedsheetsWeight, #othersInput, .detergent-additional, .fabric-detergent-additional, #pickupTime').forEach(function(input) {
        input.addEventListener('input', calculateTotal);
    });

    // Add event listeners to service category radio buttons to recalculate total when selections change
    document.querySelectorAll('input[name="SERVICE"]').forEach(function(radio) {
        radio.addEventListener('change', calculateTotal);
    });

    // Add event listeners to detergents to recalculate total when selections change
    document.querySelectorAll('input[name="DETERGENT_TYPE"], input[name="FABRIC_DETERGENT_TYPE"]').forEach(function(radio) {
        radio.addEventListener('change', calculateTotal);
    });

    // ----- Form Submission Handling -----
    // Updated to include stock checks for Detergent and Fabric Detergent
    laundryForm.addEventListener('submit', async function(event) {
        event.preventDefault(); // Prevent default form submission

        calculateTotal(); // Ensure total is up-to-date before submission

        const detergentType = document.querySelector('input[name="DETERGENT_TYPE"]:checked')?.value || null;
        const fabricDetergentType = document.querySelector('input[name="FABRIC_DETERGENT_TYPE"]:checked')?.value || null;

        let isOutOfStock = false;

        // Check detergent stock if a detergent type is selected
        if (detergentType) {
            await fetch('../php/checkDetergentStock.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `detergent_type=${encodeURIComponent(detergentType)}&required_count=1`
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success || data.stock <= 0) {
                    isOutOfStock = true;
                    toastr.error(`Detergent "${detergentType}" is out of stock.`);
                    // Uncheck the radio button
                    const detergentRadios = document.querySelectorAll(`input[name="DETERGENT_TYPE"]`);
                    detergentRadios.forEach(radio => {
                        if (radio.value === detergentType) {
                            radio.checked = false;
                        }
                    });
                }
            })
            .catch(error => {
                console.error('Error checking detergent stock:', error);
                toastr.error('An error occurred while checking detergent stock.');
                isOutOfStock = true;
            });
        }

        // Check fabric detergent stock if a fabric detergent type is selected
        if (fabricDetergentType) {
            await fetch('../php/checkDetergentStock.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `detergent_type=${encodeURIComponent(fabricDetergentType)}&required_count=1`
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success || data.stock <= 0) {
                    isOutOfStock = true;
                    toastr.error(`Fabric Detergent "${fabricDetergentType}" is out of stock.`);
                    // Uncheck the radio button
                    const fabricDetergentRadios = document.querySelectorAll(`input[name="FABRIC_DETERGENT_TYPE"]`);
                    fabricDetergentRadios.forEach(radio => {
                        if (radio.value === fabricDetergentType) {
                            radio.checked = false;
                        }
                    });
                }
            })
            .catch(error => {
                console.error('Error checking fabric detergent stock:', error);
                toastr.error('An error occurred while checking fabric detergent stock.');
                isOutOfStock = true;
            });
        }

        // Prevent form submission if any detergent is out of stock
        if (isOutOfStock) {
            return;
        }

        const formData = new FormData();

        // Collect all form inputs, including disabled ones
        const inputs = laundryForm.querySelectorAll('input, select, textarea');
        inputs.forEach(function(input) {
            const name = input.name;
            let value = input.value;
            if (input.type === 'radio' || input.type === 'checkbox') {
                if (!input.checked) {
                    return; // Skip unchecked radio buttons and checkboxes
                }
            }
            if (name) {
                formData.append(name, value);
            }
        });

        fetch('../php/addlaundry.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            if (data.trim() === 'Success') {
                toastr.success(`<i class="fas fa-check-circle"></i> Laundry entry added successfully.`);
                // Close the modal and reset the form
                closeModal();
                laundryForm.reset();
                updateDate();
                disableDetergentAdditional();
                disableFabricDetergentAdditional();
                document.getElementById('total').value = '';
                document.getElementById('load').value = '';
                fetchDataAndApplyFilters();

                // Fetch the latest laundry entry to generate the receipt
                // Assuming OrderID is unique and incremental, fetch the entry with the highest OrderID
                fetch('../php/getLatestLaundry.php')
                    .then(response => response.json())
                    .then(latestData => {
                        if (latestData.success) {
                            populateReceipt([latestData.data]); // Wrap in array for consistency
                            openReceiptModal();
                        } else {
                            toastr.error(`<i class="fas fa-exclamation-triangle"></i> Unable to generate receipt.`);
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching latest laundry entry:', error);
                        toastr.error(`<i class="fas fa-exclamation-triangle"></i> An error occurred while generating the receipt.`);
                    });
            } else {
                toastr.error(`<i class="fas fa-exclamation-triangle"></i> Error: ${data}`);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            toastr.error(`<i class="fas fa-exclamation-triangle"></i> An error occurred while adding the laundry entry.`);
        });
    });

    // ----- Receipt Modal Elements -----
    const receiptModal = document.getElementById("receiptModal");
    const closeReceiptBtn = document.querySelector(".close-receipt");
    const printReceiptBtn = document.getElementById("printReceiptBtn");

    // ----- Function to Populate Receipt Data -----
    function populateReceipt(dataArray) {
        const receiptDiv = document.getElementById('receipt');
        receiptDiv.innerHTML = ''; // Clear previous receipts

        dataArray.forEach(data => {
            const receiptSection = document.createElement('div');
            receiptSection.classList.add('single-receipt');

            // Create the receipt content similar to existing structure
            const receiptHTML = `
                <div class="receipt-header">
                    <img src="../img/ane-laundry-logo.png" alt="AN'E Laundry Logo" class="receipt-logo">
                    <h2>AN'E Laundry Receipt</h2>
                    <p class="receipt-address">Address: Banay-Banay, Lipa City</p>
                    <p class="receipt-phone">Phone: +123 456 7890</p>
                </div>
                <p><strong>Order ID:</strong> <span>${data.OrderID}</span></p>
                <p><strong>Name:</strong> <span>${data.NAME}</span></p>
                <p><strong>Date:</strong> <span>${new Date(data.DATE).toLocaleDateString()}</span></p>
                <p><strong>Service:</strong> <span>${data.SERVICE}</span></p>
                <p><strong>Load:</strong> <span>${data.LAUNDRY_LOAD}</span></p>
                <h3>Articles</h3>
                <ul>
                    ${generateArticlesList(data)}
                </ul>
                <p><strong>Detergent:</strong> <span>${data.DETERGENT}</span></p>
                <p><strong>Fabric Detergent:</strong> <span>${data.FABRIC_DETERGENT}</span></p>
                <p><strong>Additional Cost:</strong> <span>${data.ADDITIONAL_COST}</span></p>
                <p><strong>Pick Up Time:</strong> <span>${data.PICKUP_TIME || 'No time set'}</span></p>
                <p>
                    <strong style="font-size: 24px;">Total:</strong> ₱ <span>${data.TOTAL}</span>
                </p>
                <hr>
            `;

            receiptSection.innerHTML = receiptHTML;
            receiptDiv.appendChild(receiptSection);
        });
    }

    // Helper function to generate articles list
    function generateArticlesList(data) {
        let articlesHTML = '';

        if (data.CLOTHES_WEIGHT_KG > 0) {
            articlesHTML += `<li>Clothes: ${data.CLOTHES_WEIGHT_KG} kg</li>`;
        }
        if (data.COMFORTER_SINGLE > 0) {
            articlesHTML += `<li>Comforter Single: ${data.COMFORTER_SINGLE} pieces</li>`;
        }
        if (data.COMFORT_DOUBLE > 0) {
            articlesHTML += `<li>Comforter Double: ${data.COMFORT_DOUBLE} pieces</li>`;
        }
        if (data.BEDSHEETS_CURTAINS_TOWEL_BLANKETS > 0) {
            articlesHTML += `<li>Bedsheet/Curtains/Towel: ${data.BEDSHEETS_CURTAINS_TOWEL_BLANKETS} pieces</li>`;
        }
        if (data.OTHERS) {
            articlesHTML += `<li>Others: ${data.OTHERS}</li>`;
        }

        return articlesHTML;
    }

    // ----- Function to Open Receipt Modal -----
    function openReceiptModal() {
        receiptModal.style.display = "block";
    }

    // ----- Function to Close Receipt Modal -----
    function closeReceiptModal() {
        receiptModal.style.display = "none";
    }

    // ----- Event Listeners for Receipt Modal -----
    closeReceiptBtn.addEventListener('click', closeReceiptModal);
    printReceiptBtn.addEventListener('click', function() {
        window.print();
        closeReceiptModal();
    });

    // Close modal when clicking outside of it
    window.addEventListener('click', function(event) {
        if (event.target == receiptModal) {
            closeReceiptModal();
        }
    });

    // ----- Print Selected Receipts Button -----
    const printSelectedReceiptsBtn = document.getElementById('printSelectedReceipts');

    // ----- Function to Fetch Multiple Laundry Entries by OrderIDs -----
    function fetchMultipleLaundries(orderIDs) {
        // Create a query string with OrderIDs
        const query = orderIDs.map(id => `ids[]=${encodeURIComponent(id)}`).join('&');
        return fetch(`../php/getMultipleLaundries.php?${query}`)
            .then(response => response.json());
    }

    // ----- Function to Handle Print Selected Receipts -----
    printSelectedReceiptsBtn.addEventListener('click', function() {
        // Get all checked checkboxes
        const checkedBoxes = document.querySelectorAll('.select:checked');
        if (checkedBoxes.length === 0) {
            toastr.warning('Please select at least one laundry entry to print its receipt.');
            return;
        }

        // Collect OrderIDs of selected entries
        const selectedOrderIDs = Array.from(checkedBoxes).map(cb => cb.parentElement.nextElementSibling.textContent.trim());

        // Fetch data for selected OrderIDs
        fetchMultipleLaundries(selectedOrderIDs)
            .then(responseData => {
                if (responseData.success) {
                    populateReceipt(responseData.data);
                    openReceiptModal();
                } else {
                    toastr.error('Unable to fetch selected laundry entries for receipts.');
                }
            })
            .catch(error => {
                console.error('Error fetching selected laundries:', error);
                toastr.error('An error occurred while fetching selected laundry entries.');
            });
    });

    // ----- Status Update Handling -----
    /**
     * Handles the status change event for a laundry entry.
     * @param {Event} event - The change event triggered by the dropdown.
     */
    function handleStatusChange(event) {
        const dropdown = event.target;
        const orderID = dropdown.getAttribute('data-order-id');
        const newStatus = dropdown.value; // Should be 'Pending' or 'Completed'
    
        // Debugging log to check the value being sent
        console.log(`Updating Status for OrderID: ${orderID}, New Status: ${newStatus}`);
    
        if (!confirm(`Change status of Order ID ${orderID} to "${newStatus}"?`)) {
            return;
        }
    
        const formData = new FormData();
        formData.append('OrderID', orderID);
        formData.append('Status', newStatus);
    
        fetch('../php/updateStatus.php', {
            method: 'POST',
            body: formData,
        })
            .then(response => response.json())
            .then(responseData => {
                if (responseData.success) {
                    // Update the class dynamically
                    dropdown.classList.remove('completed', 'pending'); // Remove old classes
                    dropdown.classList.add(newStatus.toLowerCase()); // Add new class
                    toastr.success(`Status for Order ID ${orderID} updated to "${newStatus}".`);
                } else {
                    toastr.error(`Failed to update status: ${responseData.message}`);
                }
            })
            .catch(error => {
                console.error('Error updating status:', error);
                toastr.error('An error occurred while updating the status.');
            });
    }
    
    
    

    /**
     * Updates the dropdown's appearance based on status.
     * @param {HTMLElement} dropdown - The status dropdown element.
     * @param {string} status - The status to apply.
     */
    function updateDropdownStyle(dropdown, status) {
        dropdown.classList.remove('completed', 'pending', 'other');
        if (status === 'Completed') {
            dropdown.classList.add('completed');
        } else if (status === 'Pending') {
            dropdown.classList.add('pending');
        } else {
            dropdown.classList.add('other');
        }
    }

    /**
     * Attaches event listeners to all status dropdowns in the table using event delegation.
     * This function should be called only once.
     */
    function attachStatusListeners() {
        const tableBody = document.getElementById('laundryListTable');
        tableBody.addEventListener('change', function(event) {
            if (event.target && event.target.classList.contains('status-dropdown')) {
                handleStatusChange(event);
            }
        });
    }

    // Attach the status listeners once after DOM content is loaded
    attachStatusListeners();
});
