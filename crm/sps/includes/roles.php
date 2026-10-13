<?php
/*  ROLES & PERMISSIONS  ─  unified role engine
    -------------------------------------------------------------------------
    A single shared engine (include()'d) that powers the new "Roles &
    Permissions" system. It lets an administrator:

      • Manage ROLES            – create unlimited custom roles, edit or delete
        non-system ones. The built-in roles (Administrator, Manager, Employee,
        Internee, Restricted) act as the system baseline.

      • Grant PERMISSIONS       – a master dictionary of capabilities, grouped
        by module and category, that can be switched on/off per role.

      • Override per USER       – a specific user can be granted extra (or
        fewer) capabilities on top of their role's defaults.

    This engine is fully ADDITIVE: it creates its own tables and never drops or
    alters the legacy tables (dashboard_access / user_permissions / graph_
    permissions / users). It also ships a BACKWARD-COMPATIBLE SHIM that keeps
    feeding the existing $acc_* permission variables so the existing pages
    continue behaving exactly as before.
    ------------------------------------------------------------------------- */

/* ---------- 0. CONTEXT ------------------------------------------------ */
if (!isset($con) || !($con instanceof mysqli)) {
    include_once __DIR__ . '/db.php';
}

/* ---------- 1. PERMISSION DICTIONARY ----------------------------------
   Each capability:
      col  = the legacy DB column name (dashboard_access / user_permissions)
             that represents this capability when seeding/migrating. NULL means
             this capability has no legacy column (it is brand-new and defaults
             to 'No' until granted in the Roles UI).
      var  = the legacy $acc_<var> variable short name this capability maps to
             for the backward-compatible shim. NULL = no $acc_ counterpart.     */
