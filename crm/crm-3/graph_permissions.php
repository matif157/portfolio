<?php
/**
 * Admin page — manage per-user graph visibility for report.php.
 *
 * Layout follows the standard curdir.php chrome (sidebar + header + dash-container).
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);

include "includes/db.php";
include "includes/auth.php";
include "includes/graph_permissions.php";

$user_id   = $_SESSION['user_id'] ?? 0;
$user_type = $_SESSION['user_type'] ?? '';
$is_admin  = ($user_type == '1');
if (!$is_admin) {
    header('Location: index.php');
    exit;
}

graph_perms_bootstrap($con);

/* ---------- POST: save matrix ---------- */
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_matrix'])) {
    $grants        = $_POST['grants'] ?? [];   // grants[user_id][] = graph_key
    $admin_user_id = (int)$user_id;
    $catalogKeys   = array_keys(graph_perms_flat_catalog());

    $con->begin_transaction();
    try {
        $submittedUserIds = array_map('intval', array_keys($grants));
        $submittedUserIds = array_unique(array_filter($submittedUserIds, fn($v) => $v > 0));

        if (!empty($submittedUserIds)) {
            $uidIn = implode(',', $submittedUserIds);
            $con->query("DELETE FROM graph_permissions WHERE user_id IN ($uidIn)");
        }

        $ins = $con->prepare("INSERT INTO graph_permissions (user_id, graph_key, granted_by) VALUES (?, ?, ?)");
        foreach ($grants as $uid => $keys) {
            $uid = (int)$uid;
            if ($uid <= 0 || !is_array($keys)) continue;
            foreach ($keys as $k) {
                if (!in_array($k, $catalogKeys, true)) continue;
                $ins->bind_param('isi', $uid, $k, $admin_user_id);
                $ins->execute();
            }
        }
        $ins->close();
        $con->commit();
        $flash = 'Saved.';
    } catch (Throwable $e) {
        $con->rollback();
        $flash = 'Error saving: ' . $e->getMessage();
    }
}

