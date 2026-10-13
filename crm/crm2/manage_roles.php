<?php
/*  ROLES & PERMISSIONS  ─  admin hub
    -------------------------------------------------------------------------
    Lets an administrator:
      • Create / rename / delete roles
      • Grant fine-grained permissions per role (matrix grouped by module)
      • See how many users hold each role
    Built on the unified roles engine (includes/roles.php).
    ------------------------------------------------------------------------- */
include "includes/db.php";
include "includes/auth.php";
require_once "includes/roles.php";

/* --- Admin gate --- */
if ($usertype != '1') {
    header("Location: index.php");
    exit;
}

$msg = '';
$msgType = '';

/* Select the role being edited (default: first role). */
$activeRoleId = isset($_POST['role_id']) ? (int) $_POST['role_id']
    : (isset($_GET['role_id']) ? (int) $_GET['role_id'] : 0);

/* ---------- POST handlers ---------- */
if (isset($_POST['roles_create'])) {
    $res = roles_create($con, $_POST['role_name'] ?? '', $_POST['role_description'] ?? '');
    $msg = $res['message'];
    $msgType = $res['success'] ? 'success' : 'danger';
    if ($res['success']) {
        $activeRoleId = $res['id'];
    }
}

if (isset($_POST['roles_update'])) {
    $res = roles_update($con, (int) $_POST['role_id'], $_POST['role_name'] ?? '', $_POST['role_description'] ?? '');
    $msg = $res['message'];
    $msgType = $res['success'] ? 'success' : 'danger';
}

if (isset($_POST['roles_delete'])) {
    $res = roles_delete($con, (int) $_POST['role_id']);
    $msg = $res['message'];
    $msgType = $res['success'] ? 'success' : 'danger';
    if ($res['success']) {
        $activeRoleId = 0;
    }
}

if (isset($_POST['roles_save_permissions'])) {
    $rid = (int) $_POST['role_id'];
    $granted = $_POST['perm'] ?? array(); // permission_key => 'Yes'
    $norm = array();
    foreach ($granted as $k => $v) {
        $norm[$k] = ($v === 'Yes') ? 'Yes' : 'No';
    }
    $res = roles_save_permissions($con, $rid, $norm);
    $msg = $res['message'];
    $msgType = $res['success'] ? 'success' : 'danger';
    $activeRoleId = $rid;
}

/* ---------- Data ---------- */
$allRoles = roles_all_roles($con);

/* Resolve active role default to the first available role. */
if ($activeRoleId === 0 && !empty($allRoles)) {
    $activeRoleId = (int) $allRoles[0]['role_id'];
}

$activeRole = null;
foreach ($allRoles as $r) {
    if ((int) $r['role_id'] === $activeRoleId) {
        $activeRole = $r;
        break;
    }
}

