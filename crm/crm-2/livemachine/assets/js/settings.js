/* Settings page actions */
(() => {
    'use strict';

    document.getElementById('settings-form')?.addEventListener('submit', function (e) {
        e.preventDefault();
        const btn = this.querySelector('[type=submit]');
        withLoading(btn, async () => {
            const data = Object.fromEntries(new FormData(this));
            try {
                await api(BASE+'/api/save_settings.php', { method: 'POST', body: data });
                toast('Settings saved', 'success');
            } catch (err) { toast('Save failed: ' + err.message, 'error'); }
        });
    });

    document.getElementById('btn-test-connection')?.addEventListener('click', function () {
        withLoading(this, async () => {
            const f = document.getElementById('settings-form');
            try {
                const r = await api(BASE+'/api/test_connection.php', { method: 'POST', body: {
                    ip:       f.device_ip.value,
                    port:     f.device_port.value,
                    password: f.device_password.value,
                }});
                if (r.connect) toast(`Connected. Device clock: ${r.time}`, 'success');
                else if (r.ping) toast('Reachable but auth failed (check comm_key)', 'warn');
                else toast('Device unreachable', 'error');
            } catch (e) { toast('Test failed: ' + e.message, 'error'); }
        });
    });

    document.getElementById('btn-sync-time')?.addEventListener('click', function () {
        withLoading(this, async () => {
            try {
                const r = await api(BASE+'/api/sync_device_time.php', { method: 'POST', body: {} });
                toast(`Device clock synced → ${r.device_time}`, 'success');
            } catch (e) { toast('Failed: ' + e.message, 'error'); }
        });
    });

    document.getElementById('btn-restart-device')?.addEventListener('click', async function () {
        const ok = await confirmModal({
            title: 'Restart device',
            message: 'The device will reboot and be unreachable for ~30 seconds. Continue?',
            confirmText: 'Restart',
            danger: true,
        });
        if (!ok) return;
        withLoading(this, async () => {
            try { await api(BASE+'/api/restart_device.php', { method: 'POST', body: {} });
                  toast('Restart command sent', 'success'); }
            catch (e) { toast('Failed: ' + e.message, 'error'); }
        });
    });

    // Erases the device's own attendance buffer via clear_device_logs.php; already-synced
    // rows in the local attendance_logs table are not deleted.
    document.getElementById('btn-clear-logs')?.addEventListener('click', async function () {
        const ok = await confirmModal({
            title: 'Clear ALL attendance logs on device?',
            message: 'This permanently erases every punch record stored on the device. Local DB rows are kept. This cannot be undone.',
            confirmText: 'Clear device logs',
            danger: true,
        });
        if (!ok) return;
        withLoading(this, async () => {
            try { await api(BASE+'/api/clear_device_logs.php', { method: 'POST', body: {} });
                  toast('Device logs cleared', 'success'); }
            catch (e) { toast('Failed: ' + e.message, 'error'); }
        });
    });
})();
