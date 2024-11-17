<?php
ob_start();
require 'dbconnection.php'; // Ensure this path is correct

// Connect to the database
$pdo = connectDB();

// Fetch user accounts
$sql = "SELECT userID, user_name, user_type FROM Account";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Start outputting HTML
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/manageuser.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <title>Manage User</title>
</head>
<body>
    <div class="sidebar">
        <img src="../img/ane-laundry-logo.png" alt="AN'E Laundry Logo" class="sidebar-logo">
        <ul>
            <li><a href="maindashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="inventorylist.php"><i class="fas fa-box"></i> Inventory</a></li>
            <li><a href="../html/laundrylist.html"><i class="fas fa-list-alt"></i> Laundries List</a></li>
            <li><a href="../php/manageuser.php"><i class="fas fa-users-cog"></i> Manage User</a></li>
            <li><a href="../html/login.html" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Log Out</a></li>
        </ul>
    </div>
    <div class="main-content">
        <header class="main-header">
            <div class="header-left">
                <h1 class="logo"></h1>
            </div>
            <div class="header-right">
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

    <!-- SweetAlert Script -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../js/manageuser.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script> <!-- jQuery library -->

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