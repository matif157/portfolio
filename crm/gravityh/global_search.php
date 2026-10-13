<?php
/**
 * GRAVITY CRM - Global Search Endpoint
 * Phase 1: Foundation
 * 
 * Yahan global search AJAX requests handle hote hain.
 * Results module-wise return hote hain.
 * Results user permissions ke according filter hote hain.
 */

require_once __DIR__ . '/functions.php';
require_login();

header('Content-Type: application/json');

$query = trim(get('q', ''));
$module = get('module', '');

if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

$results = perform_global_search($query, $module);
echo json_encode($results);