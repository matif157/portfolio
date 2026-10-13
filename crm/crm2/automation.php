<?php
/* =============================================================================
   SPS CRM AUTOMATION — ADMIN CONTROL PANEL
   -----------------------------------------------------------------------------
   Build, test, enable/disable and inspect Zapier-style workflows.
   Admin only (user_type == 1).
   ---------------------------------------------------------------------------*/
include "includes/db.php";
include "includes/auth.php";

if ($usertype != '1') { header("Location: index.php"); exit; }

require_once __DIR__ . '/includes/automation.php';
require_once __DIR__ . '/includes/automation_templates.php';
aut_ensure_tables($con);

$msg = ''; $msgType = '';

/* ── Save (create/update) ──────────────────────────────────────────────── */
if (isset($_POST['save_wf'])) {
    $id   = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $status = (int)!empty($_POST['status']);
    $tt   = in_array($_POST['trg_type'] ?? '', ['schedule','record','webhook','manual']) ? $_POST['trg_type'] : 'manual';

    $trg = [];
    $trg['cron']        = trim($_POST['trg']['cron'] ?? '');
    $trg['query']       = trim($_POST['trg']['query'] ?? '');
    $trg['object']      = trim($_POST['trg']['object'] ?? '');
    $trg['event']       = in_array($_POST['trg']['event'] ?? '', ['created','updated']) ? $_POST['trg']['event'] : 'created';
    $trg['probe_limit'] = max(10, (int)($_POST['trg']['probe_limit'] ?? 200));
    $trg['fields']      = array_values(array_filter(array_map('trim', explode(',', $_POST['trg']['fields'] ?? ''))));
    $trg['token']       = trim($_POST['trg']['token'] ?? '');
    if ($tt === 'record' && $trg['watermark'] < 0) $trg['watermark'] = (int)($_POST['trg']['watermark'] ?? 0);

    $steps = json_decode((string)($_POST['steps_json'] ?? '[]'), true);
    if (!is_array($steps)) { $msg = 'Steps could not be parsed — fix the JSON.'; $msgType = 'danger'; }
    else {
        // sanitize step structure (strip transient keys)
        $steps = aut_sanitize_steps($steps);
        $nameE = mysqli_real_escape_string($con, $name);
        $trgE  = mysqli_real_escape_string($con, aut_json($trg));
        $stpE  = mysqli_real_escape_string($con, aut_json($steps));
        if ($id > 0) {
            mysqli_query($con, "UPDATE sps_automations SET name='$nameE',status=$status,trigger_type='$tt',trigger_config='$trgE',steps='$stpE' WHERE id=$id");
            $msg = 'Workflow updated.'; $msgType = 'success';
        } else {
            mysqli_query($con, "INSERT INTO sps_automations (name,status,trigger_type,trigger_config,steps) VALUES ('$nameE',$status,'$tt','$trgE','$stpE')");
            $id = mysqli_insert_id($con);
            $msg = 'Workflow created.'; $msgType = 'success';
        }
        if ($id > 0) $_GET['edit'] = $id;
    }
}

/* ── Toggle status ─────────────────────────────────────────────────────── */
if (isset($_POST['toggle']) && isset($_POST['st'])) {
    mysqli_query($con, "UPDATE sps_automations SET status=" . (int)!((int)$_POST['st']) . " WHERE id=" . (int)$_POST['toggle']);
    $msg = 'Status updated.'; $msgType = 'success';
}

/* ── Delete ────────────────────────────────────────────────────────────── */
if (isset($_POST['del'])) {
    mysqli_query($con, "DELETE FROM sps_automations WHERE id=" . (int)$_POST['del']);
    mysqli_query($con, "DELETE FROM sps_automation_logs WHERE automation_id=" . (int)$_POST['del']);
    mysqli_query($con, "DELETE FROM sps_automation_queue WHERE automation_id=" . (int)$_POST['del']);
    mysqli_query($con, "DELETE FROM sps_automation_probe WHERE wf_id=" . (int)$_POST['del']);
    $msg = 'Workflow deleted.'; $msgType = 'success';
}

/* ── Duplicate ─────────────────────────────────────────────────────────── */
if (isset($_POST['dup'])) {
    $w = aut_q($con, "SELECT * FROM sps_automations WHERE id=" . (int)$_POST['dup'] . " LIMIT 1")[0] ?? null;
    if ($w) {
        mysqli_query($con, "INSERT INTO sps_automations (name,status,trigger_type,trigger_config,steps) VALUES ('" .
            mysqli_real_escape_string($con, $w['name'] . ' (copy)') . "',0,'" . $w['trigger_type'] . "','" .
            mysqli_real_escape_string($con, $w['trigger_config']) . "','" . mysqli_real_escape_string($con, $w['steps']) . "')");
        $msg = 'Workflow duplicated.'; $msgType = 'success';
    }
}

/* ── Test / Run now ────────────────────────────────────────────────────── */
if (isset($_POST['test_wf'])) {
    $wfId = (int)$_POST['test_wf'];
    $object_type = trim($_POST['test_object_type'] ?? '');
    $object_id   = (int)($_POST['test_object_id'] ?? 0);
    $testData = json_decode((string)($_POST['test_data'] ?? '{}'), true);
    if (!is_array($testData)) $testData = [];
    header('Content-Type: application/json');
    echo json_encode(aut_dispatch($con, $wfId, $_POST['test_mode'] === 'run' ? 'manual' : 'test', [
        'object_type' => $object_type, 'object_id' => $object_id, 'data' => $testData
    ], ['user_id' => (int)$_SESSION['user_id'], 'user_name' => $_SESSION['fullname'] ?? '']));
    exit;
}

