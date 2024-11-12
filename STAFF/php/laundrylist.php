<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'dbconnection.php'; // Ensure correct path

try {
    // Establish the database connection
    $conn = connectDB();

    // SQL query to fetch the required columns from the laundry table
    $sql = "SELECT OrderID, NAME, DATE, STATUS, PICKUP_TIME FROM laundry ORDER BY OrderID DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();

    // Fetch all the rows as an associative array
    $laundryData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Set the content type to JSON
    header('Content-Type: application/json');

    // Convert the PHP array to JSON and output it
    echo json_encode($laundryData);

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// Close the connection
$conn = null;
?>