$rolePerms = $activeRole ? roles_permissions_for_role($con, $activeRoleId) : array();
$roleUserCount = $activeRole ? roles_user_count($con, $activeRoleId) : 0;
$isAdminRole = ($activeRole && (int) $activeRole['role_id'] === ROLE_ADMIN_ROLE_ID);
$isSystem = ($activeRole && (int) $activeRole['is_system'] === 1);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Syed Professional Services - Roles &amp; Permissions</title>
    <?php include "includes/links.php"; ?>
    <style>
        :root {
            --rlz-primary: #1456b8;
            --rlz-primary-dark: #0f4595;
            --rlz-primary-soft: rgba(20, 86, 184, .08);
            --rlz-border: #e8ecf1;
            --rlz-text: #334155;
        }

        .rlz-hero {
            background: linear-gradient(120deg, #1456b8 0%, #1d6fd8 60%, #3b82f6 100%);
            color: #fff;
            border-radius: 14px;
            padding: 22px 26px;
            margin-bottom: 20px;
            box-shadow: 0 8px 20px rgba(20, 86, 184, .25);
        }

        .rlz-hero h3 {
            font-weight: 700;
            margin: 0;
            font-size: 1.4rem;
        }

        .rlz-hero p {
            margin: 6px 0 0;
            opacity: .92;
            font-size: .92rem;
        }

        .rlz-stats {
            display: flex;
            gap: 26px;
            margin-top: 16px;
            flex-wrap: wrap;
        }

        .rlz-stat b {
            font-size: 1.5rem;
            display: block;
            line-height: 1.1;
        }

        .rlz-stat {
            font-size: .78rem;
            opacity: .9;
        }

        .rlz-card {
            background: #fff;
            border: 1px solid var(--rlz-border);
            border-radius: 12px;
            padding: 14px 16px;
            margin-bottom: 12px;
            transition: box-shadow .15s, border-color .15s;
        }

        .rlz-card:hover {
            box-shadow: 0 6px 16px rgba(20, 86, 184, .1);
        }

        .rlz-card.active {
            border-color: var(--rlz-primary);
            box-shadow: 0 0 0 2px var(--rlz-primary-soft);
        }

        .rlz-role-title {
            font-weight: 700;
            font-size: 1rem;
            color: var(--rlz-text);
        }

        .rlz-badge {
            display: inline-block;
            font-size: .7rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 20px;
            margin-left: 6px;
            vertical-align: middle;
        }

        .rlz-badge.system {
            background: var(--rlz-primary-soft);
            color: var(--rlz-primary);
        }

        .rlz-badge.custom {
            background: #eef2ff;
            color: #4f46e5;
        }

        .rlz-badge.locked {
            background: #fee2e2;
            color: #b91c1c;
        }

        .rlz-users {
            font-size: .8rem;
            color: #64748b;
            margin-top: 4px;
        }

        .rlz-btn {
            background: var(--rlz-primary);
            border: none;
            color: #fff;
            border-radius: 8px;
            font-weight: 600;
        }

        .rlz-btn:hover {
            background: var(--rlz-primary-dark);
            color: #fff;
        }

        .rlz-btn-outline {
            background: #fff;
            border: 1px solid var(--rlz-border);
            color: var(--rlz-primary);
        }

        .rlz-btn-outline:hover {
            border-color: var(--rlz-primary);
            color: var(--rlz-primary);
            background: var(--rlz-primary-soft);
        }

        .rlz-form-label {
            font-size: .8rem;
            font-weight: 600;
            color: #475569;
        }

        .rlz-module-tabs {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-bottom: 8px;
        }

        .rlz-module-tab {
            padding: 6px 12px;
            border-radius: 8px;
            border: 1px solid var(--rlz-border);
            background: #fff;
            color: #475569;
            font-size: .8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all .15s;
        }

        .rlz-module-tab.active {
            background: var(--rlz-primary);
            border-color: var(--rlz-primary);
            color: #fff;
        }

        .rlz-cat-title {
            font-weight: 700;
            font-size: .85rem;
            color: var(--rlz-primary);
            text-transform: uppercase;
            letter-spacing: .04em;
            margin: 14px 0 8px;
            border-bottom: 1px solid var(--rlz-border);
            padding-bottom: 6px;
        }

        .rlz-perm-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 10px;
            border: 1px solid var(--rlz-border);
            border-radius: 8px;
            margin-bottom: 8px;
            background: #fff;
            transition: background .15s;
        }

        .rlz-perm-row:hover {
            background: #f8fafc;
        }

        .rlz-perm-label {
            font-size: .88rem;
            color: var(--rlz-text);
        }

        .rlz-perm-key {
            font-size: .72rem;
            color: #94a3b8;
            font-family: monospace;
        }

        .rlz-group-switch {
            font-size: .75rem;
            color: var(--rlz-primary);
            font-weight: 700;
            cursor: pointer;
        }

        .rlz-info {
            font-size: .82rem;
            color: #64748b;
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
                                <h4 class="m-b-10">Roles &amp; Permissions</h4>
                            </div>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                <li class="breadcrumb-item">Roles &amp; Permissions</li>
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

            <div class="rlz-hero">
                <h3><i class="fas fa-user-shield"></i> Roles &amp; Permissions</h3>
                <p>Create unlimited roles and control exactly what each one can do — down to individual actions per module.</p>
                <div class="rlz-stats">
                    <div class="rlz-stat"><b><?= count($allRoles) ?></b>Roles</div>
                    <div class="rlz-stat"><b><?= count(ROLE_PERMISSION_DICTIONARY) ?></b>Modules</div>
                    <div class="rlz-stat"><b><?= $roleUserCount ?></b>Users in selected role</div>
                </div>
            </div>

            <div class="row">
                <!-- LEFT: role list -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-list-alt"></i> Roles</h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($allRoles as $r) {
                                $rid = (int) $r['role_id'];
                                $isActive = ($rid === $activeRoleId);
                                $cnt = roles_user_count($con, $rid);
                                $locked = ($rid === ROLE_ADMIN_ROLE_ID);
                                ?>
                                <a href="manage_roles.php?role_id=<?= $rid ?>" style="text-decoration:none">
                                    <div class="rlz-card<?= $isActive ? ' active' : '' ?>">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="rlz-role-title"><?= htmlspecialchars($r['name']) ?>
                                                <span class="rlz-badge <?= $locked ? 'locked' : ((int) $r['is_system'] === 1 ? 'system' : 'custom') ?>">
                                                    <?= $locked ? 'Locked' : ((int) $r['is_system'] === 1 ? 'System' : 'Custom') ?>
                                                </span>
                                            </span>
                                            <i class="fas fa-chevron-right text-muted"></i>
                                        </div>
                                        <div class="rlz-users"><?= $cnt ?> user<?= $cnt === 1 ? '' : 's' ?></div>
                                    </div>
                                </a>
                            <?php } ?>

                            <hr>

                            <h6 class="rlz-form-label">Add a New Role</h6>
                            <form method="POST" class="row g-2">
                                <div class="col-12">
                                    <input type="text" name="role_name" class="form-control" placeholder="Role name (e.g. Accountant)" required>
                                </div>
                                <div class="col-12">
                                    <input type="text" name="role_description" class="form-control" placeholder="Short description (optional)">
                                </div>
                                <div class="col-12">
                                    <button type="submit" name="roles_create" class="rlz-btn btn"><i class="fas fa-plus"></i> Add Role</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- RIGHT: role detail / permissions -->
                <div class="col-lg-8">
                    <?php if (!$activeRole) { ?>
                        <div class="card">
                            <div class="card-body">
                                <p class="text-muted mb-0">Select a role on the left to manage its permissions.</p>
                            </div>
                        </div>
                    <?php } else { ?>

                    <!-- Role header / rename -->
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start flex-wrap" style="gap:10px">
                                <div>
                                    <h5 class="mb-1"><?= htmlspecialchars($activeRole['name']) ?></h5>
                                    <div class="rlz-info">
                                        <i class="fas fa-users"></i> <?= $roleUserCount ?> users
                                        <?php if ($isAdminRole) { ?> · <span class="text-danger fw-bold">Administrator always has full access</span><?php } ?>
                                    </div>
                                </div>
                                <?php if (!$isAdminRole) { ?>
                                    <form method="POST" class="d-flex" style="gap:6px;flex-wrap:wrap">
                                        <input type="hidden" name="role_id" value="<?= $activeRoleId ?>">
                                        <input type="text" name="role_name" value="<?= htmlspecialchars($activeRole['name']) ?>" class="form-control" style="max-width:180px" required>
                                        <button type="submit" name="roles_update" class="rlz-btn btn btn-sm">Rename</button>
                                        <?php if (!$isSystem) { ?>
                                            <button type="button" class="btn btn-danger btn-sm" onclick="deleteRole(<?= $activeRoleId ?>)">Delete</button>
                                        <?php } ?>
                                    </form>
                                <?php } ?>
                            </div>
                        </div>
                    </div>

                    <?php if (!$isAdminRole) { ?>
                    <!-- Permission matrix -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                            <h5><i class="fas fa-check-circle"></i> Permissions</h5>
                            <div class="d-flex align-items-center" style="gap:8px">
                                <span class="rlz-group-switch" role="button" data-grant="on" id="grantAllBtn"><i class="fas fa-check-square"></i> All On</span>
                                <span class="rlz-group-switch text-muted" role="button" data-grant="off" id="clearAllBtn"><i class="fas fa-square"></i> All Off</span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="rlz-module-tabs" id="moduleTabs">
                                <?php foreach (ROLE_PERMISSION_DICTIONARY as $mkey => $mcfg) { ?>
                                    <button type="button" class="rlz-module-tab" data-module="<?= $mkey ?>" data-active="0">
                                        <i class="<?= $mcfg['icon'] ?? 'fas fa-shield-alt' ?>"></i> <?= htmlspecialchars($mcfg['name']) ?>
                                    </button>
                                <?php } ?>
                            </div>

                            <form method="POST" id="permsForm">
                                <input type="hidden" name="roles_save_permissions" value="1">
                                <input type="hidden" name="role_id" value="<?= $activeRoleId ?>">
                                <?php
                                $first = true;
                                foreach (ROLE_PERMISSION_DICTIONARY as $mkey => $mcfg) {
                                    ?>
                                    <div class="rlz-module-panel" data-module="<?= $mkey ?>" style="display:<?= $first ? 'block' : 'none' ?>">
                                        <?php foreach ($mcfg['cats'] as $cat => $perms) { ?>
                                            <div class="rlz-cat-title">
                                                <?= htmlspecialchars($cat) ?>
                                                <span class="rlz-group-switch" role="button" data-module="<?= $mkey ?>" data-grant="on">Grant all</span> ·
                                                <span class="rlz-group-switch text-muted" role="button" data-module="<?= $mkey ?>" data-grant="off">Revoke all</span>
                                            </div>
                                            <?php foreach ($perms as $key => $p) {
                                                $checked = (($rolePerms[$key] ?? 'No') === 'Yes') || $isAdminRole;
                                                ?>
                                                <div class="rlz-perm-row" data-key="<?= htmlspecialchars($key) ?>" data-module="<?= $mkey ?>">
                                                    <div>
                                                        <div class="rlz-perm-label"><?= htmlspecialchars($p['label']) ?></div>
                                                        <div class="rlz-perm-key"><?= htmlspecialchars($key) ?></div>
                                                    </div>
                                                    <div class="form-check form-switch mb-0">
                                                        <input class="form-check-input rlz-switch" type="checkbox" name="perm[<?= htmlspecialchars($key) ?>]" value="Yes" <?= $checked ? 'checked' : '' ?> id="sw_<?= htmlspecialchars($key) ?>">
                                                        <label class="form-check-label" for="sw_<?= htmlspecialchars($key) ?>"></label>
                                                    </div>
                                                </div>
                                            <?php } ?>
                                        <?php } ?>
                                    </div>
                                    <?php
                                    $first = false;
                                }
                                ?>
                                <div class="mt-3">
                                    <button type="submit" class="rlz-btn btn"><i class="fas fa-save"></i> Save Permissions</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php } else { ?>
                    <div class="card">
                        <div class="card-body">
                            <div class="alert alert-info mb-0">
                                <i class="fas fa-lock"></i> The <strong>Administrator</strong> role is system-locked and automatically has every capability. Use the left panel to manage other roles.
                            </div>
                        </div>
                    </div>
                    <?php } ?>

                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <?php include "includes/footer.php"; ?>
    <?php include "includes/scripts.php"; ?>

    <script>
        // Module tabs
        var tabs = document.querySelectorAll('.rlz-module-tab');
        var panels = document.querySelectorAll('.rlz-module-panel');
        function showModule(mod) {
            panels.forEach(function (p) { p.style.display = p.dataset.module === mod ? 'block' : 'none'; });
            tabs.forEach(function (t) { t.classList.toggle('active', t.dataset.module === mod); });
        }
        tabs.forEach(function (t) {
            t.addEventListener('click', function () { showModule(t.dataset.module); });
        });
        // activate first tab
        if (tabs.length) showModule(tabs[0].dataset.module);

        // Grant / revoke within a module (or all)
        function setModule(mod, on) {
            document.querySelectorAll('.rlz-perm-row[data-module="' + mod + '"] input[type=checkbox]').forEach(function (cb) {
                cb.checked = on;
            });
        }
        document.querySelectorAll('[data-grant]').forEach(function (el) {
            el.addEventListener('click', function () {
                var mod = el.dataset.module || null;
                var on = el.dataset.grant === 'on';
                if (mod) setModule(mod, on);
                else {
                    Object.keys(window.__rlzModules || {}).forEach(function () {});
                    document.querySelectorAll('.rlz-perm-row input[type=checkbox]').forEach(function (cb) { cb.checked = on; });
                }
            });
        });

        // Delete role confirmation
        function deleteRole(roleId) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Delete this role?',
                    text: 'Users assigned to it will be moved to Restricted. This cannot be undone.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Delete',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#b91c1c'
                }).then(function (r) {
                    if (r.isConfirmed) submitDelete(roleId);
                });
            } else {
                submitDelete(roleId);
            }
        }
        function submitDelete(roleId) {
            var form = document.createElement('form');
            form.method = 'POST';
            var k1 = document.createElement('input'); k1.type = 'hidden'; k1.name = 'roles_delete'; k1.value = '1';
            var k2 = document.createElement('input'); k2.type = 'hidden'; k2.name = 'role_id'; k2.value = roleId;
            form.appendChild(k1); form.appendChild(k2);
            document.body.appendChild(form);
            form.submit();
        }
    </script>
</body>

</html>
