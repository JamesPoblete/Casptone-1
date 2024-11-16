<?php
// addstock.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start(); // Start the session

include 'dbconnection.php';

// Set the desired timezone
date_default_timezone_set('Asia/Manila'); // Change to your timezone if different

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check if the user is logged in
    if (!isset($_SESSION['userID'])) {
        // User is not logged in, redirect to login page
        header("Location: ../html/login.html");
        exit();
    }

    // CSRF Protection: Validate CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error_message'] = 'Invalid CSRF token.';
        header("Location: inventorylist.php");
        exit();
    }

    // Retrieve and sanitize form data
    $inventoryID = isset($_POST['InventoryID']) ? (int)$_POST['InventoryID'] : 0;
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;

    // Basic validation
    if ($inventoryID <= 0 || $quantity <= 0) {
        $_SESSION['error_message'] = 'Invalid input data. Please select a valid product and enter a positive quantity.';
        header("Location: inventorylist.php");
        exit();
    }

    // Get the PDO connection
    $pdo = connectDB();

    try {
        // Verify that the InventoryID exists and belongs to the user
        $verifySql = "SELECT InventoryID, PricePerStock FROM inventory WHERE InventoryID = :inventoryID AND userID = :userID LIMIT 1";
        $verifyStmt = $pdo->prepare($verifySql);
        $verifyStmt->execute([
            ':inventoryID' => $inventoryID,
            ':userID' => $_SESSION['userID']
        ]);
        $inventory = $verifyStmt->fetch(PDO::FETCH_ASSOC);

        if (!$inventory) {
            $_SESSION['error_message'] = 'Invalid Inventory Item.';
            header("Location: inventorylist.php");
            exit();
        }

        $pricePerStock = (float)$inventory['PricePerStock'];
        $totalExpense = $pricePerStock * $quantity;
        $expenseDate = date('Y-m-d'); // Current date in YYYY-MM-DD format

        // Begin Transaction
        $pdo->beginTransaction();

        // Update CurrentStock and TotalExpense in the inventory table
        $updateInventorySql = "UPDATE inventory 
                               SET CurrentStock = CurrentStock + :quantity, 
                                   TotalExpense = TotalExpense + :totalExpense 
                               WHERE InventoryID = :inventoryID";
        $updateInventoryStmt = $pdo->prepare($updateInventorySql);
        $updateInventoryStmt->execute([
            ':quantity' => $quantity,
            ':totalExpense' => $totalExpense,
            ':inventoryID' => $inventoryID
        ]);

        // Insert into inventory_expenses table with dynamic Description
        $insertExpenseSql = "INSERT INTO inventory_expenses (InventoryID, Amount, ExpenseDate, Description) 
                             VALUES (:inventoryID, :amount, :expenseDate, :description)";
        $insertExpenseStmt = $pdo->prepare($insertExpenseSql);
        $insertExpenseStmt->execute([
            ':inventoryID' => $inventoryID,
            ':amount' => $totalExpense,
            ':expenseDate' => $expenseDate,
            ':description' => "Added stock: {$quantity} units." // Dynamic Description
        ]);

        // Commit Transaction
        $pdo->commit();

        // Set success flag in session
        $_SESSION['add_success'] = true;

        // Redirect back to the inventory list
        header("Location: inventorylist.php");
        exit();
    } catch (PDOException $e) {
        // Rollback Transaction on Error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Log the error message to a file
        // Ensure the directory and file have appropriate permissions
        error_log("Error in addstock.php: " . $e->getMessage(), 3, "/path/to/your/error.log"); // Update the path as needed
        
        // Set a user-friendly error message
        $_SESSION['error_message'] = 'An error occurred while adding stock. Please try again later.';
        header("Location: inventorylist.php");
        exit();
    }
} else {
    // If not a POST request, redirect to inventory list
    header("Location: inventorylist.php");
    exit();
}
?>
