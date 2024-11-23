<?php
// php/updatePaymentStatus.php

// Disable error display and enable logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Set content type to JSON
header('Content-Type: application/json');

// Include the database connection script
include 'dbconnection.php';

// Check if required POST parameters are set
if (!isset($_POST['OrderID']) || !isset($_POST['PaymentStatus'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters for payment status update.']);
    exit();
}

$orderID = $_POST['OrderID'];
$newPaymentStatus = $_POST['PaymentStatus'];

// Optional: Validate the new payment status
$allowedPaymentStatuses = ['Unpaid', 'Paid'];
if (!in_array($newPaymentStatus, $allowedPaymentStatuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid payment status value.']);
    exit();
}

try {
    $conn = connectDB();

    // SQL to update payment status
    $sql = "UPDATE laundry SET PAYMENT_STATUS = :paymentStatus WHERE OrderID = :orderID";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':paymentStatus', $newPaymentStatus);
    $stmt->bindParam(':orderID', $orderID, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Payment status updated successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No records updated.']);
    }
    exit();
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    exit();
}
?>
