<?php
// S3/S4 regression - Event bus durability, consumers, retries

t_suite('events', 'EventBus durability, consumers, retry, retention', function () {
    $ids = [];
    try {
        $hit = [];
        bus_register_consumer('REGR_TEST', function ($p) use (&$hit) { $hit[] = $p['k']; });

        $e = bus_emit('REGR_TEST', ['k' => 'v'], ['source' => 'regr', 'source_id' => 1]);
        $ids[] = $e;
        $row = db_row('SELECT * FROM events WHERE id = ?', [$e]);
        t_assert('event processed', $row && $row['status'] === 'processed');
        t_assert('consumer received', $hit === ['v']);

        // Failing consumer -> failed + retry sweep increments attempts
        bus_register_consumer('REGR_FAIL', function () { throw new RuntimeException('boom'); });
        $f = bus_emit('REGR_FAIL', [], ['regr']);
        $ids[] = $f;
        $r1 = db_row('SELECT * FROM events WHERE id = ?', [$f]);
        t_assert('failing consumer marked failed', $r1 && $r1['status'] === 'failed' && strpos((string)$r1['last_error'], 'boom') !== false);
        $a1 = (int)$r1['attempts'];
        bus_dispatch_pending(10);
        $r2 = db_row('SELECT * FROM events WHERE id = ?', [$f]);
        t_assert('retry re-attempts', $r2 && (int)$r2['attempts'] === $a1 + 1);
    } finally {
        if ($ids) {
            db_execute('DELETE FROM events WHERE id IN (' . implode(',', $ids) . ')');
            db_execute('DELETE FROM events WHERE source = ? OR event_name LIKE ?', ['regr', 'REGR%']);
        }
        // drop the test consumers only if they were registered
    }
});