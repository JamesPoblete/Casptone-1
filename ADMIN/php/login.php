<?php
session_start();
require 'dbconnection.php'; // Ensure this path is correct

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the username and password from the form
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Connect to the database
    $pdo = connectDB();

    // Prepare the SQL statement to fetch user details, including userID
    $sql = "SELECT userID, user_pass, user_type FROM Account WHERE user_name = :username";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    
    // Fetch the user details
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verify the password and check the role
    if ($user && password_verify($password, $user['user_pass']) && strtolower($user['user_type']) === 'admin') {
        // Set session variables, including userID
        $_SESSION['userID'] = $user['userID']; // Store userID in session
        $_SESSION['username'] = $username;
        $_SESSION['user_type'] = $user['user_type'];

        // Redirect to the main dashboard
        header('Location: ../php/maindashboard.php');
        exit;
    } else {
        // Invalid credentials or role, use SweetAlert
        echo "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Login Error</title>
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        </head>
        <body>
            <script>
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Credentials',
                    text: 'Invalid username or password, or you do not have the required role.',
                    confirmButtonText: 'OK'
                }).then(() => {
                    window.location.href='../html/login.html'; // Redirect after closing alert
                });
            </script>
        </body>
        </html>
        ";
        exit; // Ensure no further output is sent
    }
}
?>
