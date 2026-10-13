<?php
/*  CUSTOMIZER  ─  Unified field & dashboard customizer engine              
    -------------------------------------------------------------------------
    A single shared engine (include()'d) that powers the new "Customizer"
    section of the CRM. It lets an administrator:

      • Edit EXISTING (system) fields   – rename the label, change the input
        type, cap the max length (e.g. phone digit limit), and mark a field
        Required or Optional. System fields keep their physical DB column;
        changes are applied at render time via an "override" layer so nothing
        in the existing schema or existing queries is disturbed.

      • Add NEW (custom) fields         – arbitrary text / email / integer /
        date / phone / textarea fields. Custom field values are stored in a
        generic EAV-style table (customizer_field_value), never by altering
        the host module's table.

      • Hide / Show fields              – at the module level (all client
        types at once) in addition to the pre-existing per-client-type
        visibility handled by client_type_manual.php.

      • Audit everything                – every create / update / delete of a
        record's fields is logged to customizer_audit_log with the acting
        user's id & name, old and new values, and a timestamp.

    This engine is module-aware but only the "clients" module is wired in
    Phase 1. Other modules (projects, invoices, staff, reminders, payment
    plans) register against the same tables through the functions below.
    ------------------------------------------------------------------------- */

/* ---------- 0. CONTEXT ----------
   Reuse an existing DB connection when included by a page that already has
   one, otherwise set up our own. */
if (!isset($con) || !($con instanceof mysqli)) {
    include_once __DIR__ . '/db.php';
}

