<?php
declare(strict_types=1);
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../config/device.php';
require_once __DIR__ . '/../config/database.php';

Auth::requireLogin();
$pageTitle = 'Settings';
$cfg = DeviceConfig::load();
$settings = [];
foreach (Database::pdo()->query('SELECT `key`,`value` FROM settings')->fetchAll() as $r) {
    $settings[$r['key']] = $r['value'];
}
require __DIR__ . '/../partials/head.php';
?>
<div class="app">
    <?php require __DIR__ . '/../partials/sidebar.php'; ?>
    <main class="main">
        <?php require __DIR__ . '/../partials/topbar.php'; ?>
        <div class="content">
            <form id="settings-form">
                <section class="panel" data-tour="set-device">
                    <header><h3>Device</h3></header>
                    <div class="body">
                        <div class="row">
                            <div class="field"><label>Name</label><input name="device_name" value="<?= htmlspecialchars($cfg->name) ?>"></div>
                            <div class="field"><label>Brand</label><input name="device_brand" value="<?= htmlspecialchars($cfg->brand) ?>"></div>
                        </div>
                        <div class="row">
                            <div class="field"><label>IP address</label><input name="device_ip" value="<?= htmlspecialchars($cfg->ip) ?>"></div>
                            <div class="field"><label>Port</label><input type="number" name="device_port" value="<?= (int)$cfg->port ?>"></div>
                            <div class="field"><label>comm_key</label><input type="number" name="device_password" value="<?= (int)$cfg->password ?>"></div>
                        </div>
                        <div class="flex">
                            <button type="button" id="btn-test-connection" class="btn"><i data-lucide="plug"></i> Test connection</button>
                            <button type="button" id="btn-sync-time"       class="btn"><i data-lucide="clock"></i> Sync clock</button>
                            <button type="button" id="btn-restart-device"  class="btn"><i data-lucide="power"></i> Restart</button>
                            <!-- Wipes the punch buffer stored ON THE DEVICE only; local attendance_logs rows are untouched. Irreversible. -->
                            <button type="button" id="btn-clear-logs"      class="btn danger hidden"><i data-lucide="trash-2"></i> Clear device logs</button>
                        </div>
                    </div>
                </section>

                <section class="panel mt-3" data-tour="set-timing">
                    <header><h3>Office timing</h3></header>
                    <div class="body">
                        <div class="row">
                            <div class="field"><label>In time</label><input type="time" name="office_in_time"  value="<?= htmlspecialchars($settings['office_in_time']  ?? '09:00') ?>"></div>
                            <div class="field"><label>Out time</label><input type="time" name="office_out_time" value="<?= htmlspecialchars($settings['office_out_time'] ?? '18:00') ?>"></div>
                            <div class="field"><label>Late threshold</label><input type="time" name="late_threshold" value="<?= htmlspecialchars($settings['late_threshold'] ?? '09:15') ?>"></div>
                            <div class="field">
                                <label>Full day &ge; (worked hours)</label>
                                <input type="number" min="0" step="0.5" name="full_day_hours" value="<?= htmlspecialchars($settings['full_day_hours'] ?? '6') ?>">
                            </div>
                            <div class="field"><label>Auto-sync (sec)</label><input type="number" name="auto_sync_seconds" value="<?= htmlspecialchars($settings['auto_sync_seconds'] ?? '30') ?>"></div>
                        </div>
                    </div>
                </section>

                <section class="panel mt-3" data-tour="set-payroll">
                    <header><h3>Payroll</h3></header>
                    <div class="body">
                        <div class="row">
                            <div class="field">
                                <label>Currency symbol</label>
                                <input type="text" name="currency_symbol" maxlength="8"
                                       value="<?= htmlspecialchars($settings['currency_symbol'] ?? 'Rs') ?>">
                            </div>
                            <div class="field">
                                <label>Compulsory break (minutes)</label>
                                <input type="number" min="0" max="480" name="break_minutes"
                                       value="<?= htmlspecialchars($settings['break_minutes'] ?? '45') ?>">
                            </div>
                            <div class="field">
                                <label>Meal break threshold (minutes)</label>
                                <input type="number" min="1" max="480" name="meal_break_minutes"
                                       value="<?= htmlspecialchars($settings['meal_break_minutes'] ?? '30') ?>">
                            </div>
                        </div>
                    </div>
                </section>

                <section class="panel mt-3" data-tour="set-leave">
                    <header><h3>Paid leave</h3></header>
                    <div class="body">
                        <!-- Overtime is Sunday-only and paid at the plain hourly rate (see Payroll::computeDay) —
                             no threshold or multiplier setting needed for it. -->
                        <div class="row">
                            <div class="field">
                                <label>Paid-leave hours / day</label>
                                <input type="number" min="0" step="0.5" name="paid_leave_hours"
                                       value="<?= htmlspecialchars($settings['paid_leave_hours'] ?? '8') ?>">
                            </div>
                        </div>
                    </div>
                </section>

                <div class="flex" style="justify-content:flex-end;margin-top:18px">
                    <button class="btn primary" type="submit"><i data-lucide="save"></i> Save settings</button>
                </div>
            </form>
        </div>
    </main>
</div>
<script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= @filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
<script src="<?= BASE_URL ?>/assets/js/settings.js"></script>
</body></html>
