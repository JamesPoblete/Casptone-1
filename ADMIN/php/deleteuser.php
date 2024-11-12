<?php
require 'dbconnection.php'; // Ensure this path is correct

// Connect to the database
$pdo = connectDB();

header('Content-Type: application/json');

try {
    // Get the JSON input
    $data = json_decode(file_get_contents("php://input"), true);
    
    // Check if userId is provided
    if (!isset($data['userId'])) {
        echo json_encode(['status' => 'error', 'message' => 'No user selected!']);
        exit;
    }

    $userId = $data['userId'];

    // Prepare and execute delete query
    $stmt = $pdo->prepare("DELETE FROM Account WHERE userID = :userId");
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->execute();

    // Check if the user was deleted
    if ($stmt->rowCount() > 0) {
        echo json_encode(['status' => 'success', 'message' => 'User deleted successfully!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'User not found or already deleted!']);
    }

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}