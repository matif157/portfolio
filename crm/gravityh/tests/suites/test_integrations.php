<?php
// Phase 6 regression - Integration adapters honesty + outbound queue

t_suite('integrations', 'Integration honesty + outbound queue idempotency/abandonment', function () {
    $qid = 0;
    try {
        // Honesty: unconfigured provider never claims connected
        db_execute("INSERT INTO settings (setting_key,setting_value,setting_group) VALUES ('smtp_host','','integration') ON DUPLICATE KEY UPDATE setting_value=''");
        $r = int_test('smtp');
        t_assert('unconfigured smtp not connected', !$r['ok'] && $r['status'] !== 'connected');
        db_execute("DELETE FROM integration_connections WHERE provider_key='smtp'");

        // Queue idempotency
        $a = int_enqueue_outbound('teams', 'notify', ['title' => 't'], '_reg_idem');
        $q = $a['id'];
        $b = int_enqueue_outbound('teams', 'notify', ['title' => 't2'], '_reg_idem');
        t_assert('duplicate idempotency key ignored', $a['queued'] === true && $b['queued'] === false && $a['id'] === $b['id']);

        // Abandonment escalates to exception
        $GLOBALS['INT_DELIVERERS']['teams'] = function () { throw new RuntimeException('outage'); };
        db_execute('UPDATE outbound_queue SET max_attempts = 2 WHERE id = ?', [$q]);
        jobs_process_outbound(10);
        jobs_process_outbound(10);
        $row = db_row('SELECT * FROM outbound_queue WHERE id=?', [$q]);
        t_eq('abandoned after max attempts', 'abandoned', $row['status']);
        t_assert('integration_failure exception opened',
            (int)db_value("SELECT COUNT(*) FROM exceptions WHERE category='integration_failure' AND source='outbound_queue' AND source_id=?", [$q]) >= 1);
    } finally {
        if ($q) db_execute('DELETE FROM outbound_queue WHERE id=?', [$q]);
        db_execute("DELETE FROM exceptions WHERE source='outbound_queue'");
        db_execute("DELETE FROM events WHERE source='outbound_queue'");
        db_execute("DELETE FROM settings WHERE setting_key='smtp_host' AND setting_group='integration'");
        db_execute("DELETE FROM integration_connections WHERE provider_key='smtp'");
        unset($GLOBALS['INT_DELIVERERS']['teams']);
    }
});