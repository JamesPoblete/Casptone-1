function editUser(userId, userName, userType) {
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

        $(document).ready(function() {
          if ($('body').hasClass('manage-user-page')) {
            $('.sidebar ul li a[href="../php/manageuser.php"]').addClass("active");
          }
        });