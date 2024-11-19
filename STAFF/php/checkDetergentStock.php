<?php
// checkDetergentStock.php
include 'dbconnection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize input
    $detergentType = isset($_POST['detergent_type']) ? trim($_POST['detergent_type']) : '';
    $requiredCount = isset($_POST['required_count']) ? intval($_POST['required_count']) : 0;

    if (empty($detergentType)) {
        echo json_encode(['success' => false, 'message' => 'Detergent type is required.']);
        exit;
    }

    if ($requiredCount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Required count must be greater than zero.']);
        exit;
    }

    $conn = connectDB();

    try {
        // Prepare and execute the query to fetch current stock
        $stmt = $conn->prepare("SELECT CurrentStock FROM inventory WHERE ProductName = :productName");
        $stmt->bindParam(':productName', $detergentType, PDO::PARAM_STR);
        $stmt->execute();

        $stock = $stmt->fetchColumn();

        if ($stock === false) {
            echo json_encode(['success' => false, 'message' => 'Detergent type not found in inventory.']);
        } else {
            echo json_encode(['success' => true, 'stock' => intval($stock)]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching stock: ' . $e->getMessage()]);
    }

    // Close the connection
    $conn = null;
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>
