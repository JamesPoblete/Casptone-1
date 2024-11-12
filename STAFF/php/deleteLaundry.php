<?php
include 'dbconnection.php'; // Include your database connection file

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Establish the database connection
        $conn = connectDB();

        // Check if 'orderIDs' is provided in the POST request
        if (isset($_POST['orderIDs']) && is_array($_POST['orderIDs'])) {
            $orderIDs = $_POST['orderIDs'];

            // Debugging: Output the received order IDs
            var_dump($orderIDs); // This will print the array of order IDs to check if it's correct

            // Create a placeholder for the SQL IN clause
            $placeholders = implode(',', array_fill(0, count($orderIDs), '?'));

            // SQL query to delete selected orders
            $sql = "DELETE FROM laundry WHERE OrderID IN ($placeholders)";
            $stmt = $conn->prepare($sql);

            // Execute the query with the provided OrderIDs
            $stmt->execute($orderIDs);

            // Return a success response
            echo json_encode(["success" => true, "message" => "Orders deleted successfully."]);
        } else {
            // Return an error response if no orderIDs are provided
            echo json_encode(["success" => false, "message" => "No orders selected for deletion."]);
        }

    } catch (PDOException $e) {
        // Handle any errors
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }

    // Close the database connection
    $conn = null;
}
?>
