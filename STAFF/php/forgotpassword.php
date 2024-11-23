<?php
// forgotpassword.php
session_start();
require 'dbconnection.php'; // Ensure this path is correct
require 'vendor/autoload.php'; // Include Composer's autoloader for PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Set the timezone to ensure consistency
date_default_timezone_set('Asia/Manila'); // Replace with your actual timezone

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

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the email from the form
    $email = trim($_POST['email']);

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Invalid email format
        echo "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Invalid Email</title>
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        </head>
        <body>
            <script>
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Email',
                    text: 'Please enter a valid email address.',
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

    // Connect to the database
    $pdo = connectDB();

    // Check if the email exists in the Account table
    $sql = "SELECT userID, user_name FROM Account WHERE email = :email";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Generate a unique token
        $token = bin2hex(random_bytes(50));
        $hashedToken = hash('sha256', $token); // Hash the token for secure storage

        // Set token expiration (e.g., 24 hours from now)
        $expires = date("Y-m-d H:i:s", time() + 86400); // Current time + 24 hours

        // Store the hashed token and its expiration in the database
        $sql = "UPDATE Account SET password_reset_token = :hashedToken, password_reset_expires = :expires WHERE email = :email";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':hashedToken' => $hashedToken,
            ':expires' => $expires,
            ':email' => $email
        ]);

        // Prepare the reset link with the plain token
        $resetLink = "localhost/capstone-1/STAFF/php/resetpassword.php?token=" . urlencode($token);
        // **Replace 'yourdomain.com' with your actual domain**

        // Send the reset email using PHPMailer
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com'; // Set the SMTP server to send through
            $mail->SMTPAuth   = true;
            $mail->Username   = 'laundryanes@gmail.com'; // Your Gmail address
            $mail->Password   = 'khujpkwnlxrsbwky';    // Your App Password (without spaces)
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Enable SSL encryption
            $mail->Port       = 465; // TCP port to connect to

            // Recipients
            $mail->setFrom('laundryanes@gmail.com', 'AN\'E Laundry'); // Sender's email and name
            $mail->addAddress($email, $user['user_name']); // Add a recipient

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Request';
            $mail->Body    = "
                <h2>Password Reset Request</h2>
                <p>Hi " . htmlspecialchars($user['user_name']) . ",</p>
                <p>You requested a password reset. Click the link below to reset your password:</p>
                <a href='" . $resetLink . "'>Reset Password</a>
                <p>This link will expire in 24 hours.</p>
                <p>If you did not request this, please ignore this email.</p>
                <p>Regards,<br>AN'E Laundry Team</p>
            ";

            $mail->send();

            // Show success message using SweetAlert
            echo "
            <!DOCTYPE html>
            <html lang='en'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>Reset Link Sent</title>
                <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
            </head>
            <body>
                <script>
                    Swal.fire({
                        icon: 'success',
                        title: 'Reset Link Sent',
                        text: 'A password reset link has been sent to your email.',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        window.location.href='../html/login.html'; // Redirect after closing alert
                    });
                </script>
            </body>
            </html>
            ";
            exit;
        } catch (Exception $e) {
            // Log the error for debugging (do not display sensitive info to users)
            error_log("PHPMailer Error: " . $mail->ErrorInfo);

            // Show generic error message to the user
            echo "
            <!DOCTYPE html>
            <html lang='en'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>Email Error</title>
                <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
            </head>
            <body>
                <script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Email Error',
                        text: 'There was an error sending the reset email. Please try again later.',
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
    } else {
        // For security, do not reveal that the email doesn't exist
        echo "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Reset Link Sent</title>
            <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
        </head>
        <body>
            <script>
                Swal.fire({
                    icon: 'success',
                    title: 'Reset Link Sent',
                    text: 'If an account with that email exists, a password reset link has been sent.',
                    confirmButtonText: 'OK'
                }).then(() => {
                    window.location.href='../html/login.html'; // Redirect after closing alert
                });
            </script>
        </body>
        </html>
        ";
        exit;
    }
}
?>
