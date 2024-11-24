<?php
// adduser.php

header('Content-Type: application/json');

// Include the database connection file
require 'dbconnection.php';  // Ensure this path is correct

// Initialize response variables
$response = [
    'status' => 'error',
    'title' => '',
    'text' => '',
    'redirect' => ''
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve and sanitize form data
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $usertype = trim($_POST['usertype']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm-password'];

    // Validate that all required fields are filled
    if (empty($name) || empty($email) || empty($usertype) || empty($username) || empty($password) || empty($confirm_password)) {
        $response['title'] = 'Incomplete Form';
        $response['text'] = 'Please fill in all required fields.';
    }
    // Validate email format
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['title'] = 'Invalid Email';
        $response['text'] = 'Please enter a valid email address.';
    }
    // Validate that passwords match
    elseif ($password !== $confirm_password) {
        $response['title'] = 'Password Mismatch';
        $response['text'] = 'Passwords do not match. Please try again.';
    }
    else {
        // **Password Strength Validation**
        // Define the password requirements
        $passwordRequirements = [
            'minimum_length' => 8,
            'uppercase' => '/[A-Z]/',
            'lowercase' => '/[a-z]/',
            'digit' => '/\d/',
            'special_character' => '/[\W_]/' // \W matches any non-word character
        ];

        $passwordErrors = [];

        // Check minimum length
        if (strlen($password) < $passwordRequirements['minimum_length']) {
            $passwordErrors[] = "at least " . $passwordRequirements['minimum_length'] . " characters long";
        }

        // Check for uppercase letter
        if (!preg_match($passwordRequirements['uppercase'], $password)) {
            $passwordErrors[] = "at least one uppercase letter (A-Z)";
        }

        // Check for lowercase letter
        if (!preg_match($passwordRequirements['lowercase'], $password)) {
            $passwordErrors[] = "at least one lowercase letter (a-z)";
        }

        // Check for digit
        if (!preg_match($passwordRequirements['digit'], $password)) {
            $passwordErrors[] = "at least one number (0-9)";
        }

        // Check for special character
        if (!preg_match($passwordRequirements['special_character'], $password)) {
            $passwordErrors[] = "at least one special character (e.g., !@#$%^&*)";
        }

        // If there are password errors, set error response
        if (!empty($passwordErrors)) {
            $errorMessage = "Your password must contain " . implode(", ", $passwordErrors) . ".";
            $response['title'] = 'Weak Password';
            $response['text'] = $errorMessage;
        }
        else {
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
                    // Email already exists, rollback and set error response
                    $pdo->rollBack();
                    $response['title'] = 'Email Already Exists';
                    $response['text'] = 'The email address you entered is already in use.';
                }
                else {
                    // Check if the username already exists
                    $usernameCheckSql = "SELECT COUNT(*) FROM Account WHERE user_name = :username";
                    $usernameCheckStmt = $pdo->prepare($usernameCheckSql);
                    $usernameCheckStmt->execute([':username' => $username]);
                    $usernameExists = $usernameCheckStmt->fetchColumn();

                    if ($usernameExists > 0) {
                        // Username already exists, rollback and set error response
                        $pdo->rollBack();
                        $response['title'] = 'Username Already Exists';
                        $response['text'] = 'The username you entered is already taken.';
                    }
                    else {
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

                        // Check if insert was successful
                        if ($insertStmt->rowCount() > 0) {
                            $response['status'] = 'success';
                            $response['title'] = 'Account Created Successfully';
                            $response['text'] = 'You will be redirected shortly.';
                            // **Corrected Redirect Path**
                            $response['redirect'] = '/capstone-1/ADMIN/php/manageuser.php'; // Absolute path
                        }
                        else {
                            $response['title'] = 'Registration Failed';
                            $response['text'] = 'There was an issue creating the account. Please try again.';
                        }
                    }
                }
            } catch (PDOException $e) {
                // Rollback the transaction in case of error
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                // Log the error message to a file (optional)
                // error_log($e->getMessage());

                // Set a generic error response
                $response['title'] = 'An Error Occurred';
                $response['text'] = 'There was an error processing your request. Please try again later.';
            }
        }

    }

}

echo json_encode($response);
?>
