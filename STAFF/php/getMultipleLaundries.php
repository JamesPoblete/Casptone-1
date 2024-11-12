<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'dbconnection.php'; // Ensure correct path

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ids']) && is_array($_GET['ids'])) {
    try {
        // Establish the database connection
        $conn = connectDB();

        // Prepare placeholders for the IN clause
        $placeholders = rtrim(str_repeat('?,', count($_GET['ids'])), ',');

        // SQL query to fetch multiple laundry entries based on OrderIDs
        $sql = "SELECT * FROM laundry WHERE OrderID IN ($placeholders) ORDER BY DATE DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute($_GET['ids']);

        // Fetch all rows as an associative array
        $laundryData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($laundryData) {
            echo json_encode(['success' => true, 'data' => $laundryData]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No matching laundry entries found.']);
        }

    } catch (PDOException $e) {
        // Log the error internally and return a generic message
        error_log("Database Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred while fetching laundry entries.']);
    }

    // Close the connection
    $conn = null;
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request parameters.']);
}
?>
