<?php
// getLastOrderID.php
header('Content-Type: application/json');

// Include your database configuration
include 'dbconnection.php'; 

// Initialize response array
$response = ['success' => false, 'lastOrderID' => null, 'message' => ''];

// Establish PDO connection using connectDB()
try {
    $pdo = connectDB();
} catch (Exception $e) {
    // Connection failed
    $response['message'] = 'Database connection failed.';
    echo json_encode($response);
    exit();
}

// Define the table name and column name
$tableName = "laundry"; // Ensure this matches your actual table name
$columnName = "OrderID"; // Ensure this matches your actual column name and case

try {
    // Prepare the SQL statement to fetch the last OrderID
    $stmt = $pdo->prepare("SELECT `$columnName` FROM `$tableName` ORDER BY `$columnName` DESC LIMIT 1");
    
    // Execute the statement
    $stmt->execute();
    
    // Fetch the result
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result && isset($result[$columnName])) {
        // Successfully retrieved the last OrderID
        $lastOrderID = intval($result[$columnName]);
        $nextOrderID = $lastOrderID + 1;
        $response['success'] = true;
        $response['lastOrderID'] = $nextOrderID;
    } else {
        // No existing orders, start with 1
        $response['success'] = true;
        $response['lastOrderID'] = 1;
    }
    
} catch (PDOException $e) {
    // Log the error for debugging purposes
    error_log("PDOException in getLastOrderID.php: " . $e->getMessage());
    
    // Update response message
    $response['message'] = 'An internal error occurred.';
}

echo json_encode($response);
?>
