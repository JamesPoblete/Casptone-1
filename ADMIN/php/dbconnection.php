<?php
function connectDB() {
    $host = 'localhost';
    $dbname = 'dbcapstone';
    $username = 'root'; // Your database username
    $password = ''; // Your database password, often empty for WAMP

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        echo 'Connection failed: ' . $e->getMessage();
        exit();
    }
}
?>