<?php
require_once 'log_in.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$client_ref = trim($_GET['id'] ?? '');

if ($client_ref !== '') {
    // Soft Delete. Support both the internal row id and the client_id value.
    if (ctype_digit($client_ref)) {
        $sql = "UPDATE clients SET deleted_at = NOW() WHERE id = ?";
        $params = [(int)$client_ref];
    } else {
        $sql = "UPDATE clients SET deleted_at = NOW() WHERE client_id = ?";
        $params = [$client_ref];
    }

    $stmt = $conn->prepare($sql);
    
    if ($stmt->execute($params)) {
        echo "<script>
            alert('Client Soft Deleted Successfully!');
            window.location='clients.php';
        </script>";
    } else {
        echo "Delete failed!";
    }
} else {
    echo "Invalid Client ID";
}
?>