<?php
/**
 * SPS Professional Services CRM
 * Multi-Channel Communications Journal
 */
require_once __DIR__ . '/auth.php';
require_permission('clients.view');

$channel_filter = trim($_GET['channel'] ?? '');

$query = "
    SELECT comm.*, c.first_name, c.last_name, c.company_name
    FROM communications comm
    JOIN clients c ON comm.client_id = c.id
    WHERE 1=1
";
$params = [];
if ($channel_filter !== '') {
    $query .= " AND comm.channel = ?";
    $params[] = $channel_filter;
}
$query .= " ORDER BY comm.sent_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$communications = $stmt->fetchAll();

render_header('Communication Journal', 'communications');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Multi-Channel Communication Journal</h1>
        <p class="page-subtitle">Outbound & inbound communications (Email, SMS, WhatsApp, AI Voice)</p>
    </div>
    <div>
        <a href="email.php" class="btn btn-primary">+ Send Client Email</a>
    </div>
</div>

<div class="card">
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <a href="communications.php" class="btn btn-sm <?= empty($channel_filter) ? 'btn-primary' : 'btn-secondary' ?>">All Channels</a>
        <a href="communications.php?channel=email" class="btn btn-sm <?= $channel_filter === 'email' ? 'btn-primary' : 'btn-secondary' ?>">Email</a>
        <a href="communications.php?channel=sms" class="btn btn-sm <?= $channel_filter === 'sms' ? 'btn-primary' : 'btn-secondary' ?>">SMS</a>
        <a href="communications.php?channel=whatsapp" class="btn btn-sm <?= $channel_filter === 'whatsapp' ? 'btn-primary' : 'btn-secondary' ?>">WhatsApp</a>
        <a href="communications.php?channel=voice" class="btn btn-sm <?= $channel_filter === 'voice' ? 'btn-primary' : 'btn-secondary' ?>">AI Voice Call</a>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Client Entity</th>
                    <th>Channel</th>
                    <th>Subject / Title</th>
                    <th>Message Summary</th>
                    <th>Direction</th>
                    <th>Status</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($communications)): ?>
                    <tr><td colspan="7" style="text-align:center; color:var(--text-muted);">No communication logs recorded.</td></tr>
                <?php else: ?>
                    <?php foreach ($communications as $comm): ?>
                        <tr>
                            <td><strong><?= e($comm['company_name'] ?: "{$comm['first_name']} {$comm['last_name']}") ?></strong></td>
                            <td><span class="badge badge-info"><?= strtoupper($comm['channel']) ?></span></td>
                            <td><strong><?= e($comm['subject'] ?: 'Direct Communication') ?></strong></td>
                            <td style="max-width:300px; font-size:12px;"><?= e(substr($comm['message_body'], 0, 100)) ?>...</td>
                            <td><span class="badge badge-secondary"><?= ucfirst($comm['direction']) ?></span></td>
                            <td><span class="badge badge-success"><?= ucfirst($comm['status']) ?></span></td>
                            <td><?= format_datetime($comm['sent_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php render_footer(); ?>
