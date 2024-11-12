<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'dbconnection.php'; // Ensure correct path

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Establish the database connection
        $conn = connectDB();

        // SQL query to fetch the latest laundry entry based on OrderID
        $sql = "SELECT * FROM laundry ORDER BY OrderID DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute();

        // Fetch the row as an associative array
        $latestLaundry = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($latestLaundry) {
            echo json_encode(['success' => true, 'data' => $latestLaundry]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No laundry entries found.']);
        }

    } catch (PDOException $e) {
        // Log the error internally and return a generic message
        error_log("Database Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred while fetching the latest laundry entry.']);
    }

    // Close the connection
    $conn = null;
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>
