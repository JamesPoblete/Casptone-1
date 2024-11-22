<?php
// addinventory.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start(); // Start the session

// Check if the user is logged in
if (!isset($_SESSION['userID'])) {
    // User is not logged in, redirect to login page
    header("Location: ../html/login.html");
    exit();
}

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error_message'] = 'Invalid CSRF token.';
        header("Location: inventorylist.php");
        exit();
    }

    // Include the database connection
    include 'dbconnection.php';

    // Get the PDO connection
    $pdo = connectDB();

    // Set MySQL session timezone to match PHP timezone
    $pdo->exec("SET time_zone = '".date('P')."'");

    // Retrieve and sanitize form inputs
    $productName = trim($_POST['ProductName'] ?? '');
    $productType = trim($_POST['ProductType'] ?? '');
    $pricePerStock = floatval($_POST['PricePerStock'] ?? 0.0);
    $initialStock = intval($_POST['InitialStock'] ?? 0);

    // Validate inputs
    if (empty($productName) || empty($productType)) {
        $_SESSION['error_message'] = 'Please fill in all required fields.';
        header("Location: inventorylist.php");
        exit();
    }

    if ($pricePerStock < 0 || $initialStock < 0) {
        $_SESSION['error_message'] = 'Price and Quantity must be non-negative.';
        header("Location: inventorylist.php");
        exit();
    }

    try {
        // Begin transaction
        $pdo->beginTransaction();

        // Insert the new product into the inventory table
        $insertSql = "INSERT INTO inventory (userID, ProductName, ProductType, CurrentStock, PricePerStock, TotalExpense)
                      VALUES (:userID, :ProductName, :ProductType, :CurrentStock, :PricePerStock, :TotalExpense)";
        $insertStmt = $pdo->prepare($insertSql);
        $insertStmt->execute([
            ':userID' => $_SESSION['userID'],
            ':ProductName' => $productName,
            ':ProductType' => $productType,
            ':CurrentStock' => $initialStock,
            ':PricePerStock' => $pricePerStock,
            ':TotalExpense' => $pricePerStock * $initialStock
        ]);

        // Get the last inserted InventoryID
        $inventoryID = $pdo->lastInsertId();

        // If initial stock is greater than 0, add an entry to inventory_expenses
        if ($initialStock > 0) {
            $expenseSql = "INSERT INTO inventory_expenses (InventoryID, Amount, ExpenseDate, Description)
                           VALUES (:InventoryID, :Amount, :ExpenseDate, :Description)";
            $expenseStmt = $pdo->prepare($expenseSql);
            $expenseStmt->execute([
                ':InventoryID' => $inventoryID,
                ':Amount' => $pricePerStock * $initialStock,
                ':ExpenseDate' => date('Y-m-d H:i:s'),
                ':Description' => "Initial stock: {$initialStock} units."
            ]);
        }

        // Commit transaction
        $pdo->commit();

        // Set success message
        $_SESSION['add_product_success'] = true;
        header("Location: inventorylist.php");
        exit();

    } catch (Exception $e) {
        // Rollback transaction
        $pdo->rollBack();
        $_SESSION['error_message'] = 'Failed to add new product: ' . $e->getMessage();
        header("Location: inventorylist.php");
        exit();
    }

} else {
    // Invalid request method
    $_SESSION['error_message'] = 'Invalid request method.';
    header("Location: inventorylist.php");
    exit();
}
?>
