<?php
/**
 * SPS Professional Services CRM
 * Unified Omnichannel Client Timeline View
 */
require_once __DIR__ . '/auth.php';
require_permission('clients.view');

$client_id = intval($_GET['client_id'] ?? 1);

$stmtC = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmtC->execute([$client_id]);
$client = $stmtC->fetch();

if (!$client) {
    die("Client not found.");
}

render_header("Unified Timeline - {$client['company_name']}", 'clients');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Unified Client Omnichannel Timeline</h1>
        <p class="page-subtitle">Real-time vertical feed aggregating Twilio calls, SMS, Emails, AI triggers, and project milestones for <?= e($client['company_name'] ?: "{$client['first_name']} {$client['last_name']}") ?></p>
    </div>
    <div>
        <a href="client_profile.php?id=<?= $client_id ?>" class="btn btn-secondary">← Client Profile</a>
    </div>
</div>

<div class="card" style="max-width:900px; margin:0 auto;">
    <div class="card-title">
        <span>Timeline Activity Feed (Client #<?= e($client['client_number']) ?>)</span>
        <button onclick="timelineApp.refreshFeed()" class="btn btn-sm btn-primary">🔄 Live Refresh</button>
    </div>

    <!-- Vertical Feed Container rendered by client-timeline.js -->
    <div id="timelineContainer" data-client-id="<?= $client_id ?>">
        <div style="text-align:center; padding:30px; color:var(--text-muted);">Loading real-time feed...</div>
    </div>
</div>

<!-- Load ES6 Module JS -->
<script type="module" src="client-timeline.js"></script>

<?php render_footer(); ?>
