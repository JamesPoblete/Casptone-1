<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start(); // Start the session

include 'dbconnection.php';

$pdo = connectDB();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Retrieve userID from session
    if (isset($_SESSION['userID'])) {
        $userID = $_SESSION['userID'];
    } else {
        // User not logged in
        echo "Error: User not logged in.";
        exit();
    }

    // Retrieve and sanitize form data
    $InventoryID = intval($_POST['InventoryID']); // Hidden input in the form
    $ProductName = trim($_POST['editProductName']);
    $ProductType = trim($_POST['editProductType']);
    $CurrentStock = intval($_POST['editQuantity']);
    // Assuming ReorderLevel and InventoryDescription are optional
    $ReorderLevel = isset($_POST['editReorderLevel']) ? intval($_POST['editReorderLevel']) : null;
    $InventoryDescription = isset($_POST['editInventoryDescription']) ? trim($_POST['editInventoryDescription']) : '';

    try {
        // Prepare the SQL statement with named placeholders
        $sql = "UPDATE inventory SET 
                    ProductName = :ProductName, 
                    ProductType = :ProductType, 
                    CurrentStock = :CurrentStock, 
                    ReorderLevel = :ReorderLevel, 
                    InventoryDescription = :InventoryDescription 
                WHERE InventoryID = :InventoryID AND userID = :userID";
        $stmt = $pdo->prepare($sql);

        // Bind parameters
        $stmt->bindParam(':ProductName', $ProductName);
        $stmt->bindParam(':ProductType', $ProductType);
        $stmt->bindParam(':CurrentStock', $CurrentStock, PDO::PARAM_INT);
        $stmt->bindParam(':ReorderLevel', $ReorderLevel, PDO::PARAM_INT);
        $stmt->bindParam(':InventoryDescription', $InventoryDescription);
        $stmt->bindParam(':InventoryID', $InventoryID, PDO::PARAM_INT);
        $stmt->bindParam(':userID', $userID, PDO::PARAM_INT);

        // Execute the statement
        if ($stmt->execute()) {
            // Set success flag in session
            $_SESSION['edit_success'] = true;

            // Redirect back to the inventory list
            header("Location: inventorylist.php");
            exit();
        } else {
            // Handle execution failure
            echo "Error: Could not execute the update statement.";
        }
    } catch (PDOException $e) {
        // Handle any PDO exceptions
        echo "Error: " . $e->getMessage();
    }
}
?>
