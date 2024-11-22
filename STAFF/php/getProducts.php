<?php
// getProducts.php

session_start();

// Check if the user is logged in
if (!isset($_SESSION['userID'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Check if ProductType is provided
if (!isset($_GET['type'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Product type not specified.']);
    exit();
}

$productType = $_GET['type'];

// Validate ProductType
$allowedTypes = ['Detergent', 'Fabric Detergent'];
if (!in_array($productType, $allowedTypes)) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Invalid product type.']);
    exit();
}

// Include database connection
include 'dbconnection.php';

try {
    $pdo = connectDB();
    
    // Prepare the SQL statement
    $sql = "SELECT InventoryID, ProductName FROM inventory 
            WHERE userID = :userID AND ProductType = :productType AND CurrentStock > 0
            ORDER BY ProductName ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':userID' => $_SESSION['userID'],
        ':productType' => $productType
    ]);
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $products]);
} catch (PDOException $e) {
    // Handle database errors
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit();
}
?>
