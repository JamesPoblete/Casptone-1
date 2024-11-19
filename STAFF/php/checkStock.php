<?php
// checkStock.php
include 'dbconnection.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize input
    $data = json_decode(file_get_contents('php://input'), true);
    $productName = isset($data['productName']) ? trim($data['productName']) : '';
    $requiredCount = isset($data['requiredCount']) ? intval($data['requiredCount']) : 0;

    if (empty($productName) || $requiredCount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid input.']);
        exit;
    }

    $conn = connectDB();

    try {
        // Prepare and execute the query to fetch current stock
        $stmt = $conn->prepare("SELECT CurrentStock FROM inventory WHERE ProductName = :productName");
        $stmt->bindParam(':productName', $productName, PDO::PARAM_STR);
        $stmt->execute();

        $stock = $stmt->fetchColumn();

        if ($stock === false) {
            echo json_encode(['success' => false, 'message' => 'Product not found in inventory.']);
        } elseif ($stock < $requiredCount) {
            echo json_encode(['success' => false, 'message' => "Only {$stock} left in stock for {$productName}."]);
        } else {
            echo json_encode(['success' => true]);
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
