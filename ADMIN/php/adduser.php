<?php
// Start output buffering and enable error reporting for debugging (disable in production)
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include SweetAlert2 library
echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";

// Include the database connection file
require 'dbconnection.php';  // Ensure this path is correct

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    echo "<div style='color: white;'>Received POST request.</div>";
    // Retrieve and sanitize form data
    $name = trim($_POST['name']);
    $email = trim($_POST['email']); // Capture email
    $usertype = trim($_POST['usertype']); // Capture user type
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm-password'];

    // Validate that all required fields are filled
    if (empty($name) || empty($email) || empty($usertype) || empty($username) || empty($password) || empty($confirm_password)) {
        echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Incomplete Form',
                    text: 'Please fill in all required fields.',
                    confirmButtonText: 'OK'
                }).then(() => {
                    window.history.back();
                });
              </script>";
        exit();
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Email',
                    text: 'Please enter a valid email address.',
                    confirmButtonText: 'OK'
                }).then(() => {
                    window.history.back();
                });
              </script>";
        exit();
    }

    // Validate that passwords match
    if ($password !== $confirm_password) {
        echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Password Mismatch',
                    text: 'Passwords do not match. Please try again.',
                    confirmButtonText: 'OK'
                }).then(() => {
                    window.history.back();
                });
              </script>";
        exit();
    }

    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    try {
        // Connect to the database
        $pdo = connectDB();

        // Begin a transaction
        $pdo->beginTransaction();

        // Check if the email already exists
        $emailCheckSql = "SELECT COUNT(*) FROM Account WHERE email = :email";
        $emailCheckStmt = $pdo->prepare($emailCheckSql);
        $emailCheckStmt->execute([':email' => $email]);
        $emailExists = $emailCheckStmt->fetchColumn();

        if ($emailExists > 0) {
            // Email already exists, rollback and show error
            $pdo->rollBack();
            echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Email Already Exists',
                        text: 'The email address you entered is already in use.',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        window.history.back();
                    });
                  </script>";
            exit();
        }

        // Check if the username already exists
        $usernameCheckSql = "SELECT COUNT(*) FROM Account WHERE user_name = :username";
        $usernameCheckStmt = $pdo->prepare($usernameCheckSql);
        $usernameCheckStmt->execute([':username' => $username]);
        $usernameExists = $usernameCheckStmt->fetchColumn();

        if ($usernameExists > 0) {
            // Username already exists, rollback and show error
            $pdo->rollBack();
            echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Username Already Exists',
                        text: 'The username you entered is already taken.',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        window.history.back();
                    });
                  </script>";
            exit();
        }

        // Insert the new user into the database
        $insertSql = "INSERT INTO Account (user_name, user_pass, name, user_type, email) 
                      VALUES (:username, :password, :name, :usertype, :email)";
        $insertStmt = $pdo->prepare($insertSql);
        $insertStmt->execute([
            ':username' => $username,
            ':password' => $hashed_password,
            ':name' => $name,
            ':usertype' => $usertype,
            ':email' => $email
        ]);

        // Commit the transaction
        $pdo->commit();

        // Only show SweetAlert if the insert was successful
        if ($insertStmt->rowCount() > 0) {
            echo "<script>
                    Swal.fire({
                      title: 'Account Created Successfully',
                      text: 'You will be redirected shortly.',
                      icon: 'success',
                      timer: 2000,
                      showConfirmButton: false
                    }).then(() => {
                      window.location.href = '../php/manageuser.php'; // Adjusted path to your manageuser.php
                    });
                  </script>";
        } else {
            // Insert failed for some reason
            echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Registration Failed',
                        text: 'There was an issue creating the account. Please try again.',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        window.history.back();
                    });
                  </script>";
        }
    } catch (PDOException $e) {
        // Rollback the transaction in case of error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        // Log the error message to a file (optional)
        // error_log($e->getMessage());

        // Show a generic error message to the user
        echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'An Error Occurred',
                    text: 'There was an error processing your request. Please try again later.',
                    confirmButtonText: 'OK'
                }).then(() => {
                    window.history.back();
                });
              </script>";
        exit();
    }
}

ob_end_flush();
?>
