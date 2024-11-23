<?php
// cleanup_expired_tokens.php
require 'dbconnection.php'; // Ensure this path is correct

// Set the timezone
date_default_timezone_set('Asia/Manila'); // Replace with your actual timezone

// Connect to the database
$pdo = connectDB();

// Delete expired tokens by setting them to NULL
$sql = "UPDATE Account SET password_reset_token = NULL, password_reset_expires = NULL WHERE password_reset_expires < NOW()";
$stmt = $pdo->prepare($sql);
$stmt->execute();

// Optionally, log the cleanup action
error_log("Expired password reset tokens cleaned up at " . date('Y-m-d H:i:s'));
?>
