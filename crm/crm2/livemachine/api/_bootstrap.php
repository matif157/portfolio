<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/device.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Employee.php';
require_once __DIR__ . '/../classes/Attendance.php';
require_once __DIR__ . '/../classes/ZKDevice.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

Auth::requireApi();

function api_ok(array $payload = []): never
{
    echo json_encode(['ok' => true] + $payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function api_fail(string $error, int $code = 400, array $extra = []): never
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $error] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
}

function api_input(): array
{
    if (($_SERVER['CONTENT_TYPE'] ?? '') === 'application/json') {
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
    return $_POST;
}

function open_device(): ZKDevice
{
    $cfg = DeviceConfig::load();
    $dev = new ZKDevice(
        $cfg->ip, $cfg->port, $cfg->password,
        8,
        __DIR__ . '/../logs/device.log'
    );
    if (!$dev->connect()) {
        api_fail('device_unreachable', 503, ['ip' => $cfg->ip, 'port' => $cfg->port]);
    }
    return $dev;
}
