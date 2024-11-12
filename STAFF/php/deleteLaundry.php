<?php
// deleteLaundry.php

header('Content-Type: application/json');
include 'dbconnection.php'; // Ensure correct path

// Disable displaying errors in output and enable error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

if (!isset($_POST['orderIDs']) || !is_array($_POST['orderIDs'])) {
    echo json_encode(['success' => false, 'message' => 'No Order IDs provided.']);
    exit;
}

$orderIDs = $_POST['orderIDs'];

if (empty($orderIDs)) {
    echo json_encode(['success' => false, 'message' => 'Order IDs array is empty.']);
    exit;
}

try {
    // Establish the database connection
    $conn = connectDB();

    // Prepare the DELETE statement with placeholders
    // Use positional placeholders for security
    $placeholders = rtrim(str_repeat('?,', count($orderIDs)), ',');
    $sql = "DELETE FROM laundry WHERE OrderID IN ($placeholders)";
    $stmt = $conn->prepare($sql);

    // Execute the statement with the OrderIDs
    $stmt->execute($orderIDs);

    // Check how many rows were deleted
    $deletedRows = $stmt->rowCount();

    echo json_encode(['success' => true, 'deletedRows' => $deletedRows]);

} catch (PDOException $e) {
    // Log the error
    error_log("Error deleting laundry entries: " . $e->getMessage());

    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

// Close the connection
$conn = null;
?>
