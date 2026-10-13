<?php
declare(strict_types=1);

/**
 * Probes the ZKTeco device to find which comm_key it actually accepts.
 * Run from CLI:  php livemachine/probe_commkey.php
 * Or from browser: http://localhost/HRMCRM3.0/livemachine/probe_commkey.php
 */

require_once __DIR__ . '/classes/ZKDevice.php';
require_once __DIR__ . '/config/device.php';

header('Content-Type: text/plain; charset=utf-8');

$cfg = DeviceConfig::load();
echo "Device: {$cfg->ip}:{$cfg->port}  (stored comm_key = {$cfg->password})\n";
echo str_repeat('-', 60) . "\n\n";

// Candidate keys to try
$candidates = [0, 1, 1234, 12345, 123456, 8888, 9999, 1111, 4321, $cfg->password];
$candidates = array_values(array_unique($candidates));

// Step 1 — raw CMD_CONNECT to see what device responds
echo "Step 1: opening raw TCP and sending CMD_CONNECT...\n";
$probe = new ZKDevice($cfg->ip, $cfg->port, 0, 5, __DIR__ . '/logs/probe.log');

$reflect = new ReflectionClass($probe);
$streamProp = $reflect->getProperty('stream');
$streamProp->setAccessible(true);

$errno = 0; $errstr = '';
$stream = @stream_socket_client("tcp://{$cfg->ip}:{$cfg->port}", $errno, $errstr, 5.0);
if (!$stream) {
    echo "  TCP connect FAILED: [$errno] $errstr\n";
    echo "  → device is unreachable. Check IP / power / network.\n";
    exit;
}
echo "  TCP connect OK\n";
fclose($stream);

// Step 2 — for each candidate, full connect+auth and report
echo "\nStep 2: trying candidate comm_keys...\n";
$winner = null;
foreach ($candidates as $key) {
    $dev = new ZKDevice($cfg->ip, $cfg->port, $key, 5, __DIR__ . '/logs/probe.log');
    $ok  = $dev->connect();
    echo sprintf("  comm_key=%-8d  →  %s\n", $key, $ok ? 'ACCEPTED ✓' : 'rejected');
    $dev->disconnect();
    if ($ok) { $winner = $key; break; }
}

echo "\n" . str_repeat('-', 60) . "\n";
if ($winner !== null) {
    echo "FOUND: device accepts comm_key = $winner\n";
    if ($winner !== $cfg->password) {
        echo "→ Open Settings in the app and set comm_key to: $winner\n";
        echo "  (currently stored: {$cfg->password})\n";
    } else {
        echo "→ This matches the stored value. The issue must be elsewhere (network flakiness, session race, etc.)\n";
    }
} else {
    echo "NO MATCH among tested candidates.\n";
    echo "→ The device has a custom comm_key. Options:\n";
    echo "   1. Check on device:  Menu → Comm. → COMM Key  (and re-enter it there to be sure)\n";
    echo "   2. Power-cycle the device after re-entering the key\n";
    echo "   3. Factory reset (last resort): Menu → System → Reset\n";
}