/* ---------- Load users + current grants ---------- */
$users_rs = $con->query("
    SELECT user_id, user_name, user_login, user_email, user_type, user_login_status
    FROM users
    WHERE user_type <> '1'
    ORDER BY user_name ASC
");
$gp_users = $users_rs ? $users_rs->fetch_all(MYSQLI_ASSOC) : [];

$current = [];
$rs = $con->query("SELECT user_id, graph_key FROM graph_permissions");
while ($r = $rs->fetch_assoc()) {
    $current[(int)$r['user_id']][$r['graph_key']] = true;
}

$catalog = graph_perms_catalog();
$flat    = graph_perms_flat_catalog();
?>
<!DOCTYPE html>
<html lang="en" dir="">
<meta name="csrf-token" id="csrf-token" content="">

<head>
   <title>Graph Permissions – Syed Professional Services</title>
   <?php include "includes/links.php"; ?>
   <style>
      .gp-matrix-wrap { overflow:auto; max-height:70vh; border:1px solid #eef0f3; border-radius:8px; }
      table.gp-matrix { width:100%; border-collapse:separate; border-spacing:0; font-size:0.85rem; }
      table.gp-matrix th, table.gp-matrix td { padding:8px 10px; border-bottom:1px solid #eef0f3; vertical-align:middle; white-space:nowrap; }
      table.gp-matrix thead th { position:sticky; top:0; background:#fafbfc; z-index:2; }
      table.gp-matrix th.user-col, table.gp-matrix td.user-col { position:sticky; left:0; background:#fff; z-index:1; min-width:240px; border-right:1px solid #eef0f3; }
      table.gp-matrix thead th.user-col { z-index:3; background:#fafbfc; }
      table.gp-matrix th.section { background:#eef9e8; color:#3d7d1f; font-weight:600; text-align:center; border-bottom:2px solid #6FD943; }
      table.gp-matrix th.center, table.gp-matrix td.center { text-align:center; }
      .gp-row:hover td, .gp-row:hover td.user-col { background:#fafff5; }
      .gp-key { font-family: ui-monospace, Consolas, monospace; font-size:0.7rem; color:#888; display:block; }
      .gp-toolbar { display:flex; justify-content:space-between; align-items:center; gap:8px; flex-wrap:wrap; }
      .gp-row-tools .btn { padding:2px 8px; font-size:0.72rem; }
      .form-check-input.gp-chk { cursor:pointer; }
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

         <!-- Page header -->
         <div class="card card-body">
            <div class="page-block">
               <div class="row align-items-center">
                  <div class="col-auto">
                     <div class="page-header-title">
                        <h4 class="m-b-10"><i class="fas fa-chart-bar me-2"></i>Graph Permissions</h4>
                     </div>
                     <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="report.php">Reports</a></li>
                        <li class="breadcrumb-item">Graph Permissions</li>
                     </ul>
                  </div>
                  <div class="col-auto ms-auto">
                     <a href="report.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Reports
                     </a>
                  </div>
               </div>
            </div>
         </div>

         <?php if ($flash): ?>
            <div class="alert alert-success alert-dismissible">
               <?= htmlspecialchars($flash) ?>
               <button class="btn-close" data-bs-dismiss="alert"></button>
            </div>
         <?php endif; ?>

         <!-- Matrix card -->
         <div class="card card-body">
            <div class="page-block">
               <p class="text-muted mb-3">
                  Pick which graphs each user is allowed to see on <code>report.php</code>.
                  Admins always see every graph and are not listed here.
               </p>

               <form method="POST">
                  <div class="gp-toolbar mb-3">
                     <div>
                        <button type="button" class="btn btn-sm btn-outline-success" id="gpSelectAll">
                           <i class="fas fa-check-double"></i> Grant all to everyone
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger" id="gpClearAll">
                           <i class="fas fa-times"></i> Revoke all
                        </button>
                     </div>
                     <button type="submit" name="save_matrix" value="1" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                     </button>
                  </div>

                  <div class="gp-matrix-wrap">
                     <table class="gp-matrix">
                        <thead>
                           <tr>
                              <th class="user-col" rowspan="2">User</th>
                              <?php foreach ($catalog as $section => $items): ?>
                                 <th class="section" colspan="<?= count($items) ?>"><?= htmlspecialchars($section) ?></th>
                              <?php endforeach; ?>
                              <th class="center" rowspan="2">Row tools</th>
                           </tr>
                           <tr>
                              <?php foreach ($catalog as $section => $items): ?>
                                 <?php foreach ($items as $key => $label): ?>
                                    <th class="center" title="<?= htmlspecialchars($key) ?>">
                                       <div><?= htmlspecialchars($label) ?></div>
                                       <span class="gp-key"><?= htmlspecialchars($key) ?></span>
                                    </th>
                                 <?php endforeach; ?>
                              <?php endforeach; ?>
                           </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($gp_users as $u):
                           $uid      = (int)$u['user_id'];
                           $isLocked = ((int)$u['user_login_status'] !== 1);
                        ?>
                           <tr class="gp-row" data-uid="<?= $uid ?>">
                              <td class="user-col">
                                 <div class="fw-semibold">
                                    <?= htmlspecialchars($u['user_name']) ?>
                                    <?php if ($isLocked): ?>
                                       <span class="badge bg-secondary ms-1">Disabled</span>
                                    <?php endif; ?>
                                 </div>
                                 <div class="small text-muted"><?= htmlspecialchars($u['user_login']) ?> · #<?= $uid ?></div>
                              </td>
                              <?php foreach ($flat as $key => $label):
                                 $checked = !empty($current[$uid][$key]);
                              ?>
                                 <td class="center">
                                    <input class="form-check-input gp-chk" type="checkbox"
                                          name="grants[<?= $uid ?>][]" value="<?= htmlspecialchars($key) ?>"
                                          <?= $checked ? 'checked' : '' ?>>
                                 </td>
                              <?php endforeach; ?>
                              <td class="center gp-row-tools">
                                 <button type="button" class="btn btn-outline-success btn-sm gp-row-all" title="Check all in this row"><i class="fas fa-check"></i></button>
                                 <button type="button" class="btn btn-outline-danger  btn-sm gp-row-none" title="Uncheck all in this row"><i class="fas fa-times"></i></button>
                              </td>
                           </tr>
                        <?php endforeach; ?>
                        <?php if (!$gp_users): ?>
                           <tr><td colspan="<?= count($flat) + 2 ?>" class="text-center text-muted py-4">No non-admin users found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                     </table>
                  </div>

                  <div class="d-flex justify-content-end mt-3">
                     <button type="submit" name="save_matrix" value="1" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                     </button>
                  </div>
               </form>
            </div>
         </div>

      </div>
   </div>

   <?php include "includes/footer.php"; ?>
   <?php include "includes/scripts.php"; ?>

   <script>
      document.querySelectorAll('.gp-row-all').forEach(b => b.addEventListener('click', () => {
         b.closest('tr').querySelectorAll('.gp-chk').forEach(c => c.checked = true);
      }));
      document.querySelectorAll('.gp-row-none').forEach(b => b.addEventListener('click', () => {
         b.closest('tr').querySelectorAll('.gp-chk').forEach(c => c.checked = false);
      }));
      document.getElementById('gpSelectAll').addEventListener('click', () => {
         document.querySelectorAll('.gp-chk').forEach(c => c.checked = true);
      });
      document.getElementById('gpClearAll').addEventListener('click', () => {
         document.querySelectorAll('.gp-chk').forEach(c => c.checked = false);
      });
   </script>
</body>

</html>
