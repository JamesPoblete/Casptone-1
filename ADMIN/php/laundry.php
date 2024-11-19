<?php
session_start(); // Start the session at the very beginning

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if the user is logged in
if (!isset($_SESSION['userID'])) {
    // User is not logged in, redirect to login page
    header("Location: ../html/login.html");
    exit();
}

require_once '../php/dbconnection.php'; // Ensure this points to the right file

// Connect to the database
$conn = connectDB();

// Fetch notifications data
$low_stock_threshold = 5;

// Fetch out-of-stock products
$stmt_out_of_stock = $conn->prepare("SELECT ProductName, CurrentStock FROM inventory WHERE userID = :userID AND CurrentStock = 0");
$stmt_out_of_stock->execute(['userID' => $_SESSION['userID']]);
$out_of_stock_products = $stmt_out_of_stock->fetchAll(PDO::FETCH_ASSOC);

// Fetch low-stock products
$stmt_low_stock = $conn->prepare("SELECT ProductName, CurrentStock FROM inventory WHERE userID = :userID AND CurrentStock > 0 AND CurrentStock <= :threshold");
$stmt_low_stock->execute(['userID' => $_SESSION['userID'], 'threshold' => $low_stock_threshold]);
$low_stock_products = $stmt_low_stock->fetchAll(PDO::FETCH_ASSOC);

// Calculate the total number of notifications
$total_notifications = count($out_of_stock_products) + count($low_stock_products);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laundry List</title>
    <link rel="stylesheet" href="../css/laundrylist.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <img src="../img/ane-laundry-logo.png" alt="AN'E Laundry Logo" class="sidebar-logo"> 
        <ul>
          <li><a href="../php/maindashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
          <li><a href="../php/inventorylist.php"><i class="fas fa-box"></i> Inventory</a></li>
          <li><a href="laundry.php"><i class="fas fa-list-alt"></i> Laundries List</a></li>
          <li><a href="../php/manageuser.php"><i class="fas fa-users-cog"></i> Manage User</a></li>
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
                <!-- Notifications Icon -->
                <div class="notifications">
    <i class="fas fa-bell" id="notificationsIcon"></i>
    <?php if ($total_notifications > 0): ?>
        <span class="badge" id="notificationBadge"><?php echo $total_notifications; ?></span>
    <?php endif; ?>
</div>
                <div class="user-profile">
                    <i class="fa fa-user-circle"></i>
                    <span>Admin</span>
                </div>
            </div>
        </header>

        <!-- Laundry List Section -->
        <div class="laundry-list-header">
            <h2>Laundries</h2>
        </div>

        <!-- Search Bar -->
        <div class="search-filter-container">
            <input type="text" id="search" placeholder="Search">
            <select class="status-filter">
                <option value="" selected disabled>Filter</option> 
                <option value="All">All</option>
                <option value="Completed">Completed</option>
                <option value="Pending">Pending</option>
            </select>
        </div>
        
        <!-- Laundry List Table -->
        <table>
            <thead>
                <tr>
                    <th>
                        Select All
                    </th>
                    <th>Order ID</th>
                    <th>Date</th>
                    <th>Name</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody id="laundryListTable">
                <!-- Data will be inserted here dynamically -->
            </tbody>
        </table>

        <!-- Pagination Controls -->
        <div class="pagination">
            <button id="firstPage" disabled><i class="fas fa-angle-double-left"></i></button>
            <button id="prevPage" disabled><i class="fas fa-chevron-left"></i></button>
            <span id="pageNumber">1</span>
            <button id="nextPage"><i class="fas fa-chevron-right"></i></button>
            <button id="lastPage"><i class="fas fa-angle-double-right"></i></button>
        </div>
    </div>

<!-- Notifications Modal -->
<div id="notificationsModal" class="notifmodal" role="dialog" aria-labelledby="notificationsModalTitle" aria-modal="true">
    <div class="modal-content">
        <span class="close-button" id="closeModal" role="button" aria-label="Close">&times;</span>
        <h2>Notifications</h2>
        <?php if ($total_notifications > 0): ?>
            <div class="notifications-section">
                <?php if (count($out_of_stock_products) > 0): ?>
                    <h3>Out of Stock</h3>
                    <ul>
                        <?php foreach ($out_of_stock_products as $product): ?>
                            <li><?php echo htmlspecialchars($product['ProductName']); ?> - Current Stock: <?php echo htmlspecialchars($product['CurrentStock']); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if (count($low_stock_products) > 0): ?>
                    <h3>Low Stock</h3>
                    <ul>
                        <?php foreach ($low_stock_products as $product): ?>
                            <li><?php echo htmlspecialchars($product['ProductName']); ?> - Current Stock: <?php echo htmlspecialchars($product['CurrentStock']); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p>No inventory issues detected.</p>
        <?php endif; ?>
    </div>
</div>


    <!-- JavaScript to fetch and display data -->
    <script src="../js/laundrylist.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script> <!-- jQuery library -->

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
