<?php
session_start();
include 'config.php';

if (isset($_GET['id']) && isset($_SESSION['user_id'])) {
    $lead_id = $_GET['id'];
    $user_id = $_SESSION['user_id'];

    try {
        // We ensure the user_id matches so someone can't edit someone else's leads!
        $sql = "UPDATE leads SET status = 'Contacted' WHERE id = ? AND user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$lead_id, $user_id]);

        header("Location: index.php");
    } catch (PDOException $e) {
        die("Error updating lead: " . $e->getMessage());
    }
}
?>