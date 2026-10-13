/*
session_start();
include 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = $_POST['username'];
    $pass = $_POST['password'];

    // Search for the user in the database
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$user]);
    $userData = $stmt->fetch();

    // Check if user exists and password is correct
    if ($userData && password_verify($pass, $userData['password'])) {
        // Give the user their "Session ID Card"
        $_SESSION['user_id'] = $userData['id'];
        $_SESSION['username'] = $userData['username'];

        header("Location: index.php");
        exit();
    } else {
        echo "Invalid username or password. <a href='login.php'>Try again</a>";
    }
}
?>*/
<?php
session_start();
include 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = $_POST['username'];
    $pass = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$user]);
    $userData = $stmt->fetch();

    if ($userData && password_verify($pass, $userData['password'])) {
        // Issue session identity access
        $_SESSION['user_id'] = $userData['id'];
        $_SESSION['username'] = $userData['username'];

        header("Location: index.php");
        exit();
    } else {
        echo "Invalid username or password. <a href='login.php'>Try again</a>";
    }
}
?>