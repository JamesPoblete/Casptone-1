function editUser(userId, userName, userType) {
    // Existing code for editing user
    Swal.fire({
        title: 'Edit User',
        html: `
            <input id="swal-input1" class="swal2-input" placeholder="Username" value="${userName}">
            <input id="swal-input2" class="swal2-input" placeholder="Role" value="${userType}">
        `,
        focusConfirm: false,
        preConfirm: () => {
            const newUserName = document.getElementById('swal-input1').value;
            const newUserType = document.getElementById('swal-input2').value;
            
            return fetch('edituser.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ userId, newUserName, newUserType })
            }).then(response => {
                if (!response.ok) {
                    throw new Error(response.statusText);
                }
                return response.json();
            }).then(data => {
                Swal.fire('Updated!', data.message, 'success');
                // Delay for 2 seconds before reloading
                setTimeout(() => {
                    location.reload();
                }, 2000);
            }).catch(error => {
                Swal.fire('Error!', error.message, 'error');
            });
        }
    });
}

function confirmDelete(userId) {
    // Existing code for deleting user
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('deleteuser.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ userId })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(response.statusText);
                }
                return response.json();
            })
            .then(data => {
                Swal.fire(data.status === 'success' ? 'Deleted!' : 'Error!', data.message, data.status === 'success' ? 'success' : 'error');
                // Delay for 2 seconds before reloading
                setTimeout(() => {
                    if (data.status === 'success') {
                        location.reload();
                    }
                }, 2000);
            })
            .catch(error => {
                Swal.fire('Error!', error.message, 'error');
            });
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('search');
    const table = document.querySelector('table tbody');

    searchInput.addEventListener('input', function() {
        const searchValue = this.value.toLowerCase();
        const rows = table.querySelectorAll('tr');

        rows.forEach(row => {
            const userID = row.cells[0].textContent.toLowerCase();
            const userName = row.cells[1].textContent.toLowerCase();
            const userType = row.cells[2].textContent.toLowerCase();

            if (userID.includes(searchValue) || userName.includes(searchValue) || userType.includes(searchValue)) {
                row.style.display = ''; // Show the row
            } else {
                row.style.display = 'none'; // Hide the row
            }
        });
    });

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

    // Active Link Highlighting
    $(document).ready(function() {
        // Get the current path from the URL
        var path = window.location.pathname.split("/").pop();

        // Set default path for the home page (if necessary)
        if (path === "") {
            path = "index.html"; // or whatever the home page path is
        }

        // Loop through all sidebar links
        $('.sidebar ul li a').each(function() {
            // Extract the href attribute and split to get just the file name
            var hrefPath = $(this).attr('href').split("/").pop();

            // Compare the current URL path with the href path
            if (hrefPath === path) {
                $(this).addClass("active");
            }
        });
    });
});