/* ── Use template (one-click draft) ────────────────────────────────────── */
if (isset($_POST['use_tpl']) && isset($_POST['tpl_id'])) {
    require_once __DIR__ . '/includes/automation_templates.php';
    $tplId = trim($_POST['tpl_id']);
    foreach (aut_templates() as $tpl) {
        if (($tpl['id'] ?? '') !== $tplId) continue;
        $tcfg = (array)($tpl['trigger']['config'] ?? []);
        $tt = in_array($tpl['trigger']['type'] ?? '', ['schedule', 'record', 'webhook', 'manual'], true) ? $tpl['trigger']['type'] : 'manual';
        if ($tt === 'webhook' && empty($tcfg['token'])) $tcfg['token'] = bin2hex(random_bytes(8));
        $n  = mysqli_real_escape_string($con, $tpl['name'] . ' (draft)');
        $tc = mysqli_real_escape_string($con, aut_json($tcfg));
        $ts = mysqli_real_escape_string($con, aut_json($tpl['steps'] ?? []));
        mysqli_query($con, "INSERT INTO sps_automations (name,status,trigger_type,trigger_config,steps) VALUES ('$n',0,'$tt','$tc','$ts')");
        $msg = 'Template added as a draft — review it, then press Enable.'; $msgType = 'success';
        $_GET['edit'] = mysqli_insert_id($con);
        $_GET['p'] = 'edit';
        break;
    }
}

/* ── views ─────────────────────────────────────────────────────────────── */
$view = $_GET['p'] ?? 'list';
$editId = (int)($_GET['edit'] ?? $_GET['id'] ?? 0);
$logId  = (int)($_GET['logs'] ?? 0);

$wf = null;
if ($editId > 0) $wf = aut_q($con, "SELECT * FROM sps_automations WHERE id=$editId LIMIT 1")[0] ?? null;