/* ---------- 1. SELF-MIGRATING SCHEMA (runs once per request) ---------- */
if (!function_exists('customizer_bootstrap')) {
    function customizer_bootstrap($con)
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        /*
        1) customizer_fields        – the master catalog of every field
           (system + custom) for every module. System fields are seeded from
           each module's catalog; custom fields are created by the admin.

           field_key     unique key within the module (e.g. 'cell_ph' or a
                         generated slug for custom fields)
           module        'clients' | 'projects' | ...
           label         display label
           field_type    text | email | integer | date | phone | textarea
           max_length    max chars / digits (0 = unlimited)
           is_required   1 required | 0 optional
           is_system     1 seeded system field | 0 admin-created field
           section       which section/group the field belongs to
           sort_order    display ordering within the section
           status        1 active | 0 hidden/disabled
        */
        $con->query("CREATE TABLE IF NOT EXISTS `customizer_fields` (
            `field_id` INT(11) NOT NULL AUTO_INCREMENT,
            `module` VARCHAR(32) NOT NULL,
            `field_key` VARCHAR(64) NOT NULL,
            `label` VARCHAR(255) NOT NULL DEFAULT '',
            `field_type` VARCHAR(16) NOT NULL DEFAULT 'text',
            `max_length` INT(11) NOT NULL DEFAULT 0,
            `is_required` TINYINT(1) NOT NULL DEFAULT 0,
            `is_system` TINYINT(1) NOT NULL DEFAULT 1,
            `section` VARCHAR(64) NOT NULL DEFAULT '',
            `section_order` INT(11) NOT NULL DEFAULT 0,
            `sort_order` INT(11) NOT NULL DEFAULT 0,
            `status` TINYINT(1) NOT NULL DEFAULT 1,
            `created_by` INT(11) NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NOT NULL DEFAULT current_timestamp(),
            `updated_by` INT(11) NOT NULL DEFAULT 0,
            `updated_at` TIMESTAMP NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`field_id`),
            UNIQUE KEY `uniq_module_key` (`module`,`field_key`),
            KEY `idx_module` (`module`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3");

        /*
        2) customizer_field_value – EAV-style value store for CUSTOM fields.
           module_record_id = the host row's primary key (e.g. members.member_id).
        */
        $con->query("CREATE TABLE IF NOT EXISTS `customizer_field_value` (
            `value_id` INT(11) NOT NULL AUTO_INCREMENT,
            `module` VARCHAR(32) NOT NULL,
            `module_record_id` INT(11) NOT NULL,
            `field_key` VARCHAR(64) NOT NULL,
            `field_value` TEXT NULL,
            `updated_by` INT(11) NOT NULL DEFAULT 0,
            `updated_at` TIMESTAMP NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`value_id`),
            UNIQUE KEY `uniq_rec_field` (`module`,`module_record_id`,`field_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3");

        /*
        3) customizer_audit_log – who did what, when, on which record.
        */
        $con->query("CREATE TABLE IF NOT EXISTS `customizer_audit_log` (
            `audit_id` INT(11) NOT NULL AUTO_INCREMENT,
            `module` VARCHAR(32) NOT NULL,
            `module_record_id` INT(11) NOT NULL DEFAULT 0,
            `field_key` VARCHAR(64) NOT NULL DEFAULT '',
            `field_label` VARCHAR(255) NOT NULL DEFAULT '',
            `action` ENUM('created','updated','deleted','field_created','field_updated','field_hidden','field_shown') NOT NULL,
            `old_value` TEXT NULL,
            `new_value` TEXT NULL,
            `user_id` INT(11) NOT NULL DEFAULT 0,
            `user_name` VARCHAR(255) NOT NULL DEFAULT '',
            `created_at` TIMESTAMP NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`audit_id`),
            KEY `idx_module_rec` (`module`,`module_record_id`),
            KEY `idx_action` (`action`),
            KEY `idx_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3");
    }
}
customizer_bootstrap($con);

/* ---------- 2. MODULE REGISTRY -----------------------------------------
   Each module that uses the customizer registers a catalog of its SYSTEM
   fields so the engine (and the customizer.php UI) knows about them.

   Each field:
     key        field_key
     label      default label
     type       default input type
     section    human section name (used to group in the UI)
   custom_field  ??? reserved
*/
define('CUSTOMIZER_MODULES', array(
    'clients' => array(
        'name'   => 'Clients',
        'icon'   => 'fas fa-id-card',
        'fields' => array(
            'client_id'  => array('label' => 'Client ID',      'type' => 'text',  'section' => 'Identity'),
            'full_name'  => array('label' => 'Full Name',      'type' => 'text',  'section' => 'Identity'),
            'name'       => array('label' => 'First Name',     'type' => 'text',  'section' => 'Identity'),
            'mname'      => array('label' => 'Middle Name',    'type' => 'text',  'section' => 'Identity'),
            'lname'      => array('label' => 'Last Name',      'type' => 'text',  'section' => 'Identity'),
            'dob'        => array('label' => 'Date of Birth',  'type' => 'date',  'section' => 'Identity'),
            'ss'         => array('label' => 'SSN',            'type' => 'text',  'section' => 'Identity'),
            'cell_ph'    => array('label' => 'Cell Phone',     'type' => 'phone', 'section' => 'Contact'),
            'prm_email'  => array('label' => 'Primary Email',  'type' => 'email', 'section' => 'Contact'),
            'sec_email'  => array('label' => 'Business Email', 'type' => 'email', 'section' => 'Contact'),
            'phone'      => array('label' => 'Work Phone',     'type' => 'phone', 'section' => 'Contact'),
            'other_ph'   => array('label' => 'Other Phone',    'type' => 'phone', 'section' => 'Contact'),
            'overseas_ph' => array('label' => 'Overseas Phone', 'type' => 'phone','section' => 'Contact'),
            'fax'        => array('label' => 'Fax',            'type' => 'text',  'section' => 'Contact'),
            'hstreet'    => array('label' => 'Home Street',    'type' => 'text',  'section' => 'Home Address'),
            'hcity'      => array('label' => 'Home City',      'type' => 'text',  'section' => 'Home Address'),
            'hstate'     => array('label' => 'Home State',     'type' => 'text',  'section' => 'Home Address'),
            'hzip'       => array('label' => 'Home Zip',       'type' => 'text',  'section' => 'Home Address'),
            'hcounty'    => array('label' => 'Home County',    'type' => 'text',  'section' => 'Home Address'),
            'hapartment' => array('label' => 'Home Apartment', 'type' => 'text',  'section' => 'Home Address'),
            'bus_name'   => array('label' => 'Business Name',  'type' => 'text',  'section' => 'Business'),
            'dba'        => array('label' => 'DBA',            'type' => 'text',  'section' => 'Business'),
            'ein'        => array('label' => 'EIN',            'type' => 'text',  'section' => 'Business'),
            'tf'         => array('label' => 'PMB',            'type' => 'text',  'section' => 'Business'),
            'pmb'        => array('label' => 'PMB (2)',        'type' => 'text',  'section' => 'Business'),
            'file'       => array('label' => 'File #',         'type' => 'text',  'section' => 'Business'),
            'client_type' => array('label' => 'Client Type',   'type' => 'text',  'section' => 'Business'),
            'category'   => array('label' => 'Category',       'type' => 'text',  'section' => 'Business'),
            'member_pos' => array('label' => 'Designation',    'type' => 'text',  'section' => 'Business'),
            'bus_start_date' => array('label' => 'Start Date', 'type' => 'date',  'section' => 'Business'),
            'incorp_date' => array('label' => 'Incorp. Date',  'type' => 'date',  'section' => 'Business'),
            'occupation' => array('label' => 'Occupation',     'type' => 'text',  'section' => 'Business'),
            'idcard'     => array('label' => 'ID Card',        'type' => 'text',  'section' => 'Business'),
            'incorpstate' => array('label' => 'Incorp. State', 'type' => 'text',  'section' => 'Business'),
            'client_since' => array('label' => 'Client Since', 'type' => 'date',  'section' => 'Business'),
            'address'    => array('label' => 'Business Street','type' => 'text',  'section' => 'Business Address'),
            'city'       => array('label' => 'Business City',  'type' => 'text',  'section' => 'Business Address'),
            'state'      => array('label' => 'Business State', 'type' => 'text',  'section' => 'Business Address'),
            'zip'        => array('label' => 'Business Zip',   'type' => 'text',  'section' => 'Business Address'),
            'bcounty'    => array('label' => 'Business County','type' => 'text',  'section' => 'Business Address'),
            'business_address' => array('label' => 'Business Address Full', 'type' => 'textarea', 'section' => 'Business Address'),
            'added_by'   => array('label' => 'Added By',       'type' => 'text',  'section' => 'Account'),
            'updated_by' => array('label' => 'Updated By',     'type' => 'text',  'section' => 'Account'),
            'date_added' => array('label' => 'Date Added',     'type' => 'date',  'section' => 'Account'),
            'dateupdated' => array('label' => 'Date Updated',  'type' => 'date',  'section' => 'Account'),
        ),
    ),
    'projects' => array(
        'name'   => 'Projects',
        'icon'   => 'fas fa-project-diagram',
        'fields' => array(
            'order_id'  => array('label' => 'Project ID',   'type' => 'text',  'section' => 'Identity'),
            'project_title'  => array('label' => 'Title',    'type' => 'text',  'section' => 'Identity'),
            'project_type'   => array('label' => 'Type',     'type' => 'text',  'section' => 'Identity'),
            'project_status' => array('label' => 'Status',   'type' => 'text',  'section' => 'Identity'),
            'mem_id'         => array('label' => 'Client',   'type' => 'text',  'section' => 'Assignment'),
            'assign_to'      => array('label' => 'Assignee', 'type' => 'text',  'section' => 'Assignment'),
            'prod_id'        => array('label' => 'Product',  'type' => 'text',  'section' => 'Assignment'),
            'start_date'     => array('label' => 'Start Date', 'type' => 'date','section' => 'Dates'),
            'exp_date'       => array('label' => 'Due Date', 'type' => 'date',  'section' => 'Dates'),
            'project_description' => array('label' => 'Description', 'type' => 'textarea', 'section' => 'Details'),
            'project_created_at' => array('label' => 'Created At', 'type' => 'date', 'section' => 'Audit'),
            'project_updated_at' => array('label' => 'Updated At', 'type' => 'date', 'section' => 'Audit'),
        ),
    ),
));

/* ---------- 3. SYSTEM FIELD SEEDING ------------------------------------
   Ensures every registered system field has a row in customizer_fields so
   the UI can list & edit them uniformly alongside custom fields. This is
   additive and idempotent. */
if (!function_exists('customizer_seed_system_fields')) {
    function customizer_seed_system_fields($con)
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        if (!defined('CUSTOMIZER_MODULES')) {
            return;
        }
        $all = CUSTOMIZER_MODULES;
        foreach ($all as $module => $cfg) {
            $i = 0;
            foreach ($cfg['fields'] as $key => $f) {
                $stmt = $con->prepare(
                    "INSERT IGNORE INTO customizer_fields
                        (module, field_key, label, field_type, is_required, is_system, section, sort_order, status)
                     VALUES (?,?,?,?,?,1,?,?,1)"
                );
                if (!$stmt) {
                    continue;
                }
                $sec = $f['section'] ?? '';
                $req = (in_array($key, array('name', 'lname', 'cell_ph', 'prm_email', 'membertype')) || ($f['required'] ?? false)) ? 1 : 0;
                $stmt->bind_param('ssssisi', $module, $key, $f['label'], $f['type'], $req, $sec, $i);
                $stmt->execute();
                $stmt->close();
                $i++;
            }
        }
    }
}
customizer_seed_system_fields($con);

