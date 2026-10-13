<?php
include 'includes/db.php';
include 'includes/auth.php';

header('Content-Type: application/json');


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['document_id'])) {
    $document_id = mysqli_real_escape_string($con, $_POST['document_id']);
    $user_id = $_SESSION['user_id'];
    $user_type = $_SESSION['user_type'];

    // Get document info with user verification
    $query = "SELECT d.* FROM project_documents d
              JOIN projects p ON d.project_id = p.order_id
              WHERE d.document_id = '$document_id'
              AND (d.user_id = '$user_id' OR p.assign_to = '$user_id' OR '$user_type' = 1)";
    $result = mysqli_query($con, $query);
    $document = mysqli_fetch_assoc($result);

    if (!$document) {
        echo json_encode(['success' => false, 'error' => 'Document not found or no permission']);
        exit;
    }

    // Delete file from server
    if (file_exists($document['file_path'])) {
        if (!unlink($document['file_path'])) {
            echo json_encode(['success' => false, 'error' => 'Failed to delete file from server']);
            exit;
        }
    }

    // Delete from database
    $deleteQuery = "DELETE FROM project_documents WHERE document_id = '$document_id'";
    if (mysqli_query($con, $deleteQuery)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . mysqli_error($con)]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
}