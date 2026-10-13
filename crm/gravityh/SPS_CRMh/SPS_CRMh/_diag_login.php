<?php
/**
 * Diagnostic — checks whether login can succeed against the live DB.
 * Not part of the app — delete after diagnosis.
 */
require_once __DIR__ . '/db.php';
@session_start();

$username = 'admin';
$password = 'ChangeMeImmediately!123';

// Replicate login.php logic
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
$stmt->execute([$username, $username]);
$user = $stmt->fetch();

echo "DB connection: OK\n";
echo "Found user: " . ($user ? $user['username'] : 'NO') . "\n";
echo "role_id: " . ($user['role_id'] ?? 'N/A') . "\n";
echo "status: " . ($user['status'] ?? 'N/A') . "\n";
echo "Stored password: " . ($user['password'] ?? 'N/A') . "\n";
echo "Stored length: " . strlen($user['password'] ?? '') . "\n";
echo "Input length : " . strlen($password) . "\n";

if ($user) {
    if ($user['status'] !== 'active') {
        echo "RESULT: account deactivated\n";
    } elseif ($password === $user['password']) {
        echo "RESULT: LOGIN OK (plain-text match)\n";
    } else {
        echo "RESULT: password mismatch\n";
    }
    // detect a hash signature
    $h = substr((string)$user['password'], 0, 4);
    echo "Hash-signature check (starts with \$h1/\$2y/etc?): " . $h . "\n";
}

// Check for any hash-like passwords anywhere
$row = $pdo->query("SELECT username, LENGTH(password) AS L, password FROM users ORDER BY id")->fetchAll();
foreach ($row as $r) {
    echo "  user " . $r['username'] . " len=" . $r['L'] . " val=" . $r['password'] . "\n";
}
