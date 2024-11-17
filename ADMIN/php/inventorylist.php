<?php
// inventorylist.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start(); // Start the session

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check for success messages
$addSuccess = false;
$editSuccess = false;
$errorMessage = '';

if (isset($_SESSION['add_success'])) {
    $addSuccess = true;
    unset($_SESSION['add_success']); // Clear the session variable
}

if (isset($_SESSION['edit_success'])) {
    $editSuccess = true;
    unset($_SESSION['edit_success']); // Clear the session variable
}

if (isset($_SESSION['error_message'])) {
    $errorMessage = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Check if the user is logged in
if (!isset($_SESSION['userID'])) {
    // User is not logged in, redirect to login page
    header("Location: ../html/login.html");
    exit();
}

include 'dbconnection.php';

// Set the desired timezone
date_default_timezone_set('Asia/Manila'); // Change to your timezone if different

// Get the PDO connection
$pdo = connectDB();

// Set MySQL session timezone to match PHP timezone
$pdo->exec("SET time_zone = '".date('P')."'");

// Pagination setup (Optional)
$itemsPerPage = 10; // Number of items per page
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($currentPage < 1) $currentPage = 1;
$offset = ($currentPage - 1) * $itemsPerPage;

// Fetch all inventory items for the logged-in user with pagination
$sql = "SELECT InventoryID, ProductName, ProductType, CurrentStock, PricePerStock, TotalExpense 
        FROM inventory 
        WHERE userID = :userID 
        LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':userID', $_SESSION['userID'], PDO::PARAM_INT);
$stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

// Fetch all results
$inventoryItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch total number of items for pagination
$countSql = "SELECT COUNT(*) FROM inventory WHERE userID = :userID";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute([':userID' => $_SESSION['userID']]);
$totalItems = $countStmt->fetchColumn();
$totalPages = ceil($totalItems / $itemsPerPage);

// Fetch aggregated expenses per month and year from inventory_expenses for the current year
$currentYear = date('Y');
$expenseSql = "SELECT 
                MONTH(ExpenseDate) AS month, 
                SUM(Amount) AS monthly_expense 
              FROM inventory_expenses 
              WHERE InventoryID IN (SELECT InventoryID FROM inventory WHERE userID = :userID)
                AND YEAR(ExpenseDate) = :currentYear
              GROUP BY MONTH(ExpenseDate)
              ORDER BY MONTH(ExpenseDate)";
$expenseStmt = $pdo->prepare($expenseSql);
$expenseStmt->execute([
    ':userID' => $_SESSION['userID'],
    ':currentYear' => $currentYear
]);
$monthlyExpenses = $expenseStmt->fetchAll(PDO::FETCH_ASSOC);

// Function to get month name
function getMonthName($monthNumber) {
    return DateTime::createFromFormat('!m', $monthNumber)->format('F');
}
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

