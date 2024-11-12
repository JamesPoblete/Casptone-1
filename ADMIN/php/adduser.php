<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>"; // Include SweetAlert2 at the top

require 'dbconnection.php';  // Ensure this path is correct

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Hide the "Received POST request" message by changing its color to white
    echo "<div style='color: white;'>Received POST request.</div>";

    // Retrieve form data
    $name = $_POST['name'];
    $usertype = $_POST['usertype']; // Capture user type
    $username = $_POST['username'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm-password'];

    // Validate that passwords match
    if ($password !== $confirm_password) {
        echo "<script>alert('Passwords do not match.'); window.history.back();</script>";
        exit();
    }

    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Connect to the database
    $pdo = connectDB();

    // Check if the username already exists
    $checkSql = "SELECT * FROM Account WHERE user_name = :username";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([':username' => $username]);

    if ($checkStmt->rowCount() > 0) {
        echo "<script>alert('Username already exists.'); window.history.back();</script>";
        exit();
    }

    // Insert the new user
    $sql = "INSERT INTO Account (user_name, user_pass, name, user_type) VALUES (:username, :password, :name, :usertype)";
    $stmt = $pdo->prepare($sql);

    try {
        $stmt->execute([
            ':username' => $username,
            ':password' => $hashed_password,
            ':name' => $name,
            ':usertype' => $usertype // Include user type in the insert
        ]);

        // Only show SweetAlert if the insert was successful
        if ($stmt->rowCount() > 0) {
            echo "<script>
                    Swal.fire({
                      title: 'Account Created Successfully',
                      text: 'You will be redirected shortly.',
                      icon: 'success',
                      timer: 2000,
                      showConfirmButton: false
                    }).then(() => {
                      window.location.href = '../php/manageuser.php'; // Adjusted path to your manageuser.html
                    });
                  </script>";
        }
    } catch (PDOException $e) {
        echo "<script>alert('Error: " . addslashes($e->getMessage()) . "');</script>";
    }
}


ob_end_flush();
?>