$alerts = [
    'members' => 'client', 'billing_invoice' => 'invoice', 'billing_invoice_instant' => 'instant_invoice',
    'billing_payment_history' => 'payment', 'orders' => 'order', 'orders_completed' => 'completed_order',
    'bank_statements' => 'statement', 'client_banks' => 'bank', 'quotations' => 'quotation',
    'email_history' => 'email', 'memberships' => 'membership', 'users' => 'user', 'member_services' => 'service',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Automation — SPS CRM</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="assets/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/truly-ui.min.css">
<link rel="stylesheet" href="assets/css/plugins/TrulyIcons/TrulyIcons.css">
<script src="assets/js/jquery.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<style>
.aut-card{border:1px solid #e7e8f5;border-radius:12px;margin-bottom:20px;background:#fff}
.aut-card .card-h{background:#f7f8ff;border-bottom:1px solid #e7e8f5;padding:14px 20px;border-radius:12px 12px 0 0;font-weight:600}
.aut-card .card-b{padding:20px}
.aut-wrap{max-width:1200px;margin:24px auto;padding:0 16px}
.aut-nav{margin-bottom:18px}
.aut-nav a{color:#4c5cc5;font-weight:600;text-decoration:none;margin-right:18px}
.aut-nav a:hover{color:#2b2566}
table.aut-tbl th{font-size:12px;text-transform:uppercase;color:#6c6f8e;letter-spacing:.4px;white-space:nowrap}
.step-item{border:1px solid #dfe2f8;border-radius:10px;margin-bottom:10px;background:#fff}
.step-head{display:flex;align-items:center;gap:8px;padding:10px 14px;background:#fbfbff;border-radius:10px 10px 0 0;border-bottom:1px solid #eef0fa}
.step-body{padding:14px}
.step-body.nested{background:#fafbff;border-top:1px solid #eef0fa}
.step-body label{font-size:12px;font-weight:600;color:#4b4e6d;margin-top:6px}
.step-body .form-control,.step-body .form-select{font-size:13px}
.badge-trigger{font-size:11px;padding:4px 10px;border-radius:20px}
.cond-rows .cond-row{display:flex;gap:8px;align-items:center;margin-bottom:8px;flex-wrap:wrap}
.cond-rows .cond-row select,.cond-rows .cond-row input{font-size:13px}
.placeholder-hint{font-size:12px;color:#6c6f8e;background:#f6f7ff;border:1px dashed #cfd3ee;border-radius:8px;padding:10px;margin:8px 0}
.field-row{display:flex;gap:6px;align-items:center;margin-bottom:6px}
.field-row input{font-size:13px}
.test-out{font-family:Consolas,monospace;font-size:12px;background:#0d1117;color:#7ee787;border-radius:8px;padding:12px;white-space:pre-wrap;max-height:280px;overflow:auto;display:none}
.badge-on{background:#e6f7ec;color:#16703c}
.badge-off{background:#fdecec;color:#b02a2a}
</style>
</head>
<body class="sidebar-collapsed">

<?php include "includes/sidebar.php"; ?>

<div class="dash-main">
<?php include "includes/header.php"; ?>
<div class="dash-content">
<div class="page-header">
    <div class="page-header-title"><h4 class="m-b-10">Workflow Automation</h4></div>
    <nav class="breadcrumb">
        <ol class="breadcrumb"><li class="breadcrumb-item">System</li><li class="breadcrumb-item active">Automation</li></ol>
    </nav>
</div>

<?php if ($msg !== '') { ?><div class="alert alert-<?php echo $msgType; ?>"><?php echo htmlspecialchars($msg); ?></div><?php } ?>

<?php if ($view === 'edit') { ?>

<?php
    $t = $wf ? ['cron'=>ucfirst(str_replace('_',' ',$wf['trigger_type']))] : [];
    $trgCfg = $wf ? aut_cfg($wf) : [];
    $stepsJson = $wf ? $wf['steps'] : '[]';
?>
<div class="aut-wrap">
<div class="aut-nav"><a href="automation.php">← Workflows</a></div>

<form method="post" id="wfForm">
<input type="hidden" name="save_wf" value="1">
<input type="hidden" name="id" value="<?php echo $editId; ?>">

<div class="aut-card">
<div class="card-h"><?php echo $editId ? 'Edit Workflow' : 'New Workflow'; ?></div>
<div class="card-b">
    <div class="row">
        <div class="col-md-7">
            <label class="form-label fw-semibold">Workflow name</label>
            <input class="form-control" name="name" required value="<?php echo htmlspecialchars($wf['name'] ?? ''); ?>" placeholder="e.g. Daily unpaid-invoice reminder">
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Status</label>
            <select class="form-select" name="status">
                <option value="1" <?php echo (!isset($wf) || $wf['status'] == 1) ? 'selected' : ''; ?>>Enabled</option>
                <option value="0" <?php echo (isset($wf) && $wf['status'] == 0) ? 'selected' : ''; ?>>Disabled</option>
            </select>
        </div>
    </div>
</div>
</div>

<div class="aut-card">
<div class="card-h">1 · Trigger</div>
<div class="card-b">
    <div class="row">
        <div class="col-md-4">
            <label class="form-label fw-semibold">Trigger type</label>
            <select class="form-select" name="trg_type" id="trgType">
                <option value="manual"  <?php echo ($wf && $wf['trigger_type']=='manual') ? 'selected':''; ?>>Manual — run from this page (button)</option>
                <option value="schedule" <?php echo ($wf && $wf['trigger_type']=='schedule') ? 'selected':''; ?>>Schedule — cron time, repeated</option>
                <option value="record"  <?php echo ($wf && $wf['trigger_type']=='record') ? 'selected':''; ?>>Record — when a record is created / changed</option>
                <option value="webhook" <?php echo ($wf && $wf['trigger_type']=='webhook') ? 'selected':''; ?>>Webhook — called by another system</option>
            </select>
        </div>
    </div>

    <div class="row mt-3 trg-sec" data-for="schedule">
        <div class="col-md-4">
            <label class="form-label">Cron expression <span class="text-muted">(min hour day month weekday)</span></label>
            <input class="form-control" name="trg[cron]" value="<?php echo htmlspecialchars($trgCfg['cron'] ?? '* * * * *'); ?>" placeholder="0 8 * * *">
            <small class="text-muted">e.g. <b>0 8 * * *</b> daily 08:00 · <b>0 9 1 * *</b> 1st of month · <b>*/15 * * * *</b> every 15 min</small>
        </div>
        <div class="col-md-8">
            <label class="form-label">Optional SQL to run once per row <span class="text-muted">(leave empty to run once)</span></label>
            <input class="form-control" name="trg[query]" value="<?php echo htmlspecialchars($trgCfg['query'] ?? ''); ?>" placeholder="SELECT member_id FROM members WHERE status=1">
        </div>
    </div>

    <div class="row mt-3 trg-sec" data-for="record">
        <div class="col-md-4">
            <label class="form-label">Record type</label>
            <select class="form-select" name="trg[object]">
                <?php foreach ($alerts as $tbl => $alias) { ?>
                <option value="<?php echo $tbl; ?>" <?php echo (($trgCfg['object'] ?? '')==$tbl) ? 'selected':''; ?>>client / <?php echo $alias; ?> (<?php echo $tbl; ?>)</option>
                <?php } ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">When</label>
            <select class="form-select" name="trg[event]">
                <option value="created" <?php echo (($trgCfg['event'] ?? 'created')=='created')?'selected':''; ?>>A new record is created</option>
                <option value="updated" <?php echo (($trgCfg['event'] ?? 'created')=='updated')?'selected':''; ?>>An existing record changes</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Probe depth <span class="text-muted">(rows scanned for changes, updates only)</span></label>
            <input type="number" class="form-control" name="trg[probe_limit]" value="<?php echo (int)($trgCfg['probe_limit'] ?? 200); ?>" min="10" max="500">
        </div>
        <div class="col-md-12 mt-2">
            <label class="form-label">Compare fields <span class="text-muted">(optional; comma separated, updates only)</span></label>
            <input class="form-control" name="trg[fields]" value="<?php echo htmlspecialchars(implode(',', $trgCfg['fields'] ?? [])); ?>" placeholder="status, balance, paid_amount">
        </div>
    </div>

    <div class="row mt-3 trg-sec" data-for="webhook">
        <div class="col-md-6">
            <label class="form-label">Webhook token</label>
            <input class="form-control" name="trg[token]" value="<?php echo htmlspecialchars($trgCfg['token'] ?? ($wf ? '' : bin2hex(random_bytes(8)))); ?>">
            <small class="text-muted">Call <code><?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] ? 'https' : 'http'); ?>://<?php echo $_SERVER['HTTP_HOST'] ?? 'CRM_HOST'; ?>/crm/hook_automation.php?token=</code><b>&lt;your token&gt;</b> with a JSON body <code>{"object_type":"members","object_id":5,"data":{...}}</code></small>
        </div>
    </div>

    <div class="row mt-3 trg-sec" data-for="manual">
        <div class="col-12 text-muted">Manual workflows run when you press <b>Test</b> or <b>Run now</b> below. You can pick an object_type / id to attach to the run context.</div>
    </div>
</div>
</div>

<div class="aut-card">
<div class="card-h">2 · Steps  <span class="text-muted">(actions run top to bottom)</span></div>
<div class="card-b">
    <input type="hidden" name="steps_json" id="stepsJson">
    <div id="stepsContainer"></div>
    <div class="mt-2">
        <select class="form-select form-select-sm d-inline-block w-auto" id="addStepType">
            <option value="">— add a step —</option>
            <option value="log">Log activity</option>
            <option value="email">Send email</option>
            <option value="sms">Send SMS (Twilio)</option>
            <option value="http">HTTP / webhook call</option>
            <option value="update_record">Update a record</option>
            <option value="insert_record">Create a record</option>
            <option value="sql">Run SQL</option>
            <option value="notify">Notify users (chat)</option>
            <option value="condition">If / else condition</option>
            <option value="loop">Loop over query rows</option>
            <option value="delay">Delay (pause)</option>
            <option value="stop">Stop workflow</option>
        </select>
        <button type="button" class="btn btn-sm btn-primary" id="btnAddStep">Add</button>
    </div>
    <div class="placeholder-hint">
        <b>Placeholders:</b> <code>{{client.name}}</code> <code>{{client.prm_email}}</code> <code>{{invoice.balance}}</code> <code>{{object.…}}</code>
        <code>{{item.…}}</code> (loop row) <code>{{now.date}}</code> <code>{{now.datetime}}</code> <code>{{meta.run_id}}</code> <code>{{trigger.user_name}}</code>
    </div>
    <div class="placeholder-hint">
        <b>Condition fields:</b> use <code>{{client.field}}</code> / <code>{{invoice.field}}</code> for the trigger record, or an exact value.
        Operators: equals, not equals, contains, not contains, greater, greater-or-equal, less, less-or-equal, in list, not in list, is empty, is not empty, regex.
    </div>
</div>
</div>

<div class="mb-3">
    <button class="btn btn-success px-4" type="submit">Save workflow</button>
    <button class="btn btn-outline-secondary" type="button" id="btnPreview">Preview JSON</button>
    <?php if ($editId) { ?>
    <input type="hidden" id="wfId" value="<?php echo $editId; ?>">
    <button class="btn btn-outline-primary" type="button" id="btnRunNow">Run now</button>
    <button class="btn btn-outline-warning" type="button" id="btnTest">Run test</button>
    <span id="testWrap" style="display:inline-block"></span>
    <?php } ?>
</div>
<div class="test-out" id="testOut"></div>
</form>
</div>

<script>
var STEPS = <?php echo $stepsJson; ?>;
var OBJECT_ALIASES = <?php echo json_encode($alerts); ?>;
var STEP_TYPES = [
 {t:'log',label:'Log activity'},{t:'email',label:'Send email'},{t:'sms',label:'Send SMS'},
 {t:'http',label:'HTTP call'},{t:'update_record',label:'Update record'},{t:'insert_record',label:'Create record'},
 {t:'sql',label:'Run SQL'},{t:'notify',label:'Notify users'},{t:'condition',label:'If/else'},
 {t:'loop',label:'Loop'},{t:'delay',label:'Delay'},{t:'stop',label:'Stop'}
];
var OPS = [
 {v:'eq',l:'is'},{v:'neq',l:'is not'},{v:'contains',l:'contains'},{v:'ncontains',l:'does not contain'},
 {v:'gt',l:'greater'},{v:'gte',l:'greater or equal'},{v:'lt',l:'less'},{v:'lte',l:'less or equal'},
 {v:'in',l:'in list'},{v:'nin',l:'not in list'},{v:'empty',l:'is empty'},{v:'nempty',l:'is not empty'},{v:'regex',l:'regex'}
];
var FIELD_IDX = 0;

function esc(s){return String(s ?? '').replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
function objectOptions(sel,friendly){
  var h=''; Object.keys(OBJECT_ALIASES).forEach(function(k){h+='<option value="'+k+'"'+(k===sel?' selected':'')+'>'+(OBJECT_ALIASES[k])+(friendly?'':' ('+k+')')+'</option>';}); return h;
}
function opOptions(sel){ var h=''; OPS.forEach(function(o){h+='<option value="'+o.v+'"'+(o.v===sel?' selected':'')+'>'+o.l+'</option>';}); return h; }
function defaultIdKey(objType){ return (OBJECT_ALIASES[objType]||'id'); }

function defaultStep(type){
  var s={type:type};
  switch(type){
    case 'log':      s.action='UPDATE'; s.object_type='members'; s.object_id='{{object.'+defaultIdKey('members')+'}}'; s.user_id='{{trigger.user_id}}'; s.description=''; break;
    case 'email':    s.to='{{client.prm_email}}'; s.subject=''; s.body=''; s.from_email=''; s.from_name=''; break;
    case 'sms':      s.to='{{client.cell_ph}}'; s.body=''; s.messaging_sid=''; break;
    case 'http':     s.url=''; s.method='POST'; s.content_type='application/json'; s.body=''; s.headers={}; break;
    case 'update_record': s.object_type='members'; s.id='{{object.'+defaultIdKey('members')+'}}'; s.fields={}; break;
    case 'insert_record': s.object_type='members'; s.fields={}; break;
    case 'sql':      s.sql=''; break;
    case 'notify':   s.recipients='admins'; s.message=''; break;
    case 'condition':s.condition=[{op:'eq',f:'{{client.field}}',v:''}]; s.then=[]; s.else=[]; break;
    case 'loop':     s.sql='SELECT * FROM members WHERE status=1 LIMIT 100'; s.steps=[]; break;
    case 'delay':    s.minutes='30'; break;
  }
  return s;
}

function condRowsId() { return 'cond_'+ (++FIELD_IDX); }

function fieldRows(s){
  var fields = s.fields && Object.keys(s.fields).length ? s.fields : {'':''};
  var h='<div class="mt-2"><label>Fields</label>';
  Object.keys(fields).forEach(function(k){
    h+='<div class="field-row"><input class="form-control w-25" placeholder="field name" value="'+esc(k)+'">'
      +'<input class="form-control flex-grow-1" placeholder="value ({{…}} allowed)" value="'+esc(fields[k])+'">'
      +'<button class="btn btn-sm btn-outline-danger" data-act="fdel">✕</button></div>';
  });
  h+='<button class="btn btn-sm btn-outline-secondary" data-act="fadd">+ add field</button></div>';
  return h;
}

function formFor(s){
  switch(s.type){
    case 'log':
      return '<div class="row"><div class="col-md-3"><label>Action</label><select class="form-control" data-k="action">'+['CREATE','UPDATE','DELETE','VIEW','EXPORT','UPLOAD','DOWNLOAD','ARCHIVE','RESTORE','EMAIL'].map(function(a){return '<option'+(s.action===a?' selected':'')+'>'+a+'</option>';}).join('')+'</select></div>'
        +'<div class="col-md-4"><label>Object type</label><select class="form-control" data-k="object_type">'+objectOptions(s.object_type||'members')+'</select></div>'
        +'<div class="col-md-2"><label>Object id</label><input class="form-control" data-k="object_id" value="'+esc(s.object_id||('{{object.'+defaultIdKey(s.object_type||'members')+'}}'))+'"></div>'
        +'<div class="col-md-3"><label>User id (0=system)</label><input class="form-control" data-k="user_id" value="'+esc(s.user_id||'{{trigger.user_id}}')+'"></div></div>'
        +'<div class="mt-2"><label>Description</label><textarea class="form-control" data-k="description" rows="2">'+esc(s.description||'')+'</textarea></div>';
    case 'email':
      return '<div class="row"><div class="col-md-4"><label>To</label><input class="form-control" data-k="to" value="'+esc(s.to||'')+'"></div>'
        +'<div class="col-md-4"><label>From email (override)</label><input class="form-control" data-k="from_email" value="'+esc(s.from_email||'')+'"></div>'
        +'<div class="col-md-4"><label>From name (override)</label><input class="form-control" data-k="from_name" value="'+esc(s.from_name||'')+'"></div></div>'
        +'<div class="mt-2"><label>Subject</label><input class="form-control" data-k="subject" value="'+esc(s.subject||'')+'"></div>'
        +'<div class="mt-2"><label>Body (HTML)</label><textarea class="form-control" data-k="body" rows="4">'+esc(s.body||'')+'</textarea></div>';
    case 'sms':
      return '<div class="row"><div class="col-md-4"><label>To</label><input class="form-control" data-k="to" value="'+esc(s.to||'')+'"></div>'
        +'<div class="col-md-4"><label>Messaging SID (optional)</label><input class="form-control" data-k="messaging_sid" value="'+esc(s.messaging_sid||'')+'"></div>'
        +'<div class="col-md-4"><label>Body</label><input class="form-control" data-k="body" value="'+esc(s.body||'')+'"></div></div>';
    case 'http':
      return '<div class="row"><div class="col-md-7"><label>URL</label><input class="form-control" data-k="url" value="'+esc(s.url||'')+'"></div>'
        +'<div class="col-md-2"><label>Method</label><select class="form-control" data-k="method">'+['GET','POST','PUT','PATCH','DELETE'].map(function(m){return '<option'+(s.method===m?' selected':'')+'>'+m+'</option>';}).join('')+'</select></div>'
        +'<div class="col-md-3"><label>Content-Type</label><input class="form-control" data-k="content_type" value="'+esc(s.content_type||'application/json')+'"></div></div>'
        +'<div class="mt-2"><label>Body</label><textarea class="form-control" data-k="body" rows="2">'+esc(s.body||'')+'</textarea></div>';
    case 'update_record':
      return '<div class="row"><div class="col-md-4"><label>Record type</label><select class="form-control" data-k="object_type">'+objectOptions(s.object_type||'members')+'</select></div>'
        +'<div class="col-md-4"><label>Record id</label><input class="form-control" data-k="id" value="'+esc(s.id||('{{object.'+defaultIdKey(s.object_type||'members')+'}}'))+'"></div></div>'
        + fieldRows(s);
    case 'insert_record':
      return '<div class="col-md-4"><label>Record type</label><select class="form-control" data-k="object_type">'+objectOptions(s.object_type||'members')+'</select></div>'
        + fieldRows(s);
    case 'sql':
      return '<label>SQL</label><textarea class="form-control" data-k="sql" rows="3">'+esc(s.sql||'')+'</textarea>';
    case 'notify':
      return '<div class="row"><div class="col-md-3"><label>Recipients (<code>admins</code>/<code>all</code>/ids)</label><input class="form-control" data-k="recipients" value="'+esc(s.recipients||'admins')+'"></div>'
        +'<div class="col-md-9"><label>Message</label><input class="form-control" data-k="message" value="'+esc(s.message||'')+'"></div></div>';
    case 'delay':
      return '<div class="col-md-3"><label>Minutes</label><input type="number" class="form-control" data-k="minutes" value="'+esc(s.minutes||'30')+'"></div>';
    case 'condition':
      return condBuilder(s);
    case 'loop':
      return '<label>SQL rows (loop {{item.…}} inside)</label><textarea class="form-control" data-k="sql" rows="2">'+esc(s.sql||'')+'</textarea>';
    case 'stop':
      return '<div class="text-muted">Stops the workflow here. Steps after this never run.</div>';
  }
  return '<div class="text-muted">Unknown step</div>';
}

function condBuilder(s){
  var rows = [];
  var group = 'and';
  if (s.condition && !('op' in s.condition)) { group = Object.keys(s.condition)[0]||'and'; rows = s.condition[group]||[]; }
  else if (s.condition && 'op' in s.condition) rows = [s.condition];
  if (!rows.length) rows = [{op:'eq',f:'',v:''}];
  var h='<div class="mb-1">Match <select class="form-control d-inline-block w-auto cond-group"><option value="and"'+(group==='and'?' selected':'')+'>ALL</option><option value="or"'+(group==='or'?' selected':'')+'>ANY</option></select> of the rules</div><div class="cond-rows">';
  rows.forEach(function(r){
    h+='<div class="cond-row"><input class="form-control w-25" placeholder="Field ({{client.field}})" value="'+esc(r.f||'')+'">'
      +'<select class="form-control w-auto cond-op">'+opOptions(r.op||'eq')+'</select>'
      +'<input class="form-control w-40 cond-val" placeholder="Value" value="'+esc(r.v??'')+'">'
      +'<button class="btn btn-sm btn-outline-danger" data-act="cdel">✕</button></div>';
  });
  h+='<button class="btn btn-sm btn-outline-secondary" data-act="cadd">+ rule</button></div>';
  return h;
}

function stepHtml(s, depth){
  var t=s.type;
  var lbl=(STEP_TYPES.find(function(x){return x.t===t;})||{label:t}).label;
  var pad='style="margin-left:'+(depth*28)+'px"';
  var h='<div class="step-item" data-type="'+t+'" '+pad+'>'
    +'<div class="step-head"><span class="badge badge-trigger bg-light text-dark">'+lbl+'</span>'
    +'<input class="form-control form-control-sm flex-grow-1" placeholder="Step label (optional)" value="'+esc(s.label||'')+'" data-k="label">'
    +'<button class="btn btn-sm btn-outline-secondary" data-act="up" title="Up">↑</button>'
    +'<button class="btn btn-sm btn-outline-secondary" data-act="dn" title="Down">↓</button>'
    +'<button class="btn btn-sm btn-outline-danger" data-act="del" title="Delete">✕</button></div>'
    +'<div class="step-body">'+formFor(s)+'</div>';
  if(t==='condition'){
    h+='<div class="step-body nested"><div class="fw-semibold text-muted mb-1">Then (condition true)</div><div class="nested-box" data-nest="then"></div>'
      +'<div class="fw-semibold text-muted mt-2 mb-1">Else (condition false)</div><div class="nested-box" data-nest="else"></div></div>';
  }
  if(t==='loop'){
    h+='<div class="step-body nested"><div class="fw-semibold text-muted mb-1">Loop body — runs per row, use {{item.…}}</div><div class="nested-box" data-nest="body"></div></div>';
  }
  return h;
}

function nestedKey(s){ return s.type==='condition' ? ['then','else'] : (s.type==='loop' ? ['body'] : []); }

function renderInto(container, arr, depth){
  depth=depth||0;
  $(container).empty();
  arr.forEach(function(s){
    var el=$(stepHtml(s,depth));
    $(container).append(el);
    nestedKey(s).forEach(function(k){
      var box=el.find('.nested-box[data-nest="'+k+'"]');
      if(box.length){
        var sub = k==='body' ? (s.steps||[]) : (s[k]||[]);
        renderInto(box, sub, depth+1);
        box.data('key',k);
      }
    });
  });
}

function collectFrom(el){
  var out=[];
  $(el).children('.step-item').each(function(){
    var el=$(this);
    var s={type:el.data('type')};
    el.find('.step-body').first().find('[data-k]').each(function(){
      var $in=$(this), k=$in.data('k');
      s[k]=k==='label'?$in.val():(k==='minutes'?$in.val():$in.val());
    });
    nestedKey(s).forEach(function(k){
      var box=el.find('.nested-box[data-nest="'+k+'"]');
      if(box.length){ var sub=collectFrom(box); if(k==='body'){ s.steps=sub; } else { s[k]=sub; } }
    });
    if(s.type==='update_record'||s.type==='insert_record'){
      var f={};
      el.find('.field-row').each(function(){
        var a=$(this).find('input').eq(0).val(), b=$(this).find('input').eq(1).val();
        if(a.trim()!=='') f[a.trim()]=b;
      });
      if(Object.keys(f).length) s.fields=f;
    }
    if(s.type==='condition'){
      var g=el.find('.cond-group').val()||'and'; var rules=[];
      el.find('.cond-row').each(function(){
        var f=$(this).find('input').eq(0).val(), op=$(this).find('.cond-op').val(), v=$(this).find('.cond-val').val();
        rules.push({f:f,op:op,v:v});
      });
      s.condition = rules.length===1 ? rules[0] : (function(){var o={};o[g]=rules;return o;})();
    }
    out.push(s);
  });
  return out;
}

function renderAll(){ renderInto('#stepsContainer', STEPS, 0); }

/* add step */
$('#btnAddStep').on('click',function(){
  var t=$('#addStepType').val(); if(!t)return;
  var idx=(arguments.callee.i=(arguments.callee.i||0)+1);
  STEPS.push(defaultStep(t));
  renderAll();
  $('#addStepType').val('');
});

/* delegated actions */
$('#stepsContainer').on('click','[data-act]',function(){
  var btn=$(this), act=btn.data('act');
  if(act==='del'){
    btn.closest('.step-item').remove();
  } else if(act==='up'||act==='dn'){
    var li=btn.closest('.step-item');
    if(act==='up' && li.prev('.step-item').length) li.prev('.step-item').before(li);
    if(act==='dn' && li.next('.step-item').length) li.next('.step-item').after(li);
  } else if(act==='fadd'){
    btn.closest('.step-body').find('.field-row').last().after('<div class="field-row"><input class="form-control w-25" placeholder="field name"><input class="form-control flex-grow-1" placeholder="value"><button class="btn btn-sm btn-outline-danger" data-act="fdel">✕</button></div>');
  } else if(act==='fdel'){
    btn.closest('.field-row').remove();
  } else if(act==='cadd'){
    btn.closest('.cond-rows').append('<div class="cond-row"><input class="form-control w-25" placeholder="Field ({{client.field}})" value=""><select class="form-control w-auto cond-op">'+opOptions('eq')+'</select><input class="form-control w-40 cond-val" placeholder="Value" value=""><button class="btn btn-sm btn-outline-danger" data-act="cdel">✕</button></div>');
  } else if(act==='cdel'){
    btn.closest('.cond-row').remove();
  }
});

/* adding nested steps inside condition/loop */
$('#stepsContainer').on('click','.nested-add',function(){
  var t=$(this).data('t');
  $(this).closest('.nested-box').append(stepHtml(defaultStep(t), 0));
});

function attachNestedAdd(){
  $('#stepsContainer .nested-box').each(function(){
    var box=$(this);
    if(box.find('.nested-add').length) return;
    $('<div class="mt-2"><select class="form-control form-control-sm d-inline-block w-auto nested-add-type">'
      + STEP_TYPES.map(function(x){return '<option value="'+x.t+'">'+x.label+'</option>';}).join('')
      + '</select> <button class="btn btn-sm btn-outline-primary nested-add">+ add nested step</button></div>')
      .appendTo(box);
  });
}

$('#stepsContainer').on('click','.nested-add',function(){
  var t=$(this).siblings('.nested-add-type').val()||'log';
  $(this).closest('.nested-box').append(stepHtml(defaultStep(t), 0));
});

/* serialize on preview / submit */
function syncJson(){ STEPS=collectFrom('#stepsContainer'); return JSON.stringify(STEPS); }
$('#btnPreview').on('click',function(){ syncJson(); $('#stepsJson').val(JSON.stringify(STEPS,null,1)); $('#testOut').show().text(JSON.stringify(STEPS,null,1)); });
$('#wfForm').on('submit',function(){ $('#stepsJson').val(syncJson()); });

/* run / test */
$('#btnRunNow, #btnTest').on('click',function(wfId,ot,oid){
  var mode=$(this).attr('id')==='btnRunNow'?'run':'test';
  var fd=new FormData();
  fd.append('test_wf', $('#wfId').val());
  fd.append('test_mode', mode);
  fd.append('test_object_type', $('#testObjectType').val());
  fd.append('test_object_id', $('#testObjectId').val());
  fd.append('test_data', JSON.stringify({}));
  $('#testOut').show().text('Running '+mode+'…');
  fetch(location.href.split('?')[0],{method:'POST',body:fd})
  .then(function(r){return r.text();})
  .then(function(t){ var d; try{ d=JSON.parse(t);}catch(e){ d={status:'n/a',msg:t}; }
    $('#testOut').text('Status: '+(d.status||'?')+' — '+(d.msg||'')
      +'\n\n'+(d.steps||[]).map(function(x){return '  #'+x.step+' ['+x.type+'] '+(x.ok?'ok':'ERR')+' → '+(x.result||'');}).join('\n'));
  })
  .catch(function(e){ $('#testOut').text('Network error: '+e.message); });
});
$('#testWrap').html(' <span class="text-muted small">Test target:</span> <select class="form-control form-control-sm d-inline-block w-auto" id="testObjectType">'+objectOptions('members')+'</select> <input type="number" class="form-control form-control-sm d-inline-block" id="testObjectId" value="5" style="width:90px">');

function trgVis(){ var t=$('#trgType').val(); $('.trg-sec').hide(); $('.trg-sec[data-for="'+t+'"]').show(); }
$('#trgType').on('change',trgVis); trgVis();

renderAll();
attachNestedAdd();
</script>

<?php } elseif ($view === 'logs') { ?>

<div class="aut-wrap">
<div class="aut-nav"><a href="automation.php">← Workflows</a></div>
<div class="aut-card"><div class="card-h">Run history<?php echo $logId ? ' — workflow #'.$logId : ''; ?></div>
<div class="card-b">
<table class="table table-sm aut-tbl">
<thead><tr><th>Run</th><th>Workflow</th><th>Type</th><th>Object</th><th>Status</th><th>Message</th><th>Started</th><th>Finished</th><th></th></tr></thead>
<tbody>
<?php
    $where = $logId ? "WHERE w.id=$logId" : '';
    $rows = aut_q($con, "SELECT l.*, w.name FROM sps_automation_logs l LEFT JOIN sps_automations w ON w.id=l.automation_id $where ORDER BY l.id DESC LIMIT 300");
    foreach ($rows as $r) {
        $st = $r['status'];
        $badge = $st==='success'?'badge-on':($st==='error'?'badge-off':'badge-trigger bg-warning');
?>
<tr style="cursor:pointer" onclick="toggleSteps(<?php echo $r['id']; ?>)">
    <td>#<?php echo $r['id']; ?></td>
    <td><?php echo htmlspecialchars($r['name'] ?? ("wf#".$r['automation_id'])); ?></td>
    <td><?php echo $r['run_type']; ?></td>
    <td><span class="text-muted small"><?php echo htmlspecialchars(($r['object_type']?:'—').' / '.($r['object_id']?:'—')); ?></span></td>
    <td><span class="badge <?php echo $badge; ?>"><?php echo $st; ?></span></td>
    <td class="small text-muted"><?php echo htmlspecialchars($r['message'] ?? ''); ?></td>
    <td><?php echo $r['started_at']; ?></td>
    <td><?php echo $r['finished_at'] ?? '…'; ?></td>
    <td></td>
</tr>
<tr id="steps-<?php echo $r['id']; ?>" style="display:none"><td colspan="9">
<pre class="test-out" style="display:block"><?php
    $sl = json_decode((string)$r['steps_log'], true);
    if (is_array($sl)) foreach ($sl as $x) echo (($x['ok']??false)?'✓':'✗')." #".($x['step']??'?')." [".htmlspecialchars($x['type']??'')."] → ".htmlspecialchars($x['result']??'')."\n";
    else echo htmlspecialchars((string)$r['steps_log']);
?></pre></td></tr>
<?php } ?>
</tbody></table>
<?php if (!$rows) echo '<p class="text-muted">No runs yet.</p>'; ?>
</div></div>
</div>
<script>function toggleSteps(id){var e=document.getElementById('steps-'+id);e.style.display=e.style.display==='none'?'':'none';}</script>

<?php } elseif ($view === 'templates') { ?>

<?php $cats = []; foreach (aut_templates() as $t) $cats[$t['cat']][] = $t; ?>
<div class="aut-wrap">
<div class="aut-nav"><a href="automation.php">← Workflows</a> <a href="automation.php?p=edit">+ New workflow</a></div>
<p class="text-muted small mb-3">Pick a ready-made workflow below. It is added as a <b>disabled draft</b> — open it, adjust any fields, then press <b>Enable</b>. Prefer a custom one? Start with <a href="automation.php?p=edit">+ New workflow</a>.</p>
<?php foreach ($cats as $cat => $list) { ?>
<div class="aut-card"><div class="card-h"><?php echo htmlspecialchars($cat); ?></div><div class="card-b">
<div class="row">
<?php foreach ($list as $t) { ?>
<div class="col-md-6 col-xl-4 mb-3">
    <div class="card h-100" style="border:1px solid #e7e8f5;border-radius:12px">
        <div class="card-body d-flex flex-column" style="padding:16px">
            <h6 class="mb-1"><?php echo htmlspecialchars($t['name']); ?></h6>
            <p class="small text-muted flex-grow-1 mb-2"><?php echo htmlspecialchars($t['desc']); ?></p>
            <form method="post">
                <input type="hidden" name="tpl_id" value="<?php echo htmlspecialchars($t['id']); ?>">
                <button type="submit" name="use_tpl" value="1" class="btn btn-sm btn-primary">Use this template</button>
            </form>
        </div>
    </div>
</div>
<?php } ?>
</div>
</div></div>
<?php } ?>
</div>

<?php } else { /* list */ ?>

<div class="aut-wrap">
<div class="aut-nav"><a href="automation.php?p=edit">+ New workflow</a> <a href="automation.php?p=templates">Templates</a> <a href="automation.php?p=logs">Run history</a>
<a href="cron_automation.php?force=1" target="_blank" title="Hit once per minute via Task Scheduler for exact timing">Scheduler endpoint</a></div>

<div class="aut-card" id="gettingStarted"><div class="card-h">Getting started — 4 easy steps</div><div class="card-b">
<ol class="small mb-0">
    <li><b>Start with a template</b> — open <a href="automation.php?p=templates">Templates</a> and pick a ready-made workflow (or press <b>+ New workflow</b> and build your own).</li>
    <li><b>Check the trigger</b> — when should it run? A schedule (timer), a record change, a webhook call, or only when you press a button.</li>
    <li><b>Add steps</b> — email, SMS, chat notify, HTTP call, SQL, loops and if/else. Use placeholders like <code>{{client.name}}</code>, <code>{{invoice.balance}}</code>, <code>{{now.date}}</code>.</li>
    <li><b>Test, then enable</b> — press <b>Run test</b> for a safe trial (green = ok), then <b>Run now</b> or flip the status to <b>Enabled</b>. Every run is recorded in <a href="automation.php?p=logs">Run history</a>.</li>
</ol>
<p class="small text-muted mt-2 mb-0">Scheduler hints: schedule workflows normally fire on page loads (up to once a minute). For exact timing, add a Windows Task Scheduler job hitting the <i>Scheduler endpoint</i> every minute.</p>
</div></div>

<div class="aut-card"><div class="card-h">Workflows</div><div class="card-b">
<table class="table aut-tbl">
<thead><tr><th>Name</th><th>Trigger</th><th>Status</th><th>Last run</th><th>Runs</th><th class="text-end">Actions</th></tr></thead>
<tbody>
<?php
    $rows = aut_q($con, "SELECT * FROM sps_automations ORDER BY id DESC");
    foreach ($rows as $r) {
        $cfg = aut_cfg($r);
        $desc = $r['trigger_type'];
        if ($r['trigger_type']==='schedule') { $q = trim($cfg['cron'] ?? ''); $desc .= " · <code>$q</code>"; }
        if ($r['trigger_type']==='record')   { $desc .= " · " . htmlspecialchars($cfg['object'] ?? '') . " " . ($cfg['event'] ?? 'created'); }
        if ($r['trigger_type']==='webhook')  { $desc .= " · token " . substr(htmlspecialchars($cfg['token'] ?? ''),0,8) . "…"; }
?>
<tr>
    <td><b><?php echo htmlspecialchars($r['name']); ?></b></td>
    <td class="small"><?php echo $desc; ?></td>
    <td><span class="badge <?php echo $r['status']==1?'badge-on':'badge-off'; ?>"><?php echo $r['status']==1?'Enabled':'Disabled'; ?></span></td>
    <td class="small text-muted"><?php echo $r['last_run_at'] ?: 'never'; ?><?php if($r['last_run_status']) echo ' <span class="badge ' . ($r['last_run_status']==='success'?'badge-on':($r['last_run_status']==='error'?'badge-off':'bg-warning')) . '">' . $r['last_run_status'] . '</span>'; ?></td>
    <td><?php echo $r['run_count']; ?></td>
    <td class="text-end" style="white-space:nowrap">
        <a class="btn btn-sm btn-outline-primary" href="automation.php?p=edit&id=<?php echo $r['id']; ?>">Edit</a>
        <a class="btn btn-sm btn-outline-secondary" href="automation.php?p=logs&logs=<?php echo $r['id']; ?>">Logs</a>
        <form method="post" class="d-inline"><?php if ($r['status']==1) { ?><input type="hidden" name="st" value="1"><input type="hidden" name="toggle" value="<?php echo $r['id']; ?>"><button class="btn btn-sm btn-outline-warning">Disable</button><?php } else { ?><input type="hidden" name="st" value="0"><input type="hidden" name="toggle" value="<?php echo $r['id']; ?>"><button class="btn btn-sm btn-outline-success">Enable</button><?php } ?></form>
        <form method="post" class="d-inline"><input type="hidden" name="dup" value="<?php echo $r['id']; ?>"><button class="btn btn-sm btn-outline-secondary">Copy</button></form>
        <form method="post" class="d-inline" onsubmit="return confirm('Delete workflow?')"><input type="hidden" name="del" value="<?php echo $r['id']; ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form>
    </td>
</tr>
<?php } ?>
</tbody></table>
<?php if (!$rows) { ?>
<p class="text-muted">No workflows yet. Start fast from a <a href="automation.php?p=templates">ready-made template</a>, or <a href="automation.php?p=edit">create your first workflow</a>.</p>
<?php } ?>
</div></div>

<div class="aut-card"><div class="card-h">Ideas you can build in minutes</div><div class="card-b">
<ul class="small mb-2">
    <li><b>Invoice paid → thank-you email</b> (record trigger: billing_payment_history created → email).</li>
    <li><b>Missing monthly statements → chase email + chat alert</b> (schedule + loop over rows with <code>{{item.…}}</code>).</li>
    <li><b>New client → notify team in chat</b> (record trigger: members created → notify).</li>
    <li><b>Multi-stage follow-up</b>: email now → <b>delay</b> → follow-up later (resumes automatically).</li>
    <li><b>Webhook from another system</b> → log + HTTP forward to a different app.</li>
</ul>
<p class="small text-muted mb-0">Browse ready-made versions of these in <a href="automation.php?p=templates">Templates</a>.</p>
</div></div>
</div>

<?php } ?>

</div>
<?php include "includes/footer.php"; ?>
</div>
</body>
</html>