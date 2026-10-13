<?php
/**
 * GRAVITY CRM — Theme Preference AJAX Endpoint
 * POST: theme, mode, csrf_token
 * Saves per-user theme preference and returns JSON response.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/theme_helper.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
    exit;
}

verify_csrf();

$theme = trim($_POST['theme'] ?? '');
$mode  = trim($_POST['mode']  ?? '');

$ok = save_user_theme($theme, $mode);

echo json_encode(['success' => $ok]);
