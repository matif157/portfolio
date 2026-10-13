<?php
/*  CUSTOMIZER  ─  Unified field customization hub (admin page)
    -------------------------------------------------------------------------
    Lets an administrator:
      • Add NEW custom fields (text / email / integer / date / phone / textarea)
      • Edit EXISTING system fields (rename label, change type, cap length,
        set Required/Optional, hide/show)
      • Delete custom fields (system fields are hidden instead)
      • Review the audit trail (who created / updated / deleted what)
    ------------------------------------------------------------------------- */
include "includes/db.php";
include "includes/auth.php";
include "includes/customizer.php";

/* --- Admin gate --- */
if ($usertype != '1') {
    header("Location: index.php");
    exit;
}

$msg = '';
$msgType = '';

/* Determine active module (tab). All registered modules are wired — the
   engine is module-aware and each tab simply switches the active module. */
$allowedModules = array_keys((defined('CUSTOMIZER_MODULES')) ? CUSTOMIZER_MODULES : array('clients' => 1));
$requestedModule = isset($_POST['module']) ? $_POST['module'] : (isset($_GET['module']) ? $_GET['module'] : '');
$activeModule = in_array($requestedModule, $allowedModules, true) ? $requestedModule : 'clients';
$moduleName = (defined('CUSTOMIZER_MODULES') && isset(CUSTOMIZER_MODULES[$activeModule]['name']))
    ? CUSTOMIZER_MODULES[$activeModule]['name']
    : ucfirst($activeModule);

/* ---------- POST handlers ---------- */
if (isset($_POST['customizer_add_field'])) {
    $res = customizer_add_field(
        $con,
        $activeModule,
        $_POST['field_label'] ?? '',
        $_POST['field_type'] ?? 'text',
        (int) ($_POST['field_max'] ?? 0),
        isset($_POST['field_required']),
        $_POST['field_section'] ?? 'General'
    );
    $msg = $res['message'];
    $msgType = $res['success'] ? 'success' : 'danger';
}

if (isset($_POST['customizer_update_field'])) {
    $res = customizer_update_field(
        $con,
        $activeModule,
        $_POST['field_key'] ?? '',
        $_POST['field_label'] ?? '',
        $_POST['field_type'] ?? 'text',
        (int) ($_POST['field_max'] ?? 0),
        isset($_POST['field_required']),
        $_POST['field_section'] ?? 'General',
        isset($_POST['field_status'])
    );
    $msg = $res['message'];
    $msgType = $res['success'] ? 'success' : 'danger';
}

if (isset($_POST['customizer_delete_field'])) {
    $res = customizer_delete_field($con, $activeModule, $_POST['field_key'] ?? '');
    $msg = $res['message'];
    $msgType = $res['success'] ? 'success' : 'danger';
}

$fields = customizer_fields_for_module($con, $activeModule);
$cpFields = customizer_custom_fields($con, $activeModule);

/* Sections for grouping in the UI, sorted in catalog order. */
$sections = array();
foreach ($fields as $fkey => $f) {
    $sec = $f['section'] !== '' ? $f['section'] : 'General';
    $sections[$sec][$fkey] = $f;
}

/* Audit trail (latest 50). */
$audit = array();
$res = $con->query("SELECT * FROM customizer_audit_log WHERE module='" . $con->real_escape_string($activeModule) . "' ORDER BY audit_id DESC LIMIT 50");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $audit[] = $row;
    }
}

