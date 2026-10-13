<?php
// SPS 2040 Regression - Security & Stage-2 audit (Phase 3/5 invariants)

t_suite('security', 'Login lockout, scoped permissions, security events', function () {
    $uid = 0;
    try {
        // Account lockout at threshold
        db_execute("INSERT INTO users (role_id, username, email, password_hash, first_name, last_name, status)
                    VALUES (4, '_reg_sec', '_reg_sec@example.test', ?, 'T', 'C', 'active')", [password_hash('x', PASSWORD_BCRYPT)]);
        $uid = db_last_id();

        $u = db_row('SELECT * FROM users WHERE id = ?', [$uid]);
        t_assert('new account not locked', !is_user_locked($u));

        for ($i = 1; $i <= 4; $i++) { record_failed_login($u['id']); }
        $u = db_row('SELECT * FROM users WHERE id = ?', [$uid]);
        t_eq('counter 4 before lock', 4, (int)$u['failed_login_attempts']);

        record_failed_login($u['id']);
        $u = db_row('SELECT * FROM users WHERE id = ?', [$uid]);
        t_assert('locked at threshold', is_user_locked($u));

        t_assert('admin unlock works', unlock_user_account($u['id']));
        $u = db_row('SELECT * FROM users WHERE id = ?', [$uid]);
        t_assert('unlock clears counter+lock', !is_user_locked($u) && (int)$u['failed_login_attempts'] === 0);

        // Permission scoping
        t_assert('scoped view denied without session', user_can_scoped('clients', 'view') === false);

        // Stage-2 security event with severity persisted
        $aid = log_security_event('reg_test_event', 'harness', null, 'critical');
        $row = db_row('SELECT * FROM activities WHERE id = ?', [$aid]);
        t_assert('security event sensitive+severity',
            $row && (int)$row['is_sensitive'] === 1 && $row['severity'] === 'critical');
        db_execute('DELETE FROM activities WHERE id = ?', [$aid]);
    } finally {
        if ($uid) { db_execute('DELETE FROM notifications WHERE module=? AND record_id=?', ['users', $uid]); }
        if ($uid) { db_execute('DELETE FROM users WHERE id = ?', [$uid]); }
    }
});