/* ---------- 4. READ HELPERS ------------------------------------------- */

/* Merge the customizer overrides / custom fields for a module into a single
   associative catalog keyed by field_key. Includes system fields (with any
   admin edits applied live) PLUS custom fields. */
if (!function_exists('customizer_fields_for_module')) {
    function customizer_fields_for_module($con, $module)
    {
        $out = array();
        $res = $con->query(
            "SELECT field_key, label, field_type, max_length, is_required, is_system, section, sort_order, status
             FROM customizer_fields WHERE module = '" . $con->real_escape_string($module) . "' ORDER BY is_system DESC, sort_order ASC, field_id ASC"
        );
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $out[$row['field_key']] = array(
                    'label'       => $row['label'],
                    'field_type'  => $row['field_type'],
                    'max_length'  => (int) $row['max_length'],
                    'is_required' => (int) $row['is_required'],
                    'is_system'   => (int) $row['is_system'],
                    'section'     => $row['section'],
                    'sort_order'  => (int) $row['sort_order'],
                    'status'      => (int) $row['status'],
                );
            }
        }
        return $out;
    }
}

/* Only the CUSTOM (admin-created) fields for a module. */
if (!function_exists('customizer_custom_fields')) {
    function customizer_custom_fields($con, $module)
    {
        $all = customizer_fields_for_module($con, $module);
        $custom = array();
        foreach ($all as $k => $f) {
            if (!$f['is_system']) {
                $custom[$k] = $f;
            }
        }
        return $custom;
    }
}