define('ROLE_PERMISSION_DICTIONARY', array(
    'client' => array(
        'name' => 'Clients',
        'icon' => 'fas fa-id-card',
        'cats' => array(
            'General' => array(
                'client.view_list'     => array('label' => 'View client list',      'col' => 'view_clients_list',  'var' => 'view_client'),
                'client.view_detail'   => array('label' => 'View client detail',    'col' => 'client_detail_area', 'var' => 'detail_client'),
                'client.view_personal' => array('label' => 'View personal info',    'col' => 'view_personal',      'var' => 'view_personal'),
                'client.view_company'  => array('label' => 'View company info',     'col' => 'view_company',       'var' => 'view_company'),
                'client.add'           => array('label' => 'Add new client',        'col' => 'add_new_client',     'var' => 'add_client'),
                'client.edit'          => array('label' => 'Edit client',           'col' => null,                 'var' => null),
                'client.delete'        => array('label' => 'Delete client',         'col' => 'delete_client',      'var' => 'delete_client'),
                'client.toggle_active' => array('label' => 'Active / Inactive',     'col' => 'client_active',      'var' => 'client_active'),
            ),
        ),
    ),
    'invoice' => array(
        'name' => 'Invoices & Billing',
        'icon' => 'fas fa-file-invoice-dollar',
        'cats' => array(
            'Invoices' => array(
                'invoice.billing_area'  => array('label' => 'Billing area access',  'col' => 'client_billing_area','var' => 'billing_client'),
                'invoice.create'        => array('label' => 'Create invoice',       'col' => 'create_invoice',     'var' => null),
                'invoice.view'          => array('label' => 'View invoices',        'col' => 'view_invoices',      'var' => null),
                'invoice.edit'          => array('label' => 'Edit invoice',         'col' => 'invoice_and_billing','var' => 'invoice_and_billing'),
                'invoice.delete'        => array('label' => 'Delete invoice',       'col' => null,                 'var' => null),
                'invoice.send'          => array('label' => 'Send / email invoice', 'col' => null,                 'var' => null),
                'invoice.edit_service'            => array('label' => 'Add/remove services',       'col' => 'inv_edit_service',        'var' => null),
                'invoice.edit_service_description'=> array('label' => 'Edit service description',  'col' => 'inv_edit_service_desc',   'var' => null),
                'invoice.edit_service_amount'     => array('label' => 'Edit service price',        'col' => 'inv_edit_service_amount', 'var' => null),
                'invoice.edit_service_provider'   => array('label' => 'Edit service provider',     'col' => 'inv_edit_service_provider','var' => null),
                'invoice.edit_discount'           => array('label' => 'Edit discount',             'col' => 'inv_edit_discount',       'var' => null),
                'invoice.edit_due_date'           => array('label' => 'Edit due date',             'col' => 'inv_edit_due',            'var' => null),
                'invoice.edit_paid_amount'        => array('label' => 'Edit paid amount',          'col' => 'inv_edit_paid_amount',    'var' => null),
                'invoice.edit_paid_date'          => array('label' => 'Edit paid date',            'col' => 'inv_edit_paid_date',      'var' => null),
            ),
            'Estimates & Quotes' => array(
                'estimate.manage'     => array('label' => 'Estimates & quotations', 'col' => 'estimates_and_qutations', 'var' => 'estimates_and_qutations'),
            ),
            'Finance' => array(
                'finance.bank_card'   => array('label' => 'Bank / card area',      'col' => 'bank_card_area',      'var' => 'bank_card_area'),
            ),
        ),
    ),
    'project' => array(
        'name' => 'Projects',
        'icon' => 'fas fa-project-diagram',
        'cats' => array(
            'General' => array(
                'project.create'          => array('label' => 'Create project',         'col' => null,                 'var' => null),
                'project.edit'            => array('label' => 'Edit project',           'col' => 'edit_project',        'var' => null),
                'project.delete'          => array('label' => 'Delete project',         'col' => 'delete_project',      'var' => null),
                'project.mark_completed'  => array('label' => 'Mark completed',         'col' => 'mark_project_completed','var' => null),
                'project.reopen'          => array('label' => 'Reopen project',         'col' => 'reopen_project',      'var' => null),
                'project.assign'          => array('label' => 'Assign to staff',        'col' => 'assign_projects',     'var' => null),
                'project.view_all'        => array('label' => 'View all staff projects','col' => 'view_all_projects',   'var' => null),
                'project.create_invoice'  => array('label' => 'Create project invoice', 'col' => 'create_invoice',      'var' => null),
                'project.view_invoices'   => array('label' => 'View project invoices',  'col' => 'view_invoices',       'var' => null),
            ),
        ),
    ),
    'service' => array(
        'name' => 'Services',
        'icon' => 'fas fa-tags',
        'cats' => array(
            'Memberships' => array(
                'service.view'        => array('label' => 'View services',       'col' => 'view_products',       'var' => 'view_products'),
                'service.add_edit'    => array('label' => 'Add / edit services', 'col' => 'add_edit_products',   'var' => 'add_edit_products'),
                'service.delete'      => array('label' => 'Delete services',     'col' => 'delete_products',     'var' => 'delete_products'),
            ),
            'Product Cycles' => array(
                'service.cycle_view'     => array('label' => 'View product cycles', 'col' => 'view_product_cycle','var' => 'view_product'),
                'service.cycle_add_edit' => array('label' => 'Add / edit cycles',   'col' => 'add_edit_cycle',      'var' => 'add_edit_product'),
                'service.cycle_delete'   => array('label' => 'Delete cycles',       'col' => 'delete_cycle',        'var' => 'delete_product'),
            ),
        ),
    ),
    'staff' => array(
        'name' => 'Staff',
        'icon' => 'fas fa-user-tie',
        'cats' => array(
            'General' => array(
                'staff.view'          => array('label' => 'View staff',           'col' => 'view_staff',          'var' => 'view_staff'),
                'staff.add_edit'      => array('label' => 'Add / edit staff',     'col' => 'add_edit_staff',      'var' => 'add_edit_staff'),
                'staff.delete'        => array('label' => 'Delete staff',         'col' => 'delete_staff',        'var' => 'delete_staff'),
                'staff.manage_perms'  => array('label' => 'Manage roles & permissions', 'col' => null,           'var' => null),
            ),
        ),
    ),
    'form' => array(
        'name' => 'Entity Forms',
        'icon' => 'fas fa-clipboard-list',
        'cats' => array(
            'General' => array(
                'form.view'           => array('label' => 'View entity forms',    'col' => 'view_entity_form',    'var' => 'view_entity'),
                'form.add_edit'       => array('label' => 'Add / edit forms',     'col' => 'add_edit_form',       'var' => 'add_edit_entity'),
                'form.delete'         => array('label' => 'Delete forms',         'col' => 'delete_form',         'var' => 'delete_entity'),
            ),
        ),
    ),
    'reminder' => array(
        'name' => 'Reminders',
        'icon' => 'fas fa-calendar-alt',
        'cats' => array(
            'General' => array(
                'reminder.view'       => array('label' => 'View reminders',       'col' => null, 'var' => null),
                'reminder.add_edit'   => array('label' => 'Add / edit reminders', 'col' => null, 'var' => null),
                'reminder.delete'     => array('label' => 'Delete reminders',     'col' => null, 'var' => null),
            ),
        ),
    ),
    'plan' => array(
        'name' => 'Payment Plans',
        'icon' => 'fas fa-credit-card',
        'cats' => array(
            'General' => array(
                'plan.view'           => array('label' => 'View payment plans',   'col' => null, 'var' => null),
                'plan.add_edit'       => array('label' => 'Add / edit plans',     'col' => null, 'var' => null),
                'plan.delete'         => array('label' => 'Delete plans',         'col' => null, 'var' => null),
            ),
        ),
    ),
    'system' => array(
        'name' => 'System',
        'icon' => 'fas fa-cog',
        'cats' => array(
            'Administration' => array(
                'system.customizer'   => array('label' => 'Use the field Customizer','col' => null, 'var' => null),
                'system.roles'        => array('label' => 'Manage roles & permissions','col' => null,'var' => null),
            ),
        ),
    ),
    'ai' => array(
        'name' => 'AI Assistant',
        'icon' => 'fas fa-robot',
        'cats' => array(
            'General' => array(
                'ai.use'     => array('label' => 'Use AI chat & read data','col' => null, 'var' => null),
                'ai.write'   => array('label' => 'AI insert / create data','col' => null, 'var' => null),
            ),
        ),
    ),
));

