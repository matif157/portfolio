<?php
/**
 * Syed Professional Services (SPS) CRM
 * Interactive 360° Visual B2B & Client Relationship Tree
 */
require_once __DIR__ . '/auth.php';
require_permission('clients.view');

$client_id = intval($_GET['client_id'] ?? 1);

// Fetch Client Entity
$stmtC = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmtC->execute([$client_id]);
$client = $stmtC->fetch();

if (!$client) {
    set_flash('danger', 'Client record not found.');
    redirect('clients.php');
}

// Fetch Linked Sub-Entities for Tree JSON
$businesses = $pdo->prepare("SELECT * FROM businesses WHERE client_id = ?");
$businesses->execute([$client_id]);
$business_list = $businesses->fetchAll();

$contacts = $pdo->prepare("SELECT * FROM contacts WHERE client_id = ?");
$contacts->execute([$client_id]);
$contact_list = $contacts->fetchAll();

$services = $pdo->prepare("
    SELECT cs.*, s.title as service_title, s.price 
    FROM client_services cs 
    JOIN services s ON cs.service_id = s.id 
    WHERE cs.client_id = ?
");
$services->execute([$client_id]);
$service_list = $services->fetchAll();

$projects = $pdo->prepare("SELECT * FROM projects WHERE client_id = ?");
$projects->execute([$client_id]);
$project_list = $projects->fetchAll();

$quotations = $pdo->prepare("SELECT * FROM quotations WHERE client_id = ?");
$quotations->execute([$client_id]);
$quotation_list = $quotations->fetchAll();

$invoices = $pdo->prepare("SELECT * FROM invoices WHERE client_id = ?");
$invoices->execute([$client_id]);
$invoice_list = $invoices->fetchAll();

$bank_accounts = $pdo->prepare("
    SELECT ba.*, b.business_name 
    FROM bank_accounts ba 
    JOIN businesses b ON ba.business_id = b.id 
    WHERE b.client_id = ?
");
$bank_accounts->execute([$client_id]);
$account_list = $bank_accounts->fetchAll();

$all_clients = $pdo->query("SELECT id, first_name, last_name, company_name FROM clients ORDER BY company_name, first_name")->fetchAll();

render_header("Relationship Tree: " . ($client['company_name'] ?: "{$client['first_name']} {$client['last_name']}"), 'clients');
?>

<div class="page-header">
    <div>
        <h1 class="page-title">🌳 360° Visual Business & Client Relationship Tree</h1>
        <p class="page-subtitle">Interactive hierarchical map connecting B2B entities, contacts, services, projects, estimates, and invoices</p>
    </div>
    <div>
        <form action="relationship_tree.php" method="GET" style="display:inline-flex; gap:8px;">
            <select name="client_id" class="form-control" onchange="this.form.submit()">
                <?php foreach ($all_clients as $cl): ?>
                    <option value="<?= $cl['id'] ?>" <?= $client_id == $cl['id'] ? 'selected' : '' ?>>
                        <?= e($cl['company_name'] ?: "{$cl['first_name']} {$cl['last_name']}") ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <a href="client_profile.php?id=<?= $client_id ?>" class="btn btn-secondary">← Client Profile</a>
    </div>
</div>

<!-- Relationship Tree View Container -->
<div class="card" style="border-top:4px solid var(--accent); position:relative; overflow:hidden; padding:25px;">
    <div class="card-title" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <span>Interactive Relationship Mindmap (Click any node to view entity details)</span>
        <div style="display:flex; gap:10px;">
            <span class="badge badge-success">🌟 Strong Relation</span>
            <span class="badge badge-info">⚡ Active Deliverable</span>
            <span class="badge badge-warning">📄 Estimate</span>
        </div>
    </div>

    <!-- Tree Render Area -->
    <div id="treeContainer" style="overflow-x:auto; padding:20px 0;">
        <!-- Javascript renders interactive tree here -->
    </div>
</div>

<!-- Interactive Entity Node Detail Modal -->
<div id="nodeDetailModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:2000;">
    <div class="card" style="max-width:500px; margin:100px auto;">
        <div class="card-title" style="display:flex; justify-content:space-between;">
            <span id="modalNodeTitle">Node Details</span>
            <button onclick="document.getElementById('nodeDetailModal').style.display='none'" style="background:none; border:none; font-size:18px; cursor:pointer;">&times;</button>
        </div>
        <div id="modalNodeBody" style="font-size:13px; margin:15px 0;"></div>
        <div style="text-align:right;">
            <a id="modalNodeLink" href="#" class="btn btn-primary btn-sm">Manage Entity →</a>
        </div>
    </div>
</div>

<!-- Embed Tree Data JSON -->
<script>
window.treeData = {
    client: <?= json_encode($client) ?>,
    businesses: <?= json_encode($business_list) ?>,
    contacts: <?= json_encode($contact_list) ?>,
    services: <?= json_encode($service_list) ?>,
    projects: <?= json_encode($project_list) ?>,
    quotations: <?= json_encode($quotation_list) ?>,
    invoices: <?= json_encode($invoice_list) ?>,
    bank_accounts: <?= json_encode($account_list) ?>
};
</script>

<script type="module" src="tree.js"></script>

<?php render_footer(); ?>
