/*
include 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = $_POST['username'];
    $email = $_POST['email'];
    $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);

    try {
        $sql = "INSERT INTO users (username,email, password) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user,$email,$pass]);

        echo "Account created! <a href='login.php'>Click here to login</a>";
    } catch (PDOException $e) {
        // Check if the error code is 23000 (Duplicate Entry)
        if ($e->getCode() == 23000) {
            echo "Error: That username or email is already taken. Please <a href='register.php'>try another one</a>.";
        } else {
            echo "An unexpected error occurred: " . $e->getMessage();
        }
    }
}
?>*/

<?php
include 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = $_POST['username'];
    $email = $_POST['email'];
    $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);

    try {
        $sql = "INSERT INTO users (username, email, password) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user, $email, $pass]);

        // Success: Send them straight to the login screen
        header("Location: login.php?message=registered");
        exit();

    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            // Duplicate item caught: Send back to form with error flag
            header("Location: register.php?error=taken");
            exit();
        } else {
            die("Critical Error: " . $e->getMessage());
        }
    }
}
?>