$Counts = array('total' => count($fields), 'custom' => count($cpFields));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Syed Professional Services - Customizer</title>
    <?php include "includes/links.php"; ?>
    <style>
        :root {
            --cuz-primary: #1456b8;
            --cuz-primary-dark: #0f4595;
            --cuz-primary-soft: rgba(20, 86, 184, .08);
            --cuz-border: #e8ecf1;
            --cuz-text: #334155;
        }

        .cuz-hero {
            background: linear-gradient(120deg, #1456b8 0%, #1d6fd8 60%, #3b82f6 100%);
            color: #fff;
            border-radius: 14px;
            padding: 22px 26px;
            margin-bottom: 20px;
            box-shadow: 0 8px 20px rgba(20, 86, 184, .25);
        }

        .cuz-hero h3 {
            font-weight: 700;
            margin: 0;
            font-size: 1.4rem;
        }

        .cuz-hero p {
            margin: 4px 0 0;
            opacity: .92;
            font-size: .9rem;
        }

        .cuz-hero .cuz-stats {
            display: flex;
            gap: 26px;
            margin-top: 14px;
        }

        .cuz-hero .cuz-stat {
            background: rgba(255, 255, 255, .14);
            border-radius: 10px;
            padding: 8px 14px;
            min-width: 92px;
        }

        .cuz-hero .cuz-stat b {
            display: block;
            font-size: 1.3rem;
        }

        .cuz-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }

        .cuz-tab {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 18px;
            border-radius: 10px;
            background: #fff;
            border: 1px solid var(--cuz-border);
            color: var(--cuz-text);
            font-weight: 600;
            font-size: .85rem;
            cursor: pointer;
            text-decoration: none;
        }

        .cuz-tab.active {
            background: var(--cuz-primary);
            border-color: var(--cuz-primary);
            color: #fff;
        }

        .cuz-tab.disabled {
            opacity: .5;
            cursor: not-allowed;
        }

        .cuz-section-title {
            font-size: .78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: #94a3b8;
            margin: 18px 0 8px;
        }

        .cuz-field-row {
            display: grid;
            grid-template-columns: 1fr 150px 110px 90px 70px 210px 44px;
            gap: 10px;
            align-items: center;
            padding: 10px 14px;
            border-bottom: 1px solid #f1f4f8;
            font-size: .85rem;
        }

        .cuz-field-row.head {
            background: var(--cuz-primary-soft);
            font-weight: 700;
            color: var(--cuz-primary-dark);
            border-radius: 8px;
            border: none;
            font-size: .74rem;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        .cuz-field-row .cuz-fkey {
            color: #94a3b8;
            font-size: .76rem;
        }

        .cuz-badge {
            display: inline-block;
            font-size: .68rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 20px;
        }

        .cuz-badge.system {
            background: rgba(20, 86, 184, .12);
            color: var(--cuz-primary-dark);
        }

        .cuz-badge.custom {
            background: rgba(20, 120, 90, .12);
            color: #0b7a55;
        }

        .cuz-badge.on {
            background: rgba(22, 163, 74, .12);
            color: #15803d;
        }

        .cuz-badge.off {
            background: rgba(239, 68, 68, .12);
            color: #b91c1c;
        }

        .cuz-add-card {
            background: #fff;
            border: 1px solid var(--cuz-border);
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 20px;
        }

        .cuz-add-card h5 {
            font-weight: 700;
            color: var(--cuz-text);
            margin-bottom: 14px;
            font-size: 1rem;
        }

        .cuz-btn {
            background: var(--cuz-primary);
            border: none;
            color: #fff;
            font-weight: 600;
            border-radius: 8px;
            padding: 8px 18px;
        }

        .cuz-btn:hover {
            background: var(--cuz-primary-dark);
            color: #fff;
        }

        .cuz-btn-outline {
            background: #fff;
            border: 1px solid var(--cuz-primary);
            color: var(--cuz-primary);
        }

        .cuz-btn-outline:hover {
            background: var(--cuz-primary-soft);
            color: var(--cuz-primary-dark);
        }

        .cuz-edit-form {
            display: none;
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 10px;
            padding: 14px;
            margin: 6px 0 14px;
        }

        .cuz-toggle {
            cursor: pointer;
        }

        .cuz-form-label {
            font-size: .75rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 4px;
        }

        .cuz-inline-form {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: end;
        }

        .audit-table {
            font-size: .82rem;
        }

        .audit-table .act {
            font-weight: 700;
        }

        .act-created {
            color: #15803d;
        }

        .act-updated,
        .act-field_updated {
            color: #1456b8;
        }

        .act-deleted,
        .act-field_hidden {
            color: #b91c1c;
        }
    </style>
</head>

<body class="theme-3">
    <div class="loader-bg">
        <div class="loader-track">
            <div class="loader-fill"></div>
        </div>
    </div>
    <?php include "includes/sidebar.php"; ?>
    <?php include "includes/header.php"; ?>

    <div class="dash-container">
        <div class="dash-content">
            <div class="page-header">
                <div class="page-block">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <div class="page-header-title">
                                <h4 class="m-b-10">Customizer</h4>
                            </div>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                <li class="breadcrumb-item">Customizer</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($msg !== '') { ?>
                <div class="alert alert-<?= $msgType ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($msg) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php } ?>

            <div class="cuz-hero">
                <h3><i class="fas fa-sliders-h"></i> CRM Customizer</h3>
                <p>Control every field across every module — edit existing fields, add new ones, and track every change.</p>
                <div class="cuz-stats">
                    <div class="cuz-stat"><b><?= $Counts['total'] ?></b>Total Fields</div>
                    <div class="cuz-stat"><b><?= $Counts['custom'] ?></b>Custom Fields</div>
                    <div class="cuz-stat"><b><?= count($audit) ?></b>Recent Changes</div>
                </div>
            </div>

            <div class="cuz-tabs">
                <?php
                foreach (CUSTOMIZER_MODULES as $mkey => $mcfg) {
                    $isActive = ($mkey === $activeModule);
                    $icon = $mcfg['icon'] ?? 'fas fa-sliders-h';
                    ?>
                    <a class="cuz-tab<?= $isActive ? ' active' : '' ?>" href="customizer.php?module=<?= urlencode($mkey) ?>">
                        <i class="<?= $icon ?>"></i> <?= htmlspecialchars($mcfg['name']) ?>
                    </a>
                <?php } ?>
            </div>

            <p class="text-muted" style="font-size:.85rem;margin:-8px 0 16px;">
                Managing fields for: <strong><?= htmlspecialchars($moduleName) ?></strong>
            </p>

            <!-- ===== Add New Field ===== -->
            <div class="cuz-add-card">
                <h5><i class="fas fa-plus-circle"></i> Add a New Custom Field</h5>
                <form method="POST" class="row g-2 align-items-end" id="customizerAddForm">
                    <input type="hidden" name="module" value="<?= htmlspecialchars($activeModule) ?>">
                    <div class="col-md-3">
                        <label class="cuz-form-label">Field Name</label>
                        <input type="text" name="field_label" class="form-control" placeholder="e.g. LinkedIn Profile" required>
                    </div>
                    <div class="col-md-2">
                        <label class="cuz-form-label">Type</label>
                        <select name="field_type" class="form-select">
                            <option value="text">Text</option>
                            <option value="email">Email</option>
                            <option value="integer">Integer</option>
                            <option value="date">Date</option>
                            <option value="phone">Phone</option>
                            <option value="password">Password (masked)</option>
                            <option value="textarea">Text Area</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="cuz-form-label">Max Length (0 = none)</label>
                        <input type="number" name="field_max" class="form-control" value="0" min="0">
                    </div>
                    <div class="col-md-2">
                        <label class="cuz-form-label">Section</label>
                        <input type="text" name="field_section" class="form-control" value="General" placeholder="Section name" list="cuz-section-suggest">
                        <datalist id="cuz-section-suggest">
                            <option value="Identity"></option>
                            <option value="Contact"></option>
                            <option value="Home Address"></option>
                            <option value="Business"></option>
                            <option value="Business Address"></option>
                            <option value="Banking"></option>
                            <option value="Card / Payment"></option>
                            <option value="Tax"></option>
                            <option value="Details"></option>
                            <option value="Dates"></option>
                            <option value="Status"></option>
                            <option value="Files"></option>
                            <option value="Audit"></option>
                            <option value="General"></option>
                        </datalist>
                    </div>
                    <div class="col-md-2">
                        <label class="cuz-form-label"></label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="field_required" value="1" id="addReq">
                            <label class="form-check-label" for="addReq">Required</label>
                        </div>
                    </div>
                    <div class="col-md-1">
                        <button type="submit" name="customizer_add_field" class="cuz-btn btn">Add Field</button>
                    </div>
                </form>
            </div>

            <!-- ===== Field List ===== -->
            <div class="card">
                <div class="card-header">
                    <h5><?= htmlspecialchars($moduleName) ?> Fields <span class="badge text-bg-light"><?= count($fields) ?> fields</span></h5>
                </div>
                <div class="card-body">
                    <?php foreach ($sections as $section => $secFields) { ?>
                        <div class="cuz-section-title"><?= htmlspecialchars($section) ?></div>
                        <div class="cuz-field-row head">
                            <span>Field</span>
                            <span>Type</span>
                            <span>Max Len</span>
                            <span>Required</span>
                            <span>Status</span>
                            <span>Actions</span>
                            <span></span>
                        </div>
                        <?php foreach ($secFields as $fk => $f) { ?>
                            <div class="cuz-field-row" id="row_<?= htmlspecialchars($fk) ?>">
                                <div>
                                    <div><i class="fas fa-<?= $f['is_system'] ? 'cog' : 'star' ?>"></i> <?= htmlspecialchars($f['label']) ?></div>
                                    <div class="cuz-fkey"><?= htmlspecialchars($fk) ?>
                                        <span class="cuz-badge <?= $f['is_system'] ? 'system' : 'custom' ?>"><?= $f['is_system'] ? 'System' : 'Custom' ?></span>
                                    </div>
                                </div>
                                <div><span class="cuz-badge on"><?= htmlspecialchars($f['field_type']) ?></span></div>
                                <div><?= (int) $f['max_length'] > 0 ? (int) $f['max_length'] : '—' ?></div>
                                <div>
                                    <span class="cuz-badge <?= $f['is_required'] ? 'on' : 'off' ?>"><?= $f['is_required'] ? 'Req.' : 'Opt.' ?></span>
                                </div>
                                <div><span class="cuz-badge <?= $f['status'] ? 'on' : 'off' ?>"><?= $f['status'] ? 'Active' : 'Hidden' ?></span></div>
                                <div>
                                    <button type="button" class="cuz-btn cuz-btn-outline btn btn-sm" onclick="toggleEdit('<?= htmlspecialchars($fk) ?>')">Edit</button>
                                    <?php if (!$f['is_system']) { ?>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="delField('<?= htmlspecialchars($fk) ?>')">Delete</button>
                                    <?php } ?>
                                </div>
                                <div></div>
                            </div>

                            <!-- Edit form -->
                            <div class="cuz-edit-form" id="edit_<?= htmlspecialchars($fk) ?>">
                                <form method="POST" class="cuz-inline-form">
                                    <input type="hidden" name="customizer_update_field" value="1">
                                    <input type="hidden" name="module" value="<?= htmlspecialchars($activeModule) ?>">
                                    <input type="hidden" name="field_key" value="<?= htmlspecialchars($fk) ?>">
                                    <div class="col-md-3">
                                        <label class="cuz-form-label">Label</label>
                                        <input type="text" name="field_label" class="form-control" value="<?= htmlspecialchars($f['label']) ?>" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="cuz-form-label">Type</label>
                                        <select name="field_type" class="form-select">
                                            <?php foreach (array('text','email','integer','date','phone','textarea') as $t) { ?>
                                                <option value="<?= $t ?>" <?= $f['field_type'] === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="cuz-form-label">Max Length</label>
                                        <input type="number" name="field_max" class="form-control" value="<?= (int) $f['max_length'] ?>" min="0">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="cuz-form-label">Section</label>
                                        <input type="text" name="field_section" class="form-control" value="<?= htmlspecialchars($f['section'] !== '' ? $f['section'] : 'General') ?>">
                                    </div>
                                    <div class="col-md-1">
                                        <label class="cuz-form-label">Required</label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="field_required" value="1" <?= $f['is_required'] ? 'checked' : '' ?>>
                                        </div>
                                    </div>
                                    <div class="col-md-1">
                                        <label class="cuz-form-label">Active</label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="field_status" value="1" <?= $f['status'] ? 'checked' : '' ?>>
                                        </div>
                                    </div>
                                    <div class="col-md-1">
                                        <button type="submit" class="cuz-btn btn btn-sm">Save</button>
                                    </div>
                                </form>
                            </div>
                        <?php } ?>
                    <?php } ?>
                </div>
            </div>

            <!-- ===== Audit Log ===== -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5><i class="fas fa-history"></i> Change History (<?= htmlspecialchars($moduleName) ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($audit)) { ?>
                        <p class="text-muted mb-0">No changes recorded yet.</p>
                    <?php } else { ?>
                        <div class="table-responsive">
                            <table class="table table-hover audit-table">
                                <thead>
                                    <tr>
                                        <th>Action</th>
                                        <th>Field</th>
                                        <th>Old / New</th>
                                        <th>User</th>
                                        <th>Record</th>
                                        <th>When</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($audit as $a) {
                                        $actClass = in_array($a['action'], array('created', 'field_created')) ? 'act-created'
                                            : (in_array($a['action'], array('updated', 'field_updated')) ? 'act-updated' : 'act-deleted');
                                    ?>
                                        <tr>
                                            <td><span class="act <?= $actClass ?>"><?= htmlspecialchars($a['action']) ?></span></td>
                                            <td><?= htmlspecialchars($a['field_label'] !== '' ? $a['field_label'] : $a['field_key']) ?>
                                                <span class="cuz-fkey">(<?= htmlspecialchars($a['field_key']) ?>)</span>
                                            </td>
                                            <td style="max-width:300px"><?= htmlspecialchars((string) $a['old_value']) ?> → <?= htmlspecialchars((string) $a['new_value']) ?></td>
                                            <td><?= htmlspecialchars($a['user_name'] !== '' ? $a['user_name'] : ('User #' . (int) $a['user_id'])) ?></td>
                                            <td><?= (int) $a['module_record_id'] ?></td>
                                            <td><?= date('M d, Y H:i', strtotime($a['created_at'])) ?></td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    <?php } ?>
                </div>
            </div>

        </div>
    </div>

    <?php include "includes/footer.php"; ?>
    <?php include "includes/scripts.php"; ?>

    <script>
        var activeModule = '<?= htmlspecialchars($activeModule) ?>';

        function toggleEdit(key) {
            var f = document.getElementById('edit_' + key);
            if (f) f.style.display = f.style.display === 'flex' ? 'none' : 'flex';
        }

        function delField(key) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Delete this field?',
                    text: 'This will remove the field and all its saved values. This cannot be undone.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Delete',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#b91c1c'
                }).then(function (r) {
                    if (r.isConfirmed) {
                        var form = document.createElement('form');
                        form.method = 'POST';
                        var k1 = document.createElement('input'); k1.type = 'hidden'; k1.name = 'customizer_delete_field'; k1.value = '1';
                        var k2 = document.createElement('input'); k2.type = 'hidden'; k2.name = 'field_key'; k2.value = key;
                        var k3 = document.createElement('input'); k3.type = 'hidden'; k3.name = 'module'; k3.value = activeModule;
                        form.appendChild(k1); form.appendChild(k2); form.appendChild(k3);
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            } else {
                var form = document.createElement('form');
                form.method = 'POST';
                var k1 = document.createElement('input'); k1.type = 'hidden'; k1.name = 'customizer_delete_field'; k1.value = '1';
                var k2 = document.createElement('input'); k2.type = 'hidden'; k2.name = 'field_key'; k2.value = key;
                var k3 = document.createElement('input'); k3.type = 'hidden'; k3.name = 'module'; k3.value = activeModule;
                form.appendChild(k1); form.appendChild(k2); form.appendChild(k3);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>

</html>
