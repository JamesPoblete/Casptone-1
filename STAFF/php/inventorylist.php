<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start(); // Start the session

// Check if the user is logged in
if (!isset($_SESSION['userID'])) {
    // User is not logged in, redirect to login page
    header("Location: ../html/login.html");
    exit();
}

include 'dbconnection.php';

// Get the PDO connection
$pdo = connectDB();

// Fetch all inventory items for the logged-in user
$sql = "SELECT * FROM inventory WHERE userID = :userID";
$params = [':userID' => $_SESSION['userID']];

// Prepare and execute the query
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

// Fetch all results
$inventoryItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Meta tags and title -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory</title>

    <!-- Stylesheets -->
    <link rel="stylesheet" href="../css/inventorylist.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Inline CSS for Print Media -->
    <style>
    @media print {
        /* Hide elements not needed in print */
        .sidebar, .main-header, .inventory-list-header, .search-filter-container {
            display: none;
        }
        table {
            width: 100%;
        }
    }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <img src="../img/ane-laundry-logo.png" alt="AN'E Laundry Logo" class="sidebar-logo"> 
        <ul>
          <li><a href="maindashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
          <li><a href="inventorylist.php"><i class="fas fa-box"></i> Inventory</a></li>
          <li><a href="../html/laundrylist.html"><i class="fas fa-list-alt"></i> Laundries List</a></li>
          <li><a href="../html/login.html" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Log Out</a></li>
        </ul>
    </div>

    <!-- Main content area -->
    <div class="main-content">
        <header class="main-header">
            <div class="header-left">
                <h1 class="logo"></h1>
            </div>
            
            <div class="header-right">
                <i class="fa fa-bell"></i>
                <div class="user-profile">
                    <i class="fa fa-user-circle"></i>
                    <span>Staff</span>
                    <i class="fa fa-caret-down"></i>
                </div>
            </div>
        </header>

        <!-- Inventory List Header Section -->
        <div class="inventory-list-header">
            <h2>Inventory</h2>
            <button class="print-btn" id="printBtn"><i class="fas fa-print">&nbsp;&nbsp;</i>Print Report</button>
        </div>

        <!-- Search Bar -->
        <div class="search-filter-container">
            <input type="text" id="search" name="search" placeholder="Search by Product ID, Name, or Type" autocomplete="off">
            <select class="status-filter" id="statusFilter" name="status">
                <option value="all">All</option> 
                <option value="high">High</option>
                <option value="medium">Medium</option>
                <option value="low">Low</option>
                <option value="out-of-stock">Out of Stock</option> 
            </select>
        </div>

        <!-- Inventory List Table -->
        <table>
            <thead>
                <tr>
                    <th>Product ID</th>
                    <th>Product Name</th>
                    <th>Type</th>
                    <th>Current Stock</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody id="inventoryTableBody">
                <?php
                if (count($inventoryItems) > 0) {
                    foreach ($inventoryItems as $row) {
                        // Determine the status based on CurrentStock
                        if ($row['CurrentStock'] == 0) {
                            $status = 'out-of-stock';
                            $statusText = 'Out of Stock';
                        } elseif ($row['CurrentStock'] >= 10 && $row['CurrentStock'] <= 15) {
                            $status = 'high';
                            $statusText = 'High';
                        } elseif ($row['CurrentStock'] >= 6 && $row['CurrentStock'] <= 9) {
                            $status = 'medium';
                            $statusText = 'Medium';
                        } elseif ($row['CurrentStock'] >= 1 && $row['CurrentStock'] <= 5) {
                            $status = 'low';
                            $statusText = 'Low';
                        } else {
                            $status = 'unknown';
                            $statusText = 'Unknown';
                        }

                        echo "<tr>";
                        echo "<td>#".$row['InventoryID']."</td>";
                        echo "<td>".$row['ProductName']."</td>";
                        echo "<td>".$row['ProductType']."</td>";
                        echo "<td>".$row['CurrentStock']."</td>";
                        echo "<td><span class='status ".$status."'>".$statusText."</span></td>";
                    }
                } else {
                    echo "<tr><td colspan='6'>No records found.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <!-- JavaScript -->
    <script>
        // JavaScript for Client-Side Search
        const searchInput = document.getElementById('search');
        const statusFilter = document.getElementById('statusFilter');
        const table = document.querySelector('table tbody');

        searchInput.addEventListener('input', filterTable);
        statusFilter.addEventListener('change', filterTable);

        function filterTable() {
            const searchValue = searchInput.value.toLowerCase();
            const statusValue = statusFilter.value.toLowerCase();
            const rows = table.querySelectorAll('tr');

            rows.forEach(row => {
                const productID = row.cells[0].textContent.toLowerCase().replace('#', '');
                const productName = row.cells[1].textContent.toLowerCase();
                const productType = row.cells[2].textContent.toLowerCase();
                const currentStatus = row.cells[4].textContent.toLowerCase();

                const matchesSearch = 
                    productID.includes(searchValue) || 
                    productName.includes(searchValue) || 
                    productType.includes(searchValue);

                const matchesStatus = statusValue === 'all' || currentStatus.includes(statusValue);

                if (matchesSearch && matchesStatus) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Print Report Functionality
        document.getElementById('printBtn').addEventListener('click', function() {
            window.print();
        });
    </script>

    <!-- jQuery library for sidebar active state -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>

    <script>
      $(document).ready(function() {
        // Get the current path from the URL
        var path = window.location.pathname.split("/").pop();

        // Set default path for home page
        if (path === "") {
          path = "index.html";
        }

        // Find the sidebar link that matches the current path
        var target = $('.sidebar ul li a[href="' + path + '"]');

        // Add active class to the matching link
        target.addClass("active");
      });
    </script>
</body>
</html>
