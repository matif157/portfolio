<?php
require_once __DIR__ . '/bootstrap.php';

$isAdmin = hrm_is_admin();

if ($isAdmin && isset($_GET['delete'])) {
    $result = hrm_delete_employee((int) $_GET['delete']);
    hrm_flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Employee deleted successfully.' : $result['message']);
    header('Location: employees.php');
    exit();
}

if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $existingId = !empty($_POST['employee_id']) ? (int) $_POST['employee_id'] : null;
    $result = hrm_save_employee($_POST, $existingId);
    hrm_flash($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Employee saved successfully.' : $result['message']);
    header('Location: employees.php' . ($result['ok'] ? '' : ($existingId ? '?edit=' . $existingId : '')));
    exit();
}

$editingId    = $isAdmin && isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$employee     = $isAdmin ? ($editingId ? hrm_fetch_employee($editingId) : null) : hrm_fetch_employee(hrm_session_user_id());
$departments  = hrm_fetch_departments();
$designations = hrm_fetch_designations();
$employees    = $isAdmin ? hrm_fetch_employees(true) : [];

new_hrm_render_page_start(
    $isAdmin ? 'Employees' : 'My Profile',
    $isAdmin ? 'Employees' : 'My Profile',
    $isAdmin
    ? 'Add, edit, and manage your team from one place.'
    : 'View your employee record. Only admins can make changes.'
);
?>

<!-- ── DataTables CDN ── -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

<style>
/* ── Layout ── */
.emp-layout {
    display: grid;
    grid-template-columns: 360px 1fr;
    gap: 20px;
    align-items: start;
}
@media (max-width: 991px) { .emp-layout { grid-template-columns: 1fr; } }