</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <img src="../img/ane-laundry-logo.png" alt="AN'E Laundry Logo" class="sidebar-logo"> 
        <ul>
            <li><a href="../php/maindashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="inventorylist.php" class="active"><i class="fas fa-box"></i> Inventory</a></li>
            <li><a href="../html/laundrylist.html"><i class="fas fa-list-alt"></i> Laundries List</a></li>
            <li><a href="../php/manageuser.php"><i class="fas fa-users-cog"></i> Manage User</a></li>
            <li><a href="../php/logout.php" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Log Out</a></li>
        </ul>
    </div>

    <!-- Main content area -->
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

        <!-- Success and Error Messages -->
        <?php if ($addSuccess): ?>
            <div class="success-message" id="successMessage">
                Stock successfully added!
            </div>
        <?php elseif ($editSuccess): ?>
            <div class="success-message" id="successMessage">
                Stock successfully updated!
            </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="error-message" id="errorMessage">
                <?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <!-- Inventory List Header Section -->
        <div class="inventory-list-header">
            <h2>Inventory</h2>
            <button class="print-btn" id="printBtn" aria-label="Print Inventory Report"><i class="fas fa-print" aria-hidden="true">&nbsp;&nbsp;</i>Print Report</button>
            <button class="add-inventory-btn" id="addStockBtn"><i class="fas fa-plus">&nbsp;&nbsp;</i>Add Stock</button>
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
                <option value="unknown">Unknown</option>
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
                    <th>Price Per Stock (₱)</th>
                    <th>Total Expense (₱)</th>
                    <th>Status</th>
                    <th>Edit</th>
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

                        // Calculate expense for the current item
                        // If TotalExpense is already in the database, use it
                        $totalExpense = $row['TotalExpense'];
                        // Removed accumulation of $totalExpenses since Total Inventory Expenses is removed

                        echo "<tr>";
                        echo "<td>#".htmlspecialchars($row['InventoryID'], ENT_QUOTES, 'UTF-8')."</td>";
                        echo "<td>".htmlspecialchars($row['ProductName'], ENT_QUOTES, 'UTF-8')."</td>";
                        echo "<td>".htmlspecialchars($row['ProductType'], ENT_QUOTES, 'UTF-8')."</td>";
                        echo "<td>".htmlspecialchars($row['CurrentStock'], ENT_QUOTES, 'UTF-8')."</td>";
                        echo "<td>".number_format($row['PricePerStock'], 2)."</td>";
                        echo "<td>".number_format($totalExpense, 2)."</td>";
                        echo "<td><span class='status ".$status."'>".$statusText."</span></td>";
                        // Add data attributes to the edit button
                        echo "<td><button class='edit-btn' 
                            data-id='".htmlspecialchars($row['InventoryID'], ENT_QUOTES, 'UTF-8')."'
                            data-productname='".htmlspecialchars($row['ProductName'], ENT_QUOTES, 'UTF-8')."'
                            data-producttype='".htmlspecialchars($row['ProductType'], ENT_QUOTES, 'UTF-8')."'
                            data-currentstock='".htmlspecialchars($row['CurrentStock'], ENT_QUOTES, 'UTF-8')."'>
                            <i class='fas fa-edit'></i></button></td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='8'>No records found.</td></tr>";
                }
                ?>
            </tbody>
        </table>

        <!-- Add Stock Modal -->
        <div id="addStockModal" class="modal" role="dialog" aria-labelledby="addStockModalTitle" aria-modal="true">
            <div class="modal-content">
                <span class="close" role="button" aria-label="Close">&times;</span>
                <h3 id="addStockModalTitle" class="modal-title">Add Stock</h3>
                
                <form id="addStockForm" method="POST" action="addstock.php">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="form-group">
                        <label for="inventoryID">Product Name:</label>
                        <select id="inventoryID" name="InventoryID" required>
                            <option value="" disabled selected>Select a Product</option>
                            <?php
                            // Fetch inventory items from the database
                            foreach ($inventoryItems as $item) {
                                echo "<option value=\"" . htmlspecialchars($item['InventoryID'], ENT_QUOTES, 'UTF-8') . "\">" 
                                     . htmlspecialchars($item['ProductName'], ENT_QUOTES, 'UTF-8') . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="quantity">Quantity to Add:</label>
                        <input type="number" id="quantity" name="quantity" min="1" required>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="submit-btn">Add Stock</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Stock Modal -->
        <div id="editStockModal" class="modal" role="dialog" aria-labelledby="editStockModalTitle" aria-modal="true">
            <div class="modal-content">
                <span class="close edit-close" role="button" aria-label="Close">&times;</span>
                <h3 id="editStockModalTitle" class="modal-title">Edit Stock</h3>
                
                <form id="editStockForm" method="POST" action="editstock.php">
                    <input type="hidden" id="editInventoryID" name="InventoryID">
                    <div class="form-group">
                        <label for="editProductName">Product Name:</label>
                        <input type="text" id="editProductName" name="editProductName" required readonly>
                    </div>

                    <div class="form-group">
                        <label for="editProductType">Product Type:</label>
                        <input type="text" id="editProductType" name="editProductType" required readonly>
                    </div>

                    <div class="form-group">
                        <label for="editQuantity">Current Stock:</label>
                        <input type="number" id="editQuantity" name="editQuantity" min="0" required>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="submit-btn">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Report Section (Hidden on Screen, Visible on Print) -->
        <div id="printReport" class="print-report">
            <h1>AN'E Laundry Inventory Report</h1>
            <h2><?php echo date('F Y'); ?></h2>
            
            <!-- Current Inventory Stock -->
            <h3>Current Inventory Stock</h3>
            <table class="inventory-table">
                <thead>
                    <tr>
                        <th>Product ID</th>
                        <th>Product Name</th>
                        <th>Type</th>
                        <th>Current Stock</th>
                        <th>Price Per Stock (₱)</th>
                        <th>Total Expense (₱)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (count($inventoryItems) > 0) {
                        foreach ($inventoryItems as $item) {
                            echo "<tr>";
                            echo "<td>#".htmlspecialchars($item['InventoryID'], ENT_QUOTES, 'UTF-8')."</td>";
                            echo "<td>".htmlspecialchars($item['ProductName'], ENT_QUOTES, 'UTF-8')."</td>";
                            echo "<td>".htmlspecialchars($item['ProductType'], ENT_QUOTES, 'UTF-8')."</td>";
                            echo "<td>".htmlspecialchars($item['CurrentStock'], ENT_QUOTES, 'UTF-8')."</td>";
                            echo "<td>".number_format($item['PricePerStock'], 2)."</td>";
                            echo "<td>".number_format($item['TotalExpense'], 2)."</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='6'>No inventory records found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>

            <!-- Stocks Added Today -->
            <h3>Stocks Added Today</h3>
            <table class="stocks-today-table">
                <thead>
                    <tr>
                        <th>Product ID</th>
                        <th>Product Name</th>
                        <th>Quantity Added</th>
                        <th>Price Per Stock (₱)</th>
                        <th>Total Expense (₱)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Fetch stocks added today
                    $today = date('Y-m-d');
                    $stocksTodaySql = "SELECT 
                                            inventory_expenses.InventoryID, 
                                            inventory.ProductName, 
                                            inventory_expenses.Amount AS TotalExpense, 
                                            inventory.PricePerStock,
                                            inventory_expenses.Description
                                       FROM inventory_expenses 
                                       JOIN inventory ON inventory_expenses.InventoryID = inventory.InventoryID
                                       WHERE inventory.userID = :userID 
                                         AND DATE(inventory_expenses.ExpenseDate) = :today
                                         AND inventory_expenses.Description LIKE 'Added stock:%'";
                    $stocksTodayStmt = $pdo->prepare($stocksTodaySql);
                    $stocksTodayStmt->execute([
                        ':userID' => $_SESSION['userID'],
                        ':today' => $today
                    ]);
                    $stocksToday = $stocksTodayStmt->fetchAll(PDO::FETCH_ASSOC);

                    // Debugging: Uncomment the following lines to see the fetched data
                    /*
                    echo "<h4>Debugging: Stocks Added Today</h4>";
                    echo "<pre>";
                    print_r($stocksToday);
                    echo "</pre>";
                    */

                    if (count($stocksToday) > 0) {
                        foreach ($stocksToday as $stock) {
                            // Extract quantity from the Description
                            // Assuming Description is "Added stock: X units."
                            $description = $stock['Description']; // Fetch the Description
                            $quantityAdded = 0;
                            if (preg_match('/Added stock:\s*(\d+)\s*units\./i', $description, $matches)) {
                                $quantityAdded = (int)$matches[1];
                            }

                            echo "<tr>";
                            echo "<td>#".htmlspecialchars($stock['InventoryID'], ENT_QUOTES, 'UTF-8')."</td>";
                            echo "<td>".htmlspecialchars($stock['ProductName'], ENT_QUOTES, 'UTF-8')."</td>";
                            echo "<td>".htmlspecialchars($quantityAdded, ENT_QUOTES, 'UTF-8')."</td>";
                            echo "<td>".number_format($stock['PricePerStock'], 2)."</td>";
                            echo "<td>".number_format($stock['TotalExpense'], 2)."</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5'>No stocks added today.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>

            <!-- Expenses Summary for Current Year -->
            <h3>Expenses Summary for <?php echo $currentYear; ?></h3>
            <table class="expenses-summary-table">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Expense (₱)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (count($monthlyExpenses) > 0) {
                        foreach ($monthlyExpenses as $expense) {
                            echo "<tr>";
                            echo "<td>".htmlspecialchars(getMonthName($expense['month']), ENT_QUOTES, 'UTF-8')."</td>";
                            echo "<td>".number_format($expense['monthly_expense'], 2)."</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='2'>No expense records found for the current year.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- JavaScript -->
        <script>
        // Generic function to handle modal behavior
        function setupModal(modalId, openBtnId, closeBtnClass) {
            var modal = document.getElementById(modalId);
            var openBtn = openBtnId ? document.getElementById(openBtnId) : null;
            var closeBtn = modal.querySelector(closeBtnClass);

            // Function to open the modal
            function openModal() {
                modal.style.display = 'flex';
                setTimeout(function() {
                    modal.classList.add('show');
                }, 10);
                document.body.classList.add('modal-active');
            }

            // Function to close the modal
            function closeModal() {
                modal.classList.remove('show');
                setTimeout(function() {
                    modal.style.display = 'none';
                    document.body.classList.remove('modal-active');
                }, 500);
            }

            // Event listeners
            if (openBtn) {
                openBtn.addEventListener('click', openModal);
            }
            closeBtn.addEventListener('click', closeModal);
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    closeModal();
                }
            });
        }

        // Setup Add Stock Modal
        setupModal('addStockModal', 'addStockBtn', '.close');

        // Setup Edit Stock Modal
        setupModal('editStockModal', null, '.edit-close');

        // Function to attach event listeners to edit buttons using event delegation
        function attachEditButtonListeners() {
            var inventoryTableBody = document.getElementById('inventoryTableBody');

            inventoryTableBody.addEventListener('click', function(event) {
                var button = event.target.closest('.edit-btn');
                if (button) {
                    var inventoryID = button.getAttribute('data-id');
                    var productName = button.getAttribute('data-productname');
                    var productType = button.getAttribute('data-producttype');
                    var currentStock = button.getAttribute('data-currentstock');

                    // Populate the Edit Stock Modal fields
                    document.getElementById('editInventoryID').value = inventoryID;
                    document.getElementById('editProductName').value = productName;
                    document.getElementById('editProductType').value = productType;
                    document.getElementById('editQuantity').value = currentStock;

                    // Open the Edit Stock Modal
                    var editStockModal = document.getElementById('editStockModal');
                    editStockModal.style.display = 'flex';
                    setTimeout(function() {
                        editStockModal.classList.add('show');
                    }, 10);
                    document.body.classList.add('modal-active');
                }
            });
        }

        // Initial attachment of event listeners
        attachEditButtonListeners();

        // JavaScript for Client-Side Search with Debouncing
        const searchInput = document.getElementById('search');
        const statusFilter = document.getElementById('statusFilter');
        const table = document.querySelector('table tbody');

        let debounceTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimeout);
            debounceTimeout = setTimeout(filterTable, 300);
        });
        statusFilter.addEventListener('change', filterTable);

        function filterTable() {
            const searchValue = searchInput.value.toLowerCase();
            const statusValue = statusFilter.value.toLowerCase();
            const rows = table.querySelectorAll('tr');

            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length < 8) return; // Skip if not enough cells (e.g., no records)

                const productID = cells[0].textContent.toLowerCase().replace('#', '');
                const productName = cells[1].textContent.toLowerCase();
                const productType = cells[2].textContent.toLowerCase();
                const currentStatus = cells[6].textContent.toLowerCase();

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

        // JavaScript to handle the success and error messages
        document.addEventListener('DOMContentLoaded', function() {
            var successMessage = document.getElementById('successMessage');
            var errorMessage = document.getElementById('errorMessage');

            if (successMessage) {
                // Automatically hide the message after 5 seconds
                setTimeout(function() {
                    successMessage.style.display = 'none';
                }, 5000);
            }

            if (errorMessage) {
                // Automatically hide the message after 7 seconds
                setTimeout(function() {
                    errorMessage.style.display = 'none';
                }, 7000);
            }
        });

        // Print Report Functionality
        document.getElementById('printBtn').addEventListener('click', function() {
            window.print();
        });

        // Keyboard navigation for modals (closing with Esc key)
        window.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                var modals = document.querySelectorAll('.modal.show');
                modals.forEach(function(modal) {
                    modal.classList.remove('show');
                    modal.style.display = 'none';
                });
                document.body.classList.remove('modal-active');
            }
        });

        // Sidebar Active State without jQuery
        document.addEventListener('DOMContentLoaded', function() {
            var path = window.location.pathname.split("/").pop() || "index.html";
            var links = document.querySelectorAll('.sidebar ul li a');
            links.forEach(function(link) {
                if (link.getAttribute('href') === path) {
                    link.classList.add('active');
                }
            });
        });
        </script>
</body>
</html>
