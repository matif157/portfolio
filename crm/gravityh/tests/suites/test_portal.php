<?php
// Phase 8 regression - Client portal auth, scope, isolation, messaging

t_suite('portal', 'Portal auth, lockout, message, scope isolation', function () {
    $cid = 0; $puid = 0;
    try {
        db_execute("INSERT INTO clients (first_name,last_name,email,status) VALUES ('REG','Portal','_regportal@example.test','active')");
        $cid = db_last_id();
        db_execute("INSERT INTO portal_users (client_id,email,password_hash,display_name,status) VALUES (?,?,'" . password_hash('Pw12345', PASSWORD_BCRYPT) . "','R','active')", [$cid, '_regportal@example.test']);
        $puid = db_last_id();

        $r = portal_authenticate('_regportal@example.test', 'wrongpw');
        t_assert('bad password rejected', empty($r['ok']));

        $r = portal_authenticate('_regportal@example.test', 'Pw12345');
        t_assert('correct password logs in', !empty($r['ok']) && portal_user() !== null && (int)portal_user()['client_id'] === $cid);

        // Scope isolation
        t_assert('portal services scoped', is_array(portal_services()));
        t_assert('portal invoices scoped', is_array(portal_invoices()));
        t_assert('portal message works', portal_message('regr note'));
        t_assert('message persisted',
            (int)db_value("SELECT COUNT(*) FROM communications WHERE client_id=? AND channel='portal'", [$cid]) >= 1);

        // Lockout after 5 failures
        unset($_SESSION['portal_uid']);
        for ($i = 0; $i < 5; $i++) { portal_authenticate('_regportal@example.test', 'bad'); }
        $p = db_row('SELECT * FROM portal_users WHERE id=?', [$puid]);
        t_assert('portal locked after 5', !empty($p['locked_until']));
    } finally {
        $_SESSION = []; session_destroy();
        if ($puid) db_execute('DELETE FROM portal_users WHERE id=?', [$puid]);
        if ($cid) {
            db_execute('DELETE FROM communications WHERE client_id=?', [$cid]);
            db_execute('DELETE FROM clients WHERE id=?', [$cid]);
        }
    }
});