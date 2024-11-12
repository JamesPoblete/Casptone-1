<?php
// php/updateStatus.php

// Disable error display and enable logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Set content type to JSON
header('Content-Type: application/json');

// Include the database connection script
include 'dbconnection.php';

// Check if required POST parameters are set
if (!isset($_POST['OrderID']) || !isset($_POST['Status'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters.']);
    exit();
}

$orderID = $_POST['OrderID'];
$newStatus = $_POST['Status'];

// Validate the new status
$allowedStatuses = ['Pending', 'Completed'];
if (!in_array($newStatus, $allowedStatuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status value.']);
    exit();
}

try {
    $conn = connectDB();
    $sql = "UPDATE laundry SET STATUS = :status WHERE OrderID = :orderID";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':status', $newStatus);
    $stmt->bindParam(':orderID', $orderID, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Status updated successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No records updated.']);
    }
    exit();
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    exit();
}
?>
