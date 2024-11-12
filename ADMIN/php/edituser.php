<?php
require 'dbconnection.php'; // Ensure this path is correct

// Get the raw POST data
$data = json_decode(file_get_contents('php://input'), true);
$userId = $data['userId'];
$newUserName = $data['newUserName'];
$newUserType = $data['newUserType'];

// Connect to the database
$pdo = connectDB();

// Update the user in the database
try {
    $sql = "UPDATE Account SET user_name = :user_name, user_type = :user_type WHERE userID = :userID";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':user_name' => $newUserName,
        ':user_type' => $newUserType,
        ':userID' => $userId
    ]);

    // Return a success response
    echo json_encode(['message' => 'User updated successfully!']);
} catch (PDOException $e) {
    // Return an error response
    echo json_encode(['message' => 'Error: ' . $e->getMessage()]);
}
?>