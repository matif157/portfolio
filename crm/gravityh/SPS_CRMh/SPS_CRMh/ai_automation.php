<?php
/**
 * SPS Professional Services CRM
 * AI Automation Decisions & Confidence Trail Log (Spec Section 23)
 */
require_once __DIR__ . '/auth.php';
require_permission('clients.view');

$interactions = $pdo->query("
    SELECT ai.*, u.full_name
    FROM ai_interactions ai
    LEFT JOIN users u ON ai.user_id = u.id
    ORDER BY ai.created_at DESC
")->fetchAll();

$threshold = floatval(get_setting('ai_confidence_threshold', '0.85'));

render_header('AI Audit & Decision Log', 'ai');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">AI Decision Audit Trail</h1>
        <p class="page-subtitle">Logs of all AI interactions, decision recommendations, confidence scores, and reasoning trails</p>
    </div>
    <div>
        <a href="ai_assistant.php" class="btn btn-primary">🤖 AI Assistant</a>
    </div>
</div>

<div class="card" style="margin-bottom:20px;">
    <div style="font-size:13px; color:var(--text-muted);">
        Configured Minimum Confidence Threshold: <strong><?= ($threshold * 100) ?>%</strong>. Decisions below this threshold automatically create human review tasks.
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Prompt / Context</th>
                    <th>Confidence</th>
                    <th>Reasoning Trail</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($interactions)): ?>
                    <tr><td colspan="6" style="text-align:center; color:var(--text-muted);">No AI interaction logs recorded yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($interactions as $ai): ?>
                        <tr>
                            <td><span style="font-size:12px; color:var(--text-muted);"><?= format_datetime($ai['created_at']) ?></span></td>
                            <td><strong><?= e($ai['full_name'] ?: 'System') ?></strong></td>
                            <td><span class="badge badge-info"><?= e($ai['action']) ?></span></td>
                            <td><?= e($ai['prompt']) ?></td>
                            <td>
                                <?php $conf = floatval($ai['confidence']); ?>
                                <span class="badge badge-<?= $conf >= $threshold ? 'success' : 'danger' ?>">
                                    <?= ($conf * 100) ?>%
                                </span>
                            </td>
                            <td style="font-size:12px; color:var(--text-muted);"><?= e($ai['reason']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php render_footer(); ?>
