<?php
// Phase 7 regression - AI orchestrator governance + ledger

t_suite('ai', 'AI orchestration, permission-gated tools, usage ledger', function () {
    $t0 = date('Y-m-d H:i:s', time() - 2);
    try {
        // Ledger + token accounting via transport hook
        $GLOBALS['AI_TRANSPORT'] = fn($t, $o) => ['text' => 'ok', 'usage' => ['prompt_tokens' => 60, 'completion_tokens' => 40]];
        $r = ai_call('regr_ai', ['tier' => 'fast', 'user' => 'REGR', 'user_id' => 1]);
        t_assert('ai_call ok', $r['ok']);
        t_eq('token sum 100', 100, $r['tokens']);
        t_assert('cost positive', $r['cost'] > 0);
        t_assert('usage row written',
            (int)db_value("SELECT COUNT(*) FROM ai_usage WHERE task_type='regr_ai' AND total_tokens=100") === 1);

        // Permission gate
        t_assert('permitted tool runs as admin', ai_tool_execute('requirements.missing', [], ['id' => 1, 'role_id' => 1])['ok'] === true);
        $deny = ai_tool_execute('requirements.missing', [], []);
        t_assert('tool denied without user', !empty($deny['perm_ok'] === false));

        // Assistant: plain answer path
        $GLOBALS['AI_TRANSPORT'] = fn($t, $o) => ['text' => '{"answer":"hi"}'];
        $ans = ai_assistant('REGR', ['id' => 1, 'role_id' => 1]);
        t_assert('assistant answer path', $ans['ok'] && $ans['text'] === 'hi' && $ans['executed'] === false);

        // Unknown tool refusal
        $GLOBALS['AI_TRANSPORT'] = fn($t, $o) => ['text' => '{"tool":"nope","args":[],"explain":"x","confidence":0.99}'];
        $bad = ai_assistant('REGR', ['id' => 1, 'role_id' => 1]);
        t_assert('unknown tool refused', $bad['ok'] === false);
    } finally {
        unset($GLOBALS['AI_TRANSPORT']);
        db_execute('DELETE FROM ai_decisions WHERE created_at >= ?', [$t0]);
        db_execute('DELETE FROM ai_usage WHERE created_at >= ?', [$t0]);
        db_execute('DELETE FROM ai_interactions WHERE created_at >= ?', [$t0]);
    }
});