/* Parse the custom field values from $_POST (name = cust_<field_key>). */
if (!function_exists('customizer_extract_custom_values')) {
    function customizer_extract_custom_values($customFields)
    {
        $vals = array();
        foreach ($customFields as $key => $f) {
            $p = $_POST['cust_' . $key] ?? '';
            if (is_array($p)) {
                $p = implode(', ', $p);
            }
            $vals[$key] = trim((string) $p);
        }
        return $vals;
    }
}

/* Persist a set of [field_key => value] for one module record. Only custom
   (non-system) keys are stored here. Returns count saved. */
if (!function_exists('customizer_save_record_values')) {
    function customizer_save_record_values($con, $module, $recordId, $values, $userId = 0, $userName = '')
    {
        $module = $con->real_escape_string($module);
        $recordId = (int) $recordId;
        $saved = 0;
        foreach ($values as $key => $val) {
            $key = $con->real_escape_string($key);
            $val = (string) $val;
            $stmt = $con->prepare(
                "INSERT INTO customizer_field_value (module, module_record_id, field_key, field_value, updated_by)
                 VALUES (?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE field_value=VALUES(field_value), updated_by=VALUES(updated_by)"
            );
            if (!$stmt) {
                continue;
            }
            $stmt->bind_param('sissi', $module, $recordId, $key, $val, $userId);
            if ($stmt->execute()) {
                $saved++;
                customizer_audit($con, $module, $recordId, $key, '', '', 'created', $userId, $userName, null, $val);
            }
            $stmt->close();
        }
        return $saved;
    }
}

/* Load the saved custom field values for one module record as [key => value]. */
if (!function_exists('customizer_record_values')) {
    function customizer_record_values($con, $module, $recordId)
    {
        $out = array();
        $stmt = $con->prepare("SELECT field_key, field_value FROM customizer_field_value WHERE module=? AND module_record_id=?");
        if ($stmt) {
            $stmt->bind_param('si', $module, (int) $recordId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $out[$row['field_key']] = $row['field_value'];
            }
            $stmt->close();
        }
        return $out;
    }
}

/* ---------- 5. RENDER HELPERS ----------------------------------------- */

/* Render the input HTML for a single custom field inside a Bootstrap col.
   $value is the existing value (for edit forms). */
if (!function_exists('customizer_render_custom_field')) {
    function customizer_render_custom_field($con, $fieldKey, $field, $value = '')
    {
        $name   = 'cust_' . $fieldKey;
        $id     = 'cust_' . $fieldKey;
        $label  = htmlspecialchars($field['label'] !== '' ? $field['label'] : ucfirst($fieldKey));
        $type   = $field['field_type'];
        $max    = (int) $field['max_length'];
        $req    = (int) $field['is_required'] === 1;
        $value  = htmlspecialchars((string) $value);
        $reqAttr  = $req ? ' required="required" data-cpc-was-required="1"' : '';
        $maxAttr  = $max > 0 ? ' maxlength="' . $max . '"' : '';
        $requiredStar = $req ? '<span class="text-danger">*</span>' : '';

        $input = '';
        if ($type === 'textarea') {
            $input = '<textarea class="form-control" name="' . $name . '" id="' . $id . '"' . $reqAttr . $maxAttr . ' rows="3">' . $value . '</textarea>';
        } elseif ($type === 'integer') {
            $input = '<input class="form-control" name="' . $name . '" id="' . $id . '" type="number" value="' . $value . '" min="0" step="1"' . $reqAttr . $maxAttr . '>';
        } elseif ($type === 'phone') {
            $input = '<input class="form-control" name="' . $name . '" id="' . $id . '" type="tel" value="' . $value . '"' . $reqAttr . $maxAttr . '>';
        } else {
            $inpType = in_array($type, array('text', 'email', 'date'), true) ? $type : 'text';
            $input = '<input class="form-control" name="' . $name . '" id="' . $id . '" type="' . $inpType . '" value="' . $value . '"' . $reqAttr . $maxAttr . '>';
        }

        return '<div class="col-md-4" data-cpc-field="' . htmlspecialchars($fieldKey) . '">
                    <div class="form-group">
                        <label for="' . $id . '" class="form-label">' . $label . '</label>' . $requiredStar . '
                        ' . $input . '
                    </div>
                </div>';
    }
}

/* Render all custom fields for a module as a wrapped block (used inside the
   add/edit forms). */
if (!function_exists('customizer_render_custom_fields_block')) {
    function customizer_render_custom_fields_block($con, $module, $recordValues = array())
    {
        $custom = customizer_custom_fields($con, $module);
        if (empty($custom)) {
            return '';
        }
        $html = '<div class="col-md-12 mt-3 customizer-custom-block"><h6><i class="fas fa-sliders-h"></i> Custom Fields</h6></div>';
        foreach ($custom as $key => $f) {
            if (!$f['status']) {
                continue;
            }
            $html .= customizer_render_custom_field($con, $key, $f, $recordValues[$key] ?? '');
        }
        return $html;
    }
}

/* Render the CUSTOM fields for the client profile VIEW (client_details.php). */
if (!function_exists('customizer_render_profile_custom')) {
    function customizer_render_profile_custom($con, $module, $recordId, $visible = null)
    {
        $custom = customizer_custom_fields($con, $module);
        if (empty($custom)) {
            return '';
        }
        $vals = customizer_record_values($con, $module, $recordId);
        $rows = '';
        foreach ($custom as $key => $f) {
            if (!$f['status']) {
                continue;
            }
            if ($visible !== null && isset($visible[$key]) && empty($visible[$key])) {
                continue;
            }
            $v = trim((string) ($vals[$key] ?? ''));
            if ($v === '') {
                continue;
            }
            $rows .= '<div class="pd-field"><span class="pd-label">' . htmlspecialchars($f['label'] !== '' ? $f['label'] : $key)
                . '</span><span class="pd-value">' . htmlspecialchars($v) . '</span></div>';
        }
        if ($rows !== '') {
            $rows = '<div class="pd-group-label mt-3"><i class="fas fa-sliders-h"></i> Custom Fields</div>' . $rows;
        }
        return $rows;
    }
}

/* ---------- 6. AUDIT LOG ----------------------------------------------- */
if (!function_exists('customizer_audit')) {
    function customizer_audit($con, $module, $recordId, $fieldKey, $fieldLabel, $action, $userId = 0, $userName = '', $oldValue = null, $newValue = null)
    {
        $module = $con->real_escape_string($module);
        $fieldKey = $con->real_escape_string($fieldKey);
        $fieldLabel = $con->real_escape_string($fieldLabel);
        $action = $con->real_escape_string($action);
        if ($userId <= 0 && isset($_SESSION['user_id'])) {
            $userId = (int) $_SESSION['user_id'];
        }
        if ($userName === '' && isset($_SESSION['fullname'])) {
            $userName = $_SESSION['fullname'];
        }
        $userName = $con->real_escape_string($userName);
        $oldValue = $oldValue === null ? null : $con->real_escape_string((string) $oldValue);
        $newValue = $newValue === null ? null : $con->real_escape_string((string) $newValue);
        $stmt = $con->prepare(
            "INSERT INTO customizer_audit_log
                (module, module_record_id, field_key, field_label, action, old_value, new_value, user_id, user_name)
             VALUES (?,?,?,?,?,?,?,?,?)"
        );
        if ($stmt) {
            $stmt->bind_param('sisssssis', $module, (int) $recordId, $fieldKey, $fieldLabel, $action, $oldValue, $newValue, $userId, $userName);
            $stmt->execute();
            $stmt->close();
        }
    }
}

/* ---------- 7. FIELD MANAGEMENT (used by customizer.php) -------------- */

/* Create a new custom field. Returns array(success, message). */
if (!function_exists('customizer_add_field')) {
    function customizer_add_field($con, $module, $label, $type, $maxLength, $required, $section)
    {
        $module = $con->real_escape_string($module);
        $label  = trim((string) $label);
        $type   = in_array($type, array('text', 'email', 'integer', 'date', 'phone', 'textarea'), true) ? $type : 'text';
        $maxLength = (int) $maxLength;
        if ($maxLength < 0) {
            $maxLength = 0;
        }
        $required = $required ? 1 : 0;
        $section  = trim((string) $section);
        if ($label === '') {
            return array('success' => false, 'message' => 'Please provide a field name.');
        }

        // Slug key unique to the module.
        $base = strtolower(preg_replace('/[^A-Za-z0-9]+/', '_', $label));
        $base = trim($base, '_');
        if ($base === '') {
            $base = 'custom_field';
        }
        $base = substr($base, 0, 48) . '_' . bin2hex(random_bytes(3)); // guarantee uniqueness

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $userName = $_SESSION['fullname'] ?? '';

        $stmt = $con->prepare(
            "INSERT INTO customizer_fields (module, field_key, label, field_type, max_length, is_required, is_system, section, status, created_by, updated_by)
             VALUES (?,?,?,?,?,?,0,?,1,?,?)"
        );
        if (!$stmt) {
            return array('success' => false, 'message' => 'Prepared statement failed.');
        }
        $stmt->bind_param('ssssiisii', $module, $base, $label, $type, $maxLength, $required, $section, $userId, $userId);
        if ($stmt->execute()) {
            customizer_audit($con, $module, 0, $base, $label, 'field_created', $userId, $userName);
            $stmt->close();
            return array('success' => true, 'message' => 'Field "' . $label . '" added.', 'key' => $base);
        }
        $err = $stmt->error;
        $stmt->close();
        return array('success' => false, 'message' => 'Could not add field: ' . $err);
    }
}

/* Update an existing field's definition (label / type / max / required).
   Works for system AND custom fields via the override layer. */
if (!function_exists('customizer_update_field')) {
    function customizer_update_field($con, $module, $fieldKey, $label, $type, $maxLength, $required, $section, $status)
    {
        $module   = $con->real_escape_string($module);
        $fieldKey = $con->real_escape_string($fieldKey);
        $label    = trim((string) $label);
        $type     = in_array($type, array('text', 'email', 'integer', 'date', 'phone', 'textarea'), true) ? $type : 'text';
        $maxLength = max(0, (int) $maxLength);
        $required = $required ? 1 : 0;
        $section  = trim((string) $section);
        $status   = $status ? 1 : 0;
        $userId   = (int) ($_SESSION['user_id'] ?? 0);
        $userName = $_SESSION['fullname'] ?? '';

        // Fetch prior values for the audit diff.
        $old = null;
        $res = $con->query("SELECT label, field_type, max_length, is_required, section, status FROM customizer_fields WHERE module='$module' AND field_key='$fieldKey'");
        if ($res) {
            $old = $res->fetch_assoc();
        }

        $stmt = $con->prepare(
            "UPDATE customizer_fields
                SET label=?, field_type=?, max_length=?, is_required=?, section=?, status=?, updated_by=?
              WHERE module=? AND field_key=?"
        );
        if (!$stmt) {
            return array('success' => false, 'message' => 'Prepared statement failed.');
        }
        $stmt->bind_param('ssiisiiss', $label, $type, $maxLength, $required, $section, $status, $userId, $module, $fieldKey);
        if ($stmt->execute()) {
            $oldLabel = $old['label'] ?? $label;
            customizer_audit($con, $module, 0, $fieldKey, $oldLabel, ($status ? 'field_updated' : 'field_hidden'), $userId, $userName, $oldLabel, $label);
            $stmt->close();
            return array('success' => true, 'message' => 'Field updated.');
        }
        $err = $stmt->error;
        $stmt->close();
        return array('success' => false, 'message' => 'Could not update field: ' . $err);
    }
}

/* Delete a CUSTOM field and its stored values. System fields cannot be
   deleted (they are disabled instead). */
if (!function_exists('customizer_delete_field')) {
    function customizer_delete_field($con, $module, $fieldKey)
    {
        $module   = $con->real_escape_string($module);
        $fieldKey = $con->real_escape_string($fieldKey);
        $userId   = (int) ($_SESSION['user_id'] ?? 0);
        $userName = $_SESSION['fullname'] ?? '';

        $res = $con->query("SELECT is_system, label FROM customizer_fields WHERE module='$module' AND field_key='$fieldKey'");
        $row = $res ? $res->fetch_assoc() : null;
        if (!$row) {
            return array('success' => false, 'message' => 'Field not found.');
        }
        if ((int) $row['is_system'] === 1) {
            return array('success' => false, 'message' => 'System fields can only be hidden, not deleted.');
        }

        $stmt = $con->prepare("DELETE FROM customizer_fields WHERE module=? AND field_key=? AND is_system=0");
        if (!$stmt) {
            return array('success' => false, 'message' => 'Prepared statement failed.');
        }
        $stmt->bind_param('ss', $module, $fieldKey);
        $ok = $stmt->execute();
        $stmt->close();

        $stmt2 = $con->prepare("DELETE FROM customizer_field_value WHERE module=? AND field_key=?");
        if ($stmt2) {
            $stmt2->bind_param('ss', $module, $fieldKey);
            $stmt2->execute();
            $stmt2->close();
        }

        if ($ok) {
            customizer_audit($con, $module, 0, $fieldKey, $row['label'], 'deleted', $userId, $userName, $row['label'], '');
            return array('success' => true, 'message' => 'Field deleted.');
        }
        return array('success' => false, 'message' => 'Could not delete field.');
    }
}
