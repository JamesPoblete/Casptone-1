<?php
session_start(); // Start the session

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if the user is logged in
if (!isset($_SESSION['userID'])) {
    // User is not logged in, redirect to login page
    header("Location: ../html/login.html");
    exit();
}

require 'dbconnection.php'; // Ensure this path is correct

// Connect to the database
$pdo = connectDB();

// Fetch user accounts
$sql = "SELECT userID, user_name, user_type FROM Account";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch notifications data
$low_stock_threshold = 5;

// Fetch out-of-stock products
$stmt_out_of_stock = $pdo->prepare("SELECT ProductName, CurrentStock FROM inventory WHERE userID = :userID AND CurrentStock = 0");
$stmt_out_of_stock->execute(['userID' => $_SESSION['userID']]);
$out_of_stock_products = $stmt_out_of_stock->fetchAll(PDO::FETCH_ASSOC);

// Fetch low-stock products
$stmt_low_stock = $pdo->prepare("SELECT ProductName, CurrentStock FROM inventory WHERE userID = :userID AND CurrentStock > 0 AND CurrentStock <= :threshold");
$stmt_low_stock->execute(['userID' => $_SESSION['userID'], 'threshold' => $low_stock_threshold]);
$low_stock_products = $stmt_low_stock->fetchAll(PDO::FETCH_ASSOC);

// Calculate the total number of notifications
$total_notifications = count($out_of_stock_products) + count($low_stock_products);

// Start outputting HTML
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Head content remains the same -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage User</title>
    <link rel="stylesheet" href="../css/manageuser.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Include SweetAlert CSS if needed -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <img src="../img/ane-laundry-logo.png" alt="AN'E Laundry Logo" class="sidebar-logo">
        <ul>
            <li><a href="maindashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="inventorylist.php"><i class="fas fa-box"></i> Inventory</a></li>
            <li><a href="../php/laundry.php"><i class="fas fa-list-alt"></i> Laundries List</a></li>
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

        <div class="manageuser-list-header">
            <h2>Manage User</h2>
        </div>

        <input type="text" id="search" placeholder="Search by User ID, Username or Role">
        <a href="../html/adduser.html" class="add-user-btn" id="addUserBtn"><i class="fas fa-plus">&nbsp;&nbsp;</i>Add User</a>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $row): ?>
                        <tr>
                            <td>#<?= htmlspecialchars($row['userID']) ?></td>
                            <td><?= htmlspecialchars($row['user_name']) ?></td>
                            <td><?= htmlspecialchars($row['user_type']) ?></td>
                            <td>
                                <button class='icon-btn' onclick="editUser(<?= $row['userID'] ?>, '<?= htmlspecialchars($row['user_name']) ?>', '<?= htmlspecialchars($row['user_type']) ?>')"><i class='fas fa-edit'></i></button>
                                <span class='separator'>|</span>
                                <button class='icon-btn' onclick="confirmDelete(<?= $row['userID'] ?>)"><i class='fas fa-trash'></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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

    <!-- SweetAlert Script -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../js/manageuser.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script> <!-- jQuery library -->

    <!-- Active Link Highlight Script -->
    <script>
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
    </script>
</body>
</html>
<?php
ob_end_flush();
?>
