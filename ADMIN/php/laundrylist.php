<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'dbconnection.php'; // Ensure correct path

// Establish the database connection
$conn = connectDB();

// SQL query to fetch the required columns from the laundry table
$sql = "SELECT OrderID, NAME, DATE, STATUS, PAYMENT_STATUS FROM laundry";
$stmt = $conn->prepare($sql); // Prepare the SQL statement
$stmt->execute(); // Execute the query

// Fetch all the rows as an associative array
$laundryData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set the content type to JSON
header('Content-Type: application/json');

// Convert the PHP array to JSON and output it
echo json_encode($laundryData);

// Close the connection
$conn = null;
?>