/* ── Form Panel ── */
.emp-form-panel {
    background: #fff;
    border: 1px solid var(--hrm-border);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 24px rgba(15,23,42,.07);
    position: sticky;
    top: 20px;
}
.emp-form-header {
    padding: 18px 22px 16px;
    background: linear-gradient(135deg, #1456b8 0%, #1e75f0 100%);
    display: flex;
    align-items: center;
    gap: 12px;
}
.emp-form-header-icon {
    width: 36px; height: 36px;
    border-radius: 8px;
    background: rgba(255,255,255,.18);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.emp-form-header-icon svg { width: 18px; height: 18px; stroke: #fff; }
.emp-form-header-title { color: #fff; font-size: 14px; font-weight: 700; }
.emp-form-header-sub   { color: rgba(255,255,255,.75); font-size: 12px; margin-top: 1px; }
.emp-form-body { padding: 20px 22px 22px; }

/* Form fields */
.emp-field { margin-bottom: 14px; }
.emp-field label {
    display: block;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--hrm-muted);
    margin-bottom: 5px;
}
.emp-field .form-control,
.emp-field .form-select,
.emp-field select {
    border: 1.5px solid var(--hrm-border);
    border-radius: 8px;
    padding: 8px 12px;
    font-size: 13px;
    color: var(--hrm-text);
    background: #fafbfd;
    transition: border-color .15s, box-shadow .15s;
    width: 100%;
}
.emp-field .form-control:focus,
.emp-field select:focus {
    border-color: var(--hrm-primary);
    box-shadow: 0 0 0 3px rgba(20,86,184,.10);
    background: #fff;
    outline: none;
}
.emp-field .form-control::placeholder { color: #aab4c0; }
.emp-field-hint { font-size: 11px; color: var(--hrm-muted); margin-top: 4px; }
.emp-field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

/* Divider */
.emp-form-divider {
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .10em;
    color: var(--hrm-muted);
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 16px 0 14px;
}
.emp-form-divider::before,
.emp-form-divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--hrm-border);
}

/* Buttons */
.emp-btn-row { display: flex; gap: 8px; margin-top: 18px; }
.emp-btn-primary {
    flex: 1;
    padding: 10px 16px;
    border-radius: 8px;
    border: none;
    background: linear-gradient(135deg, #1456b8, #1e75f0);
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition: opacity .15s, transform .15s;
}
.emp-btn-primary:hover { opacity: .9; transform: translateY(-1px); }
.emp-btn-light {
    padding: 10px 16px;
    border-radius: 8px;
    border: 1.5px solid var(--hrm-border);
    background: #fff;
    color: var(--hrm-muted);
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    transition: border-color .15s;
}
.emp-btn-light:hover { border-color: var(--hrm-primary); color: var(--hrm-primary); }

/* ── Directory Panel ── */
.emp-dir-panel {
    background: #fff;
    border: 1px solid var(--hrm-border);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 24px rgba(15,23,42,.07);
}
.emp-dir-header {
    padding: 16px 22px;
    background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
    border-bottom: 1px solid var(--hrm-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
.emp-dir-title {
    display: flex; align-items: center; gap: 8px;
    font-size: 14px; font-weight: 700; color: var(--hrm-text);
}
.emp-dir-title svg { width: 18px; height: 18px; stroke: var(--hrm-primary); }
.emp-dir-count {
    font-size: 11px; font-weight: 700;
    padding: 4px 10px; border-radius: 999px;
    background: var(--hrm-primary-soft); color: var(--hrm-primary);
}
.emp-dir-body { padding: 18px 20px; }

/* Avatar */
.emp-avatar {
    width: 32px; height: 32px;
    border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 800;
    flex-shrink: 0;
}

/* Status badges */
.emp-status {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 9px; border-radius: 999px;
    font-size: 11px; font-weight: 700; white-space: nowrap;
}
.emp-status--active   { background: #e8f7ee; color: #15803d; }
.emp-status--inactive { background: #feeceb; color: #b42318; }
.emp-status--on_leave { background: #fff4df; color: #b45309; }
.emp-status-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

/* Role badge */
.emp-role {
    display: inline-flex; align-items: center;
    padding: 3px 8px; border-radius: 6px;
    font-size: 11px; font-weight: 700;
}
.emp-role--admin    { background: #f1ebff; color: #6d28d9; }
.emp-role--employee { background: #eef2f6; color: #475569; }

/* Action buttons */
.emp-action-edit {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 5px 10px; border-radius: 7px;
    background: var(--hrm-primary-soft); color: var(--hrm-primary);
    font-size: 12px; font-weight: 700; text-decoration: none;
    border: 1px solid transparent;
    transition: background .13s, border-color .13s;
}
.emp-action-edit:hover { background: #d4e5ff; border-color: var(--hrm-primary); }
.emp-action-delete {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 5px 10px; border-radius: 7px;
    background: #feeceb; color: var(--hrm-danger);
    font-size: 12px; font-weight: 700; text-decoration: none;
    border: 1px solid transparent;
    transition: background .13s;
}
.emp-action-delete:hover { background: #fdd9d7; }
.emp-actions-cell { display: flex; gap: 6px; justify-content: flex-end; }

/* Employee name cell */
.emp-name-cell { display: flex; align-items: center; gap: 10px; }
.emp-name-info { display: flex; flex-direction: column; }
.emp-name-primary { font-size: 13px; font-weight: 700; color: var(--hrm-text); }
.emp-name-secondary { font-size: 11px; color: var(--hrm-muted); margin-top: 1px; }

/* DataTables overrides */
.emp-dt-table thead th {
    background: #f4f7fb !important;
    color: var(--hrm-muted) !important;
    font-size: 11px !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: .06em !important;
    border-bottom: 2px solid var(--hrm-border) !important;
    white-space: nowrap;
    padding: 10px 12px !important;
}
.emp-dt-table td {
    vertical-align: middle !important;
    padding: 10px 12px !important;
    border-bottom: 1px solid #f1f5f9 !important;
    font-size: 13px;
}
.emp-dt-table tbody tr:last-child td { border-bottom: none !important; }
.emp-dt-table tbody tr { transition: background .12s; }
.emp-dt-table tbody tr:hover td { background: #f5f9ff !important; }

div.dataTables_wrapper div.dataTables_length select,
div.dataTables_wrapper div.dataTables_filter input {
    border: 1.5px solid var(--hrm-border) !important;
    border-radius: 8px !important;
    padding: 6px 10px !important;
    font-size: 12px !important;
    background: #fff !important;
    outline: none !important;
    box-shadow: none !important;
}
div.dataTables_wrapper div.dataTables_filter input:focus {
    border-color: var(--hrm-primary) !important;
    box-shadow: 0 0 0 3px rgba(20,86,184,.10) !important;
}
div.dataTables_wrapper div.dataTables_length label,
div.dataTables_wrapper div.dataTables_filter label {
    display: flex !important; align-items: center !important; gap: 6px !important;
    font-size: 12px !important; color: var(--hrm-muted) !important; margin: 0 !important;
}
div.dataTables_wrapper div.dataTables_info {
    font-size: 12px !important; color: var(--hrm-muted) !important; padding-top: 10px !important;
}
div.dataTables_wrapper .pagination { gap: 3px; margin: 8px 0 0; }
div.dataTables_wrapper .pagination .page-item .page-link {
    border-radius: 6px !important; font-size: 12px !important;
    padding: 5px 10px !important; border: 1px solid var(--hrm-border) !important;
    color: var(--hrm-text) !important; background: #fff !important; line-height: 1.4 !important;
}
div.dataTables_wrapper .pagination .page-item .page-link:hover {
    background: var(--hrm-primary-soft) !important; color: var(--hrm-primary) !important;
}
div.dataTables_wrapper .pagination .page-item.active .page-link {
    background: var(--hrm-primary) !important;
    border-color: var(--hrm-primary) !important;
    color: #fff !important;
}
div.dataTables_wrapper .pagination .page-item.disabled .page-link { opacity: .4 !important; }

/* ── Profile view (non-admin) ── */
.emp-profile-card {
    background: #fff;
    border: 1px solid var(--hrm-border);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 24px rgba(15,23,42,.07);
}
.emp-profile-hero {
    background: linear-gradient(135deg, #1456b8 0%, #1e75f0 100%);
    padding: 28px 28px 24px;
    display: flex;
    align-items: center;
    gap: 20px;
}
.emp-profile-big-avatar {
    width: 64px; height: 64px; border-radius: 50%;
    background: rgba(255,255,255,.2);
    border: 3px solid rgba(255,255,255,.4);
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; font-weight: 800; color: #fff;
    flex-shrink: 0;
}
.emp-profile-hero-name { color: #fff; font-size: 18px; font-weight: 800; }
.emp-profile-hero-sub  { color: rgba(255,255,255,.75); font-size: 13px; margin-top: 3px; }
.emp-profile-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0;
    padding: 0;
}
@media (max-width: 767px) { .emp-profile-grid { grid-template-columns: 1fr 1fr; } }
.emp-profile-field {
    padding: 18px 22px;
    border-right: 1px solid var(--hrm-border);
    border-bottom: 1px solid var(--hrm-border);
}
.emp-profile-field:nth-child(3n) { border-right: none; }
@media (max-width: 767px) {
    .emp-profile-field:nth-child(2n) { border-right: none; }
    .emp-profile-field:nth-child(3n) { border-right: 1px solid var(--hrm-border); }
}
.emp-profile-label {
    font-size: 10px; font-weight: 800;
    text-transform: uppercase; letter-spacing: .08em;
    color: var(--hrm-muted); margin-bottom: 5px;
}
.emp-profile-value {
    font-size: 13px; font-weight: 700; color: var(--hrm-text);
}

/* Avatar color palette */
.avc-0 { background:#e8f1ff; color:#1456b8; }
.avc-1 { background:#e8f7ee; color:#15803d; }
.avc-2 { background:#f1ebff; color:#6d28d9; }
.avc-3 { background:#fff4df; color:#b45309; }
.avc-4 { background:#feeceb; color:#b42318; }
.avc-5 { background:#e0f7f5; color:#0f766e; }
</style>

<?php if ($isAdmin): ?>

<div class="emp-layout">

    <!-- ── LEFT: Add / Edit Form ── -->
    <div class="emp-form-panel">
        <div class="emp-form-header">
            <div class="emp-form-header-icon">
                <?php if ($employee): ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                <?php else: ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                <?php endif; ?>
            </div>
            <div>
                <div class="emp-form-header-title"><?php echo $employee ? 'Edit Employee' : 'Add New Employee'; ?></div>
                <div class="emp-form-header-sub"><?php echo $employee ? 'Update the employee\'s details below.' : 'Fill in the details to create an account.'; ?></div>
            </div>
        </div>

        <div class="emp-form-body">
            <form method="post" action="new_hrm/employees.php<?php echo $employee ? '?edit=' . (int)$employee['user_id'] : ''; ?>">
                <input type="hidden" name="employee_id" value="<?php echo (int)($employee['user_id'] ?? 0); ?>">

                <!-- Account -->
                <div class="emp-form-divider">Account</div>

                <div class="emp-field">
                    <label>Full Name</label>
                    <input type="text" name="full_name" class="form-control" required
                           placeholder="e.g. Sarah Khan"
                           value="<?php echo hrm_e($employee['full_name'] ?? $employee['user_name'] ?? ''); ?>">
                </div>

                <div class="emp-field-row">
                    <div class="emp-field">
                        <label>Username</label>
                        <input type="text" name="user_login" class="form-control" required
                               placeholder="login"
                               value="<?php echo hrm_e($employee['user_login'] ?? ''); ?>">
                    </div>
                    <div class="emp-field">
                        <label>Email</label>
                        <input type="email" name="user_email" class="form-control" required
                               placeholder="email@example.com"
                               value="<?php echo hrm_e($employee['user_email'] ?? ''); ?>">
                    </div>
                </div>

                <div class="emp-field">
                    <label><?php echo $employee ? 'New Password' : 'Password'; ?></label>
                    <input type="text" name="user_password" class="form-control"
                           placeholder="<?php echo $employee ? 'Leave blank to keep current' : 'Set a password'; ?>"
                           value="">
                    <?php if ($employee): ?><div class="emp-field-hint">Leave blank to keep the current password.</div><?php endif; ?>
                </div>

                <!-- Role & Status -->
                <div class="emp-form-divider">Role &amp; Status</div>

                <div class="emp-field-row">
                    <div class="emp-field">
                        <label>Role</label>
                        <select name="role" class="form-control">
                            <option value="employee" <?php echo hrm_selected_role($employee) === 'employee' ? 'selected' : ''; ?>>Employee</option>
                            <option value="admin"    <?php echo hrm_selected_role($employee) === 'admin'    ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </div>
                    <div class="emp-field">
                        <label>Status</label>
                        <?php $statusValue = $employee['hrm_status'] ?? 'active'; ?>
                        <select name="status" class="form-control">
                            <option value="active"   <?php echo $statusValue === 'active'   ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $statusValue === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="on_leave" <?php echo $statusValue === 'on_leave' ? 'selected' : ''; ?>>On Leave</option>
                        </select>
                    </div>
                </div>

                <!-- Organisation -->
                <div class="emp-form-divider">Organisation</div>

                <div class="emp-field-row">
                    <div class="emp-field">
                        <label>Department</label>
                        <select name="department_id" class="form-control">
                            <option value="0">— Select —</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo (int)$dept['department_id']; ?>"
                                    <?php echo (int)($employee['department_id'] ?? 0) === (int)$dept['department_id'] ? 'selected' : ''; ?>>
                                    <?php echo hrm_e($dept['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="emp-field">
                        <label>Designation</label>
                        <select name="designation_id" class="form-control">
                            <option value="0">— Select —</option>
                            <?php foreach ($designations as $desig): ?>
                                <option value="<?php echo (int)$desig['designation_id']; ?>"
                                    <?php echo (int)($employee['designation_id'] ?? 0) === (int)$desig['designation_id'] ? 'selected' : ''; ?>>
                                    <?php echo hrm_e($desig['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="emp-field">
                    <label>Designation Text</label>
                    <input type="text" name="user_desig" class="form-control"
                           placeholder="e.g. Senior Developer"
                           value="<?php echo hrm_e($employee['user_desig'] ?? $employee['designation_name'] ?? ''); ?>">
                </div>

                <!-- Details -->
                <div class="emp-form-divider">Details</div>

                <div class="emp-field-row">
                    <div class="emp-field">
                        <label>Employee Code</label>
                        <input type="text" name="employee_code" class="form-control"
                               placeholder="EMP-001"
                               value="<?php echo hrm_e($employee['hrm_employee_code'] ?? $employee['device_employee_code'] ?? ''); ?>">
                    </div>
                    <div class="emp-field">
                        <label>Device User ID</label>
                        <input type="text" name="device_user_id" class="form-control"
                               placeholder="Device ID"
                               value="<?php echo hrm_e($employee['device_user_id'] ?? ''); ?>">
                    </div>
                </div>

                <div class="emp-field-row">
                    <div class="emp-field">
                        <label>Phone</label>
                        <input type="text" name="phone" class="form-control"
                               placeholder="+92 300 0000000"
                               value="<?php echo hrm_e($employee['phone'] ?? ''); ?>">
                    </div>
                    <div class="emp-field">
                        <label>Join Date</label>
                        <input type="date" name="join_date" class="form-control"
                               value="<?php echo hrm_e($employee['join_date'] ?? date('Y-m-d')); ?>">
                    </div>
                </div>

                <div class="emp-btn-row">
                    <button type="submit" class="emp-btn-primary">
                        <?php echo $employee ? '✓ Save Changes' : '+ Add Employee'; ?>
                    </button>
                    <?php if ($employee): ?>
                        <a href="new_hrm/employees.php" class="emp-btn-light">Cancel</a>
                    <?php endif; ?>
                </div>

            </form>
        </div>
    </div>

    <!-- ── RIGHT: Directory DataTable ── -->
    <div class="emp-dir-panel">
        <div class="emp-dir-header">
            <div class="emp-dir-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
                Employee Directory
            </div>
            <span class="emp-dir-count"><?php echo count($employees); ?> people</span>
        </div>
        <div class="emp-dir-body">
            <table id="employeeTable" class="emp-dt-table display nowrap" style="width:100%">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Role</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($employees as $row):
$name    = $row['full_name'] ?: $row['user_name'];
$initials = hrm_initials($name);
$colorIdx = abs(crc32($name)) % 6;
$role     = hrm_selected_role($row);
$status   = $row['hrm_status'] ?: 'active';
                ?>
                <tr>
                    <td>
                        <div class="emp-name-cell">
                            <div class="emp-avatar avc-<?php echo $colorIdx; ?>"><?php echo hrm_e($initials); ?></div>
                            <div class="emp-name-info">
                                <span class="emp-name-primary"><?php echo hrm_e($name); ?></span>
                                <span class="emp-name-secondary">
                                    <?php echo hrm_e($row['user_email']); ?>
                                    <?php if ($row['user_login']): ?>
                                        &nbsp;&middot;&nbsp;<?php echo hrm_e($row['user_login']); ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="emp-role emp-role--<?php echo hrm_e($role); ?>">
                            <?php echo hrm_e(ucfirst($role)); ?>
                        </span>
                    </td>
                    <td><?php echo hrm_e($row['department_name'] ?: '—'); ?></td>
                    <td>
                        <span class="emp-status emp-status--<?php echo hrm_e($status); ?>">
                            <span class="emp-status-dot"></span>
                            <?php echo hrm_e(ucfirst(str_replace('_', ' ', $status))); ?>
                        </span>
                    </td>
                    <td>
                        <div class="emp-actions-cell">
                            <a href="new_hrm/employees.php?edit=<?php echo (int)$row['user_id']; ?>"
                               class="emp-action-edit">
                                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" width="12"><path d="M11.5 2.5a1.414 1.414 0 0 1 2 2L5 13H3v-2L11.5 2.5z"/></svg>
                                Edit
                            </a>
                            <!-- <a href="new_hrm/employees.php?delete=<?php echo (int)$row['user_id']; ?>"
                               class="emp-action-delete"
                               onclick="return confirm('Delete <?php echo hrm_e(addslashes($name)); ?> and all related HRM data?');">
                                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" width="12"><polyline points="2 4 14 4"/><path d="M5 4V2h6v2M6 7v5M10 7v5"/><path d="M3 4l1 10h8l1-10"/></svg>
                                Delete
                            </a> -->
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /emp-layout -->

<?php else: /* ── NON-ADMIN PROFILE VIEW ── */ ?>

<div class="emp-profile-card">

    <!-- Hero -->
    <div class="emp-profile-hero">
        <div class="emp-profile-big-avatar">
            <?php echo hrm_e(hrm_initials($employee['full_name'] ?: $employee['user_name'] ?: 'U')); ?>
        </div>
        <div>
            <div class="emp-profile-hero-name">
                <?php echo hrm_e($employee['full_name'] ?: $employee['user_name'] ?: ($_SESSION['fullname'] ?? 'Employee')); ?>
            </div>
            <div class="emp-profile-hero-sub">
                <?php echo hrm_e($employee['designation_name'] ?: $employee['user_desig'] ?: 'Employee'); ?>
                <?php if ($employee['department_name']): ?>
                    &nbsp;&bull;&nbsp;<?php echo hrm_e($employee['department_name']); ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Fields grid -->
    <div class="emp-profile-grid">

        <div class="emp-profile-field">
            <div class="emp-profile-label">Full Name</div>
            <div class="emp-profile-value"><?php echo hrm_e($employee['full_name'] ?: $employee['user_name'] ?: ($_SESSION['fullname'] ?? '—')); ?></div>
        </div>
        <div class="emp-profile-field">
            <div class="emp-profile-label">Username</div>
            <div class="emp-profile-value"><?php echo hrm_e($employee['user_login'] ?? ($_SESSION['username'] ?? '—')); ?></div>
        </div>
        <div class="emp-profile-field">
            <div class="emp-profile-label">Email</div>
            <div class="emp-profile-value"><?php echo hrm_e($employee['user_email'] ?? '—'); ?></div>
        </div>
        <div class="emp-profile-field">
            <div class="emp-profile-label">Phone</div>
            <div class="emp-profile-value"><?php echo hrm_e($employee['phone'] ?: '—'); ?></div>
        </div>
        <div class="emp-profile-field">
            <div class="emp-profile-label">Department</div>
            <div class="emp-profile-value"><?php echo hrm_e($employee['department_name'] ?: '—'); ?></div>
        </div>
        <div class="emp-profile-field">
            <div class="emp-profile-label">Designation</div>
            <div class="emp-profile-value"><?php echo hrm_e($employee['designation_name'] ?: $employee['user_desig'] ?: '—'); ?></div>
        </div>
        <div class="emp-profile-field">
            <div class="emp-profile-label">Employee Code</div>
            <div class="emp-profile-value"><?php echo hrm_e($employee['hrm_employee_code'] ?: $employee['device_employee_code'] ?: '—'); ?></div>
        </div>
        <div class="emp-profile-field">
            <div class="emp-profile-label">Join Date</div>
            <div class="emp-profile-value">
                <?php echo $employee['join_date'] ? hrm_e(date('d M Y', strtotime($employee['join_date']))) : '—'; ?>
            </div>
        </div>
        <div class="emp-profile-field">
            <div class="emp-profile-label">Status</div>
            <div class="emp-profile-value">
                <?php $st = $employee['hrm_status'] ?: 'active'; ?>
                <span class="emp-status emp-status--<?php echo hrm_e($st); ?>">
                    <span class="emp-status-dot"></span>
                    <?php echo hrm_e(ucfirst(str_replace('_', ' ', $st))); ?>
                </span>
            </div>
        </div>

    </div>
</div>

<?php endif; ?>

<!-- ── DataTable init ── -->
<script>
(function () {
    function init() {
        if (typeof $ === 'undefined' || typeof $.fn.DataTable === 'undefined') {
            return setTimeout(init, 50);
        }
        var $tbl = $('#employeeTable');
        if ($tbl.length && !$.fn.DataTable.isDataTable($tbl)) {
            $tbl.DataTable({
                pageLength: 10,
                responsive: true,
                order: [[0, 'asc']],
                language: {
                    search: '',
                    searchPlaceholder: 'Search employees…',
                    lengthMenu: 'Show _MENU_ rows',
                    info: 'Showing _START_–_END_ of _TOTAL_',
                    infoEmpty: 'No employees found',
                    emptyTable: 'No employees added yet',
                    paginate: { previous: '‹', next: '›' }
                },
                dom: '<"d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3"lf>rtip',
                columnDefs: [
                    { targets: 0, width: '32%' },
                    { targets: 4, orderable: false }
                ]
            });
        }
    }
    init();
})();
</script>

<?php
new_hrm_render_page_end();
?>