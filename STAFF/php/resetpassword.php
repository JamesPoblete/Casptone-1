<?php
// resetpassword.php
session_start();
require 'dbconnection.php'; // Ensure this path is correct

// Set the timezone to ensure consistency
date_default_timezone_set('Asia/Manila'); // Replace with your actual timezone

// Connect to the database
$pdo = connectDB();

// Initialize variables
$user = null;
$token = '';

// Function to validate password strength (optional but recommended)
function isStrongPassword($password) {
    if (strlen($password) < 8) {
        return false;
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return false;
    }
    if (!preg_match('/[a-z]/', $password)) {
        return false;
    }
    if (!preg_match('/[0-9]/', $password)) {
        return false;
    }
    if (!preg_match('/[\W]/', $password)) {
        return false;
    }
    return true;
}

// Check if the token is provided via GET or POST
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['token'])) {
    $token = $_GET['token'];

    // Hash the token to compare with stored hashed token
    $hashedToken = hash('sha256', $token);

    // Fetch the user with the matching token and check if it's not expired
    $sql = "SELECT userID, user_name FROM Account WHERE password_reset_token = :hashedToken AND password_reset_expires > NOW()";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':hashedToken' => $hashedToken]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Invalid or expired token
        echo "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Invalid Token</title>
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        </head>
        <body>
            <script>
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid or Expired Token',
                    text: 'The password reset link is invalid or has expired.',
                    confirmButtonText: 'OK'
                }).then(() => {
                    window.location.href='../html/forgotpassword.html'; // Redirect after closing alert
                });
            </script>
        </body>
        </html>
        ";
        exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['token'])) {
    $token = $_POST['token'];

    // Hash the token to compare with stored hashed token
    $hashedToken = hash('sha256', $token);

    // Fetch the user with the matching token and check if it's not expired
    $sql = "SELECT userID FROM Account WHERE password_reset_token = :hashedToken AND password_reset_expires > NOW()";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':hashedToken' => $hashedToken]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Handle form submission to reset the password
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        // Validate passwords
        if ($password !== $confirm_password) {
            // Passwords do not match
            echo "
            <!DOCTYPE html>
            <html lang='en'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>Password Mismatch</title>
                <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
            </head>
            <body>
                <script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Password Mismatch',
                        text: 'Passwords do not match. Please try again.',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        window.location.href='resetpassword.php?token=" . htmlspecialchars($token) . "';
                    });
                </script>
            </body>
            </html>
            ";
            exit;
        }

        // Validate password strength
        if (!isStrongPassword($password)) {
            // Password does not meet strength requirements
            echo "
            <!DOCTYPE html>
            <html lang='en'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>Weak Password</title>
                <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
            </head>
            <body>
                <script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Weak Password',
                        text: 'Your password must be at least 8 characters long and include uppercase letters, lowercase letters, numbers, and special characters.',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        window.location.href='resetpassword.php?token=" . htmlspecialchars($token) . "';
                    });
                </script>
            </body>
            </html>
            ";
            exit;
        }

        // Hash the new password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Update the password in the database and remove the reset token
        $sql = "UPDATE Account SET user_pass = :password, password_reset_token = NULL, password_reset_expires = NULL WHERE userID = :userID";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':password' => $hashed_password,
            ':userID' => $user['userID']
        ]);

        // Show success message
        echo "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Password Reset Successful</title>
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        </head>
        <body>
            <script>
                Swal.fire({
                    icon: 'success',
                    title: 'Password Reset Successful',
                    text: 'Your password has been updated. You can now log in with your new password.',
                    confirmButtonText: 'OK'
                }).then(() => {
                    window.location.href='../html/login.html'; // Redirect after closing alert
                });
            </script>
        </body>
        </html>
        ";
        exit;
    } else {
        // Invalid or expired token
        echo "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Invalid Token</title>
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        </head>
        <body>
            <script>
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid or Expired Token',
                    text: 'The password reset link is invalid or has expired.',
                    confirmButtonText: 'OK'
                }).then(() => {
                    window.location.href='../html/forgotpassword.html'; // Redirect after closing alert
                });
            </script>
        </body>
        </html>
        ";
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - AN'E Laundry</title>
    <link rel="stylesheet" href="../css/resetpassword.css"> <!-- Ensure this CSS file exists and is correctly linked -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Include SweetAlert2 library -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <?php if ($user): ?>
    <div class="container">
        <div class="resetpassword-logo-container">
            <div class="resetpassword-form">
                <h2>Reset Password</h2>
                <p>Enter your new password below.</p>
                <form action="resetpassword.php" method="POST">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    <label for="password">New Password*</label>
                    <input type="password" id="password" name="password" placeholder="Enter new password" required>

                    <label for="confirm_password">Confirm New Password*</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>

                    <button type="submit" class="btn">Reset Password</button>
                </form> 
            </div>

            <div class="logo-section">
                <img src="../img/ane-laundry-logo.png" alt="AN'E Laundry Logo" class="logo">
            </div>
        </div>
    </div>
    <div class="wave-container">
        <div class="wave wave1"></div>
        <div class="wave wave2"></div>
    </div>
    <?php endif; ?>
</body>
</html>