/* Built-in roles seeded from the legacy user_type table. */
define('ROLE_BUILTIN_ROLES', array(
    1 => 'Administrator',
    2 => 'Manager',
    3 => 'Employee',
    4 => 'Internee',
    5 => 'Restricted',
));

/* The Administrator role is locked: it always has every capability. */
define('ROLE_ADMIN_ROLE_ID', 1);

/* ---------- 2. SELF-MIGRATING SCHEMA (runs once per request) ---------- */
if (!function_exists('roles_bootstrap')) {
    function roles_bootstrap($con)
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $con->query("CREATE TABLE IF NOT EXISTS `roles` (
            `role_id` INT(11) NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(100) NOT NULL,
            `description` VARCHAR(255) NOT NULL DEFAULT '',
            `is_system` TINYINT(1) NOT NULL DEFAULT 0,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_by` INT(11) NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NOT NULL DEFAULT current_timestamp(),
            `updated_by` INT(11) NOT NULL DEFAULT 0,
            `updated_at` TIMESTAMP NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`role_id`),
            UNIQUE KEY `uniq_name` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3");

        $con->query("CREATE TABLE IF NOT EXISTS `permissions` (
            `permission_id` INT(11) NOT NULL AUTO_INCREMENT,
            `permission_key` VARCHAR(64) NOT NULL,
            `module` VARCHAR(32) NOT NULL DEFAULT '',
            `category` VARCHAR(64) NOT NULL DEFAULT '',
            `label` VARCHAR(255) NOT NULL DEFAULT '',
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (`permission_id`),
            UNIQUE KEY `uniq_key` (`permission_key`),
            KEY `idx_module` (`module`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3");

        $con->query("CREATE TABLE IF NOT EXISTS `role_permission` (
            `role_id` INT(11) NOT NULL,
            `permission_key` VARCHAR(64) NOT NULL,
            `granted` ENUM('Yes','No') NOT NULL DEFAULT 'No',
            `updated_by` INT(11) NOT NULL DEFAULT 0,
            `updated_at` TIMESTAMP NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`role_id`,`permission_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3");

        $con->query("CREATE TABLE IF NOT EXISTS `user_role` (
            `user_id` INT(11) NOT NULL,
            `role_id` INT(11) NOT NULL,
            PRIMARY KEY (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3");

        $con->query("CREATE TABLE IF NOT EXISTS `user_permission_override` (
            `user_id` INT(11) NOT NULL,
            `permission_key` VARCHAR(64) NOT NULL,
            `granted` ENUM('Yes','No') NOT NULL DEFAULT 'No',
            `updated_by` INT(11) NOT NULL DEFAULT 0,
            `updated_at` TIMESTAMP NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`user_id`,`permission_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3");

        $con->query("CREATE TABLE IF NOT EXISTS `settings_marker` (
            `marker_key` VARCHAR(64) NOT NULL,
            `marker_value` VARCHAR(64) NOT NULL DEFAULT '',
            PRIMARY KEY (`marker_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3");
    }
}
roles_bootstrap($con);

/* Persistent "migration already done" flag, stored in settings_marker. */
if (!function_exists('roles_migration_done')) {
    function roles_migration_done($con, $set = null)
    {
        if ($set !== null) {
            $v = $con->real_escape_string((string) $set);
            $con->query("INSERT INTO settings_marker (marker_key, marker_value) VALUES ('roles_migration', '$v')
                         ON DUPLICATE KEY UPDATE marker_value='$v'");
            return;
        }
        $res = $con->query("SELECT marker_value FROM settings_marker WHERE marker_key='roles_migration'");
        if ($res && $row = $res->fetch_assoc()) {
            return $row['marker_value'] === '1';
        }
        return false;
    }
}

/* ---------- 3. SEEDING & MIGRATION ------------------------------------ */
if (!function_exists('roles_seed_dictionary')) {
    function roles_seed_dictionary($con)
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        foreach (ROLE_PERMISSION_DICTIONARY as $module => $mod) {
            foreach ($mod['cats'] as $cat => $perms) {
                foreach ($perms as $key => $p) {
                    $stmt = $con->prepare("INSERT IGNORE INTO permissions (permission_key, module, category, label, is_active) VALUES (?,?,?,?,1)");
                    if ($stmt) {
                        $stmt->bind_param('ssss', $key, $module, $cat, $p['label']);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            }
        }
    }
}

if (!function_exists('roles_seed_builtin_roles')) {
    function roles_seed_builtin_roles($con)
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        foreach (ROLE_BUILTIN_ROLES as $typeId => $name) {
            $stmt = $con->prepare("INSERT IGNORE INTO roles (role_id, name, description, is_system, is_active) VALUES (?,?,?,1,1)");
            if ($stmt) {
                $desc = 'Built-in role (user type ' . $typeId . ')';
                $stmt->bind_param('iss', $typeId, $name, $desc);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}

/* Map each user to a role the first time we run, using users.user_type. */
if (!function_exists('roles_seed_user_role')) {
    function roles_seed_user_role($con)
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $res = $con->query("SELECT user_id, user_type FROM users");
        if (!$res) {
            return;
        }
        while ($row = $res->fetch_assoc()) {
            $type = (int) $row['user_type'];
            if (!isset(ROLE_BUILTIN_ROLES[$type])) {
                $type = 5; // Restricted fallback
            }
            $stmt = $con->prepare("INSERT IGNORE INTO user_role (user_id, role_id) VALUES (?,?)");
            if ($stmt) {
                $stmt->bind_param('ii', $row['user_id'], $type);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}

/* Populate role_permission from the legacy dashboard_access table (role level),
   mapping each legacy 'col' value onto its capability key. Runs once. */
if (!function_exists('roles_seed_role_permission')) {
    function roles_seed_role_permission($con)
    {
        $res = $con->query("SELECT * FROM dashboard_access");
        if (!$res) {
            return;
        }
        $rows = array();
        while ($row = $res->fetch_assoc()) {
            $rows[$row['user_type']] = $row;
        }

        /* capability key -> legacy col name(s) */
        $colMap = array(); // col => [keys]
        foreach (ROLE_PERMISSION_DICTIONARY as $module => $mod) {
            foreach ($mod['cats'] as $cat => $perms) {
                foreach ($perms as $key => $p) {
                    if (!empty($p['col'])) {
                        $colMap[$p['col']][] = $key;
                    }
                }
            }
        }

        foreach ($rows as $type => $row) {
            if (!isset(ROLE_BUILTIN_ROLES[$type])) {
                continue;
            }
            $roleId = (int) $type;
            foreach ($colMap as $legacyCol => $keys) {
                if (!array_key_exists($legacyCol, $row)) {
                    continue;
                }
                $granted = (strcasecmp((string) $row[$legacyCol], 'Yes') === 0) ? 'Yes' : 'No';
                foreach ($keys as $k) {
                    $stmt = $con->prepare("INSERT INTO role_permission (role_id, permission_key, granted) VALUES (?,?,?)
                                           ON DUPLICATE KEY UPDATE granted=VALUES(granted)");
                    if ($stmt) {
                        $stmt->bind_param('iss', $roleId, $k, $granted);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            }
        }
        roles_migration_done($con, '1');
    }
}

/* Copy legacy per-user permission overrides into user_permission_override,
   mapping each legacy 'col' value onto its capability key. Runs once. */
if (!function_exists('roles_seed_user_override')) {
    function roles_seed_user_override($con)
    {
        $res = $con->query("SELECT * FROM user_permissions");
        if (!$res) {
            return;
        }
        $colMap = array(); // col => [keys]
        foreach (ROLE_PERMISSION_DICTIONARY as $module => $mod) {
            foreach ($mod['cats'] as $cat => $perms) {
                foreach ($perms as $key => $p) {
                    if (!empty($p['col'])) {
                        $colMap[$p['col']][] = $key;
                    }
                }
            }
        }
        while ($row = $res->fetch_assoc()) {
            $uid = (int) $row['user_id'];
            foreach ($colMap as $legacyCol => $keys) {
                if (!array_key_exists($legacyCol, $row)) {
                    continue;
                }
                $granted = (strcasecmp((string) $row[$legacyCol], 'Yes') === 0) ? 'Yes' : 'No';
                foreach ($keys as $k) {
                    $stmt = $con->prepare("INSERT INTO user_permission_override (user_id, permission_key, granted) VALUES (?,?,?)
                                           ON DUPLICATE KEY UPDATE granted=VALUES(granted)");
                    if ($stmt) {
                        $stmt->bind_param('iss', $uid, $k, $granted);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            }
        }
    }
}

/* Run all seeding steps. Idempotent; safe to call every request. */
if (!function_exists('roles_seed_all')) {
    function roles_seed_all($con)
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        roles_bootstrap($con);
        roles_seed_dictionary($con);
        roles_seed_builtin_roles($con);
        roles_seed_user_role($con);
        if (!roles_migration_done($con)) {
            roles_seed_role_permission($con);
            roles_seed_user_override($con);
        }
    }
}
roles_seed_all($con);

/* ---------- 4. READ HELPERS ------------------------------------------- */
if (!function_exists('roles_all_roles')) {
    function roles_all_roles($con)
    {
        $out = array();
        $res = $con->query("SELECT * FROM roles ORDER BY is_system DESC, role_id ASC");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $out[] = $row;
            }
        }
        return $out;
    }
}

if (!function_exists('roles_permissions_for_role')) {
    function roles_permissions_for_role($con, $roleId)
    {
        $out = array();
        $roleId = (int) $roleId;
        $res = $con->query("SELECT permission_key, granted FROM role_permission WHERE role_id='$roleId'");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $out[$row['permission_key']] = $row['granted'];
            }
        }
        return $out;
    }
}

if (!function_exists('roles_user_role_id')) {
    function roles_user_role_id($con, $userId)
    {
        $userId = (int) $userId;
        $res = $con->query("SELECT role_id FROM user_role WHERE user_id='$userId'");
        if ($res && $row = $res->fetch_assoc()) {
            return (int) $row['role_id'];
        }
        return 0;
    }
}

/* Resolve the merged set of granted capabilities for a user:
   role permissions (role defaults) UNION per-user overrides.
   The Administrator role always gets everything. */
if (!function_exists('roles_user_caps')) {
    function roles_user_caps($con, $userId)
    {
        $userId = (int) $userId;
        $caps = array();

        $roleId = roles_user_role_id($con, $userId);
        if ($roleId === ROLE_ADMIN_ROLE_ID) {
            foreach (ROLE_PERMISSION_DICTIONARY as $module => $mod) {
                foreach ($mod['cats'] as $cat => $perms) {
                    foreach ($perms as $key => $p) {
                        $caps[$key] = 'Yes';
                    }
                }
            }
            return $caps;
        }

        $rolePerms = $roleId ? roles_permissions_for_role($con, $roleId) : array();
        foreach (ROLE_PERMISSION_DICTIONARY as $module => $mod) {
            foreach ($mod['cats'] as $cat => $perms) {
                foreach ($perms as $key => $p) {
                    $caps[$key] = (($rolePerms[$key] ?? 'No') === 'Yes') ? 'Yes' : 'No';
                }
            }
        }

        /* Apply per-user overrides. */
        $res = $con->query("SELECT permission_key, granted FROM user_permission_override WHERE user_id='$userId'");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                if (isset($caps[$row['permission_key']])) {
                    $caps[$row['permission_key']] = $row['granted'];
                }
            }
        }
        return $caps;
    }
}

/* ---------- 5. MANAGEMENT HELPERS (used by manage_roles.php) ---------- */

/* Create a new custom role. Returns array(success, message). */
if (!function_exists('roles_create')) {
    function roles_create($con, $name, $description = '')
    {
        $name = trim((string) $name);
        if ($name === '') {
            return array('success' => false, 'message' => 'Please provide a role name.');
        }
        $uname = $con->real_escape_string($name);
        $res = $con->query("SELECT role_id FROM roles WHERE name='$uname'");
        if ($res && $res->num_rows() > 0) {
            return array('success' => false, 'message' => 'A role with that name already exists.');
        }
        $desc = $con->real_escape_string($description);
        $uid  = (int) ($_SESSION['user_id'] ?? 0);
        $stmt = $con->prepare("INSERT INTO roles (name, description, is_system, is_active, created_by, updated_by) VALUES (?,?,0,1,?,?)");
        if (!$stmt) {
            return array('success' => false, 'message' => 'Prepared statement failed.');
        }
        $stmt->bind_param('ssii', $name, $desc, $uid, $uid);
        if ($stmt->execute()) {
            $id = (int) $con->insert_id;
            $stmt->close();
            /* Grant nothing by default; admin can tick permissions. */
            return array('success' => true, 'message' => 'Role "' . $name . '" created.', 'id' => $id);
        }
        $err = $stmt->error;
        $stmt->close();
        return array('success' => false, 'message' => 'Could not create role: ' . $err);
    }
}

/* Rename / update a role. System roles are editable for name/description but
   the Administrator role is locked. Returns array(success, message). */
if (!function_exists('roles_update')) {
    function roles_update($con, $roleId, $name, $description = '')
    {
        $roleId = (int) $roleId;
        $name = trim((string) $name);
        if ($roleId <= 0 || $name === '') {
            return array('success' => false, 'message' => 'Invalid role or empty name.');
        }
        if ($roleId === ROLE_ADMIN_ROLE_ID) {
            return array('success' => false, 'message' => 'The Administrator role cannot be renamed.');
        }
        $uid = (int) ($_SESSION['user_id'] ?? 0);
        $desc = $con->real_escape_string($description);
        $stmt = $con->prepare("UPDATE roles SET name=?, description=?, updated_by=? WHERE role_id=?");
        if (!$stmt) {
            return array('success' => false, 'message' => 'Prepared statement failed.');
        }
        $stmt->bind_param('ssii', $name, $desc, $uid, $roleId);
        if ($stmt->execute()) {
            $stmt->close();
            return array('success' => true, 'message' => 'Role updated.');
        }
        $err = $stmt->error;
        $stmt->close();
        return array('success' => false, 'message' => 'Could not update role: ' . $err);
    }
}

/* Delete a non-system role (and its permission matrix). Users assigned to it
   are moved to the Restricted role. Returns array(success, message). */
if (!function_exists('roles_delete')) {
    function roles_delete($con, $roleId)
    {
        $roleId = (int) $roleId;
        if ($roleId <= 0 || $roleId === ROLE_ADMIN_ROLE_ID) {
            return array('success' => false, 'message' => 'The Administrator role cannot be deleted.');
        }
        $res = $con->query("SELECT is_system FROM roles WHERE role_id='$roleId'");
        $row = $res ? $res->fetch_assoc() : null;
        if (!$row) {
            return array('success' => false, 'message' => 'Role not found.');
        }
        if ((int) $row['is_system'] === 1) {
            return array('success' => false, 'message' => 'Built-in roles cannot be deleted.');
        }
        $con->query("UPDATE user_role SET role_id=5 WHERE role_id='$roleId'");
        $con->query("DELETE FROM role_permission WHERE role_id='$roleId'");
        $con->query("DELETE FROM roles WHERE role_id='$roleId'");
        return array('success' => true, 'message' => 'Role deleted; its users moved to Restricted.');
    }
}

/* Replace a role's entire permission matrix from an associative array
   permission_key => 'Yes'|'No'. Returns array(success, message). */
if (!function_exists('roles_save_permissions')) {
    function roles_save_permissions($con, $roleId, array $granted)
    {
        $roleId = (int) $roleId;
        $uid = (int) ($_SESSION['user_id'] ?? 0);
        /* Validate the role exists. */
        $res = $con->query("SELECT role_id FROM roles WHERE role_id='$roleId'");
        if (!$res || $res->num_rows() === 0) {
            return array('success' => false, 'message' => 'Role not found.');
        }
        /* Only write keys that exist in the dictionary. */
        $known = array();
        foreach (ROLE_PERMISSION_DICTIONARY as $module => $mod) {
            foreach ($mod['cats'] as $cat => $perms) {
                foreach ($perms as $key => $p) {
                    $known[$key] = 1;
                }
            }
        }
        $stmt = $con->prepare("INSERT INTO role_permission (role_id, permission_key, granted, updated_by) VALUES (?,?,?,?)
                               ON DUPLICATE KEY UPDATE granted=VALUES(granted), updated_by=VALUES(updated_by)");
        if (!$stmt) {
            return array('success' => false, 'message' => 'Prepared statement failed.');
        }
        foreach ($known as $key => $_) {
            $val = (($granted[$key] ?? '') === 'Yes') ? 'Yes' : 'No';
            $stmt->bind_param('issi', $roleId, $key, $val, $uid);
            $stmt->execute();
        }
        $stmt->close();
        return array('success' => true, 'message' => 'Permissions saved for role.');
    }
}

/* Fetch the number of users assigned to a role (for the UI). */
if (!function_exists('roles_user_count')) {
    function roles_user_count($con, $roleId)
    {
        $roleId = (int) $roleId;
        $res = $con->query("SELECT COUNT(*) AS c FROM user_role WHERE role_id='$roleId'");
        if ($res && $row = $res->fetch_assoc()) {
            return (int) $row['c'];
        }
        return 0;
    }
}

/* ---------- 6. BACKWARD-COMPATIBLE SHIM ------------------------------- */
/* Resolve a user's capabilities and produce:
      $legacy -> keyed array of legacy $acc_* values to extract() so existing
                 pages keep working. Also registers crm_can()/crm_can_any().   */
if (!function_exists('roles_shim')) {
    function roles_shim($con, $userId, $usertype)
    {
        $caps = roles_user_caps($con, $userId);
        $GLOBALS['CRM_CAPS'] = $caps;
        $GLOBALS['CRM_ROLE_ID'] = roles_user_role_id($con, $userId);

        if (!function_exists('crm_can')) {
            function crm_can($key)
            {
                $caps = $GLOBALS['CRM_CAPS'] ?? array();
                return (($caps[$key] ?? 'No') === 'Yes');
            }
        }
        if (!function_exists('crm_can_any')) {
            function crm_can_any(array $keys)
            {
                foreach ($keys as $k) {
                    if (crm_can($k)) {
                        return true;
                    }
                }
                return false;
            }
        }

        /* Every $acc_* short name the legacy pages read. */
        $legacyVars = array(
            'add_client', 'view_client', 'detail_client', 'view_personal', 'view_company',
            'billing_client', 'delete_client', 'client_active',
            'view_products', 'add_edit_products', 'delete_products',
            'view_staff', 'add_edit_staff', 'delete_staff',
            'view_entity', 'add_edit_entity', 'delete_entity',
            'view_product', 'add_edit_product', 'delete_product',
            'invoice_and_billing', 'estimates_and_qutations', 'bank_card_area',
        );

        /* var -> [cap keys that set it] */
        $varMap = array();
        foreach (ROLE_PERMISSION_DICTIONARY as $module => $mod) {
            foreach ($mod['cats'] as $cat => $perms) {
                foreach ($perms as $key => $p) {
                    if (!empty($p['var'])) {
                        foreach ((array) $p['var'] as $v) {
                            $varMap[$v][] = $key;
                        }
                    }
                }
            }
        }

        $legacy = array();
        foreach ($legacyVars as $var) {
            $val = 'No';
            if (isset($varMap[$var])) {
                foreach ($varMap[$var] as $capKey) {
                    if (($caps[$capKey] ?? 'No') === 'Yes') {
                        $val = 'Yes';
                        break;
                    }
                }
            }
            $legacy['acc_' . $var] = $val;
        }
        return $legacy;
    }
}
