/*
session_start();
include 'config.php';

// Security Guard Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['client_name'];
    $email = $_POST['client_email'];
    $user_id = $_SESSION['user_id']; // The link to the logged-in user

    try {
        // Store it in the memory
        $sql = "INSERT INTO clients (user_id, client_name, client_email) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $name, $email]);

        // SUCCESS: Jump back to the dashboard and tell it we succeeded
        header("Location: index.php?success=1");
        exit();

    } catch (PDOException $e) {
        die("Database Error: " . $e->getMessage());
    }
}
?>

<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['client_name'];
    $email = $_POST['client_email'];
    $user_id = $_SESSION['user_id']; 

    try {
        $sql = "INSERT INTO clients (user_id, client_name, client_email) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $name, $email]);

        // Return immediately to view the results seamlessly
        header("Location: index.php?success=1");
        exit();

    } catch (PDOException $e) {
        die("Database Error: " . $e->getMessage());
    }
}
?>