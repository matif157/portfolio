<?php
/*  CLIENT TYPE MANUAL  ──────────────────────────────────────────────
    A single file that plays two roles:

      • Opened in the browser  -> the settings page where you assign each
        client type an icon and choose which profile fields are shown.

      • include()'d by another page (client_details.php) -> it stays
        SILENT and only exposes the shared "engine": the field catalog,
        the read helpers and the renderer. No HTML is produced.

    This keeps the whole feature in just two files (this one +
    client_details.php) with zero duplicated logic.
    ------------------------------------------------------------------- */

/* ---------- 0. CONTEXT ----------
   When included by a page that already has a DB connection we reuse it;
   when opened directly we set up our own db / auth / logging. */
if (!isset($con) || !($con instanceof mysqli)) {
    include 'includes/db.php';
    include "includes/auth.php";
    include "includes/activity_log.php";
}

/* ---------- 1. SELF-MIGRATING SCHEMA (runs once per request) ---------- */
if (!function_exists('cpc_bootstrap')) {
    function cpc_bootstrap($con)
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        // EVERYTHING lives in this one new table. No existing table (member_type,
        // members, …) is altered. It's a per-type key/value store:
        //   setting_key = '__icon__'  -> setting_value is the Font Awesome class
        //   setting_key = <field key> -> setting_value is '1' (show) or '0' (hide)
        $con->query("CREATE TABLE IF NOT EXISTS `client_type_profile_config` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `type_id` INT(11) NOT NULL,
            `setting_key` VARCHAR(64) NOT NULL,
            `setting_value` VARCHAR(255) NOT NULL DEFAULT '',
            `updated_at` TIMESTAMP NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_type_setting` (`type_id`,`setting_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3");
    }
}
cpc_bootstrap($con);

/* ---------- 2. FIELD CATALOG -----------------------------------------
   Two kinds of field:
     'data'   – backed by a members column (or composite via 'cols').
                Configurable here AND hidden automatically when the client
                left it blank.
     'fixed'  – structural / computed (Client ID, Full Name, Billing
                Balance …). Always shown; not toggleable. Its HTML is
                supplied by the page at render time through $fixed[key].

   Field options:
     'col'    => members column           (single value)
     'cols'   => [colA, colB]             (joined with a space, e.g. State/Zip)
     'format' => 'date'                   (M d, Y)
     'mailto' => true                     (wrap value in mailto:)
     'note'   => 'phone'|'email'|...      (render via cf_render_field – the
                                           field-notes widget)                */
if (!function_exists('cpc_catalog')) {
    function cpc_catalog()
    {
        return [
            'personal' => [
                'title' => 'Personal Information',
                'icon'  => 'fas fa-id-card',
                'perm'  => 'view_personal',
                'groups' => [
                    'identity' => [
                        'label' => 'Identity',
                        'icon'  => 'fas fa-fingerprint',
                        'fields' => [
                            'client_id' => ['label' => 'Client ID', 'kind' => 'fixed'],
                            'full_name' => ['label' => 'Full Name', 'kind' => 'fixed'],
                            'dob'       => ['label' => 'Date of Birth', 'col' => 'dob', 'format' => 'date'],
                            'ss'        => ['label' => 'SSN', 'col' => 'ss'],
                        ],
                    ],
                    'contact' => [
                        'label' => 'Contact',
                        'icon'  => 'fas fa-phone-alt',
                        'fields' => [
                            'cell_ph'   => ['label' => 'Cell Phone', 'col' => 'cell_ph', 'note' => 'phone'],
                            'prm_email' => ['label' => 'Primary Email', 'col' => 'prm_email', 'note' => 'email', 'mailto' => true],
                        ],
                    ],
                    'home_address' => [
                        'label' => 'Home Address',
                        'icon'  => 'fas fa-map-marker-alt',
                        'fields' => [
                            'hstreet'   => ['label' => 'Street', 'col' => 'hstreet'],
                            'hcity'     => ['label' => 'City', 'col' => 'hcity'],
                            'hstate_zip' => ['label' => 'State / Zip', 'cols' => ['hstate', 'hzip']],
                            'hcounty'   => ['label' => 'County', 'col' => 'hcounty'],
                        ],
                    ],
                    'account' => [
                        'label' => 'Account',
                        'icon'  => 'fas fa-info-circle',
                        'fields' => [
                            'billing_balance' => ['label' => 'Billing Balance', 'kind' => 'fixed'],
                            'added_by'        => ['label' => 'Added By', 'kind' => 'fixed'],
                            'updated_by'      => ['label' => 'Updated By', 'kind' => 'fixed'],
                        ],
                    ],
                ],
            ],
            'company' => [
                'title' => 'Company Information',
                'icon'  => 'fas fa-building',
                'perm'  => 'view_company',
                'groups' => [
                    'business' => [
                        'label' => 'Business',
                        'icon'  => 'fas fa-briefcase',
                        'fields' => [
                            'bus_name'       => ['label' => 'Business Name', 'col' => 'bus_name'],
                            'dba'            => ['label' => 'DBA', 'col' => 'dba'],
                            'ein'            => ['label' => 'EIN', 'col' => 'ein'],
                            'tf'             => ['label' => 'PMB', 'col' => 'tf'],
                            'file'           => ['label' => 'File #', 'col' => 'file'],
                            'client_type'    => ['label' => 'Client Type', 'kind' => 'fixed'],
                            'category'       => ['label' => 'Category', 'col' => 'category'],
                            'member_pos'     => ['label' => 'Designation', 'col' => 'member_pos'],
                            'bus_start_date' => ['label' => 'Start Date', 'col' => 'bus_start_date', 'format' => 'date'],
                            'incorp_date'    => ['label' => 'Incorp. Date', 'col' => 'incorp_date', 'format' => 'date'],
                        ],
                    ],
                    'ccontact' => [
                        'label' => 'Contact',
                        'icon'  => 'fas fa-phone-alt',
                        'fields' => [
                            'phone'      => ['label' => 'Work Phone', 'col' => 'phone'],
                            'other_ph'   => ['label' => 'Other Phone', 'col' => 'other_ph'],
                            'overseas_ph' => ['label' => 'Overseas Phone', 'col' => 'overseas_ph'],
                            'sec_email'  => ['label' => 'Business Email', 'col' => 'sec_email', 'mailto' => true],
                        ],
                    ],
                    'business_address' => [
                        'label' => 'Business Address',
                        'icon'  => 'fas fa-map-marker-alt',
                        'fields' => [
                            'address'   => ['label' => 'Street', 'col' => 'address'],
                            'city'      => ['label' => 'City', 'col' => 'city'],
                            'state_zip' => ['label' => 'State / Zip', 'cols' => ['state', 'zip']],
                            'bcounty'   => ['label' => 'County', 'col' => 'bcounty'],
                        ],
                    ],
                ],
            ],
        ];
    }
}

/* All toggleable (kind === 'data') field keys, flat list. */
if (!function_exists('cpc_data_field_keys')) {
    function cpc_data_field_keys()
    {
        $keys = [];
        foreach (cpc_catalog() as $card) {
            foreach ($card['groups'] as $group) {
                foreach ($group['fields'] as $fk => $f) {
                    if (($f['kind'] ?? 'data') === 'data') {
                        $keys[] = $fk;
                    }
                }
            }
        }
        return $keys;
    }
}

/* ---------- 3. READ HELPERS ------------------------------------------ */
// Folder of ready-made client-type icon images to pick from.
if (!defined('CPC_ICON_DIR')) {
    define('CPC_ICON_DIR', 'Uploads/client_type_icon');
}
// Shown for any client type that hasn't been given an icon yet.
if (!defined('CPC_DEFAULT_ICON')) {
    define('CPC_DEFAULT_ICON', 'images/default-user.png');
}

if (!function_exists('cpc_icon_gallery')) {
    /* The pickable icon images that live in CPC_ICON_DIR (relative paths). */
    function cpc_icon_gallery()
    {
        $out = [];
        if (is_dir(CPC_ICON_DIR)) {
            foreach (scandir(CPC_ICON_DIR) as $f) {
                if (preg_match('/\.(png|jpe?g|gif|webp|svg)$/i', $f)) {
                    $out[] = CPC_ICON_DIR . '/' . $f;
                }
            }
            sort($out);
        }
        return $out;
    }
}

if (!function_exists('cpc_store_uploaded_icon')) {
    /* Accepts a PNG/JPG picked from the admin's PC, drops it into
       CPC_ICON_DIR and returns its relative path. Returns '' when no file
       was sent (not an error) or when the file was rejected — in the latter
       case $err carries the reason. */
    function cpc_store_uploaded_icon($file, &$err = '')
    {
        $err = '';
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return '';
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $err = 'The image could not be uploaded (error code ' . (int) $file['error'] . ').';
            return '';
        }
        if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
            $err = 'Image is too large. Maximum size is 2 MB.';
            return '';
        }

        // Trust what the file actually is, not the extension the browser sent.
        $info = @getimagesize($file['tmp_name']);
        $ext  = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg'][$info[2] ?? 0] ?? '';
        if ($ext === '') {
            $err = 'Only PNG or JPG images are allowed.';
            return '';
        }

        $dir = __DIR__ . '/' . CPC_ICON_DIR;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            $err = 'The image folder (' . CPC_ICON_DIR . ') could not be created.';
            return '';
        }

        $base = preg_replace('/[^A-Za-z0-9_-]+/', '_', pathinfo($file['name'] ?? '', PATHINFO_FILENAME));
        $base = trim(substr($base, 0, 40), '_');
        $name = ($base !== '' ? $base : 'client_type') . '-' . time() . '.' . $ext;

        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
            $err = 'The uploaded image could not be saved.';
            return '';
        }
        return CPC_ICON_DIR . '/' . $name;
    }
}

if (!function_exists('cpc_type_icon_src')) {
    /* Raw stored icon path for a type, or '' if none chosen. */
    function cpc_type_icon_src($con, $type_id)
    {
        $type_id = (int) $type_id;
        $r = mysqli_fetch_assoc(mysqli_query($con, "SELECT setting_value FROM client_type_profile_config WHERE type_id=$type_id AND setting_key='__icon__'"));
        return trim($r['setting_value'] ?? '');
    }
}

if (!function_exists('cpc_type_icon_url')) {
    /* Display URL: the chosen image if present, otherwise the default. */
    function cpc_type_icon_url($con, $type_id)
    {
        $src = cpc_type_icon_src($con, $type_id);
        return $src !== '' ? $src : CPC_DEFAULT_ICON;
    }
}

if (!function_exists('cpc_type_field_config')) {
    /* Returns [field_key => bool visible]. Defaults to visible when the
       type has never been configured (backward compatible). */
    function cpc_type_field_config($con, $type_id)
    {
        $cfg = array_fill_keys(cpc_data_field_keys(), true);
        $type_id = (int) $type_id;
        // Pull all rows for this type; the '__icon__' row is simply ignored here
        // because it isn't one of the catalog's field keys.
        $res = mysqli_query($con, "SELECT setting_key, setting_value FROM client_type_profile_config WHERE type_id=$type_id");
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                if (array_key_exists($row['setting_key'], $cfg)) {
                    $cfg[$row['setting_key']] = ((int) $row['setting_value'] === 1);
                }
            }
        }
        return $cfg;
    }
}

if (!function_exists('cpc_all_types_field_config')) {
    /* Same as cpc_type_field_config() but for every member_type row at once —
       one query instead of N. Used by add/edit client pages to embed every
       type's field visibility as JSON for instant client-side switching. */
    function cpc_all_types_field_config($con)
    {
        $keys = cpc_data_field_keys();
        $out = [];
        $typesRes = mysqli_query($con, "SELECT type_id FROM member_type");
        while ($t = mysqli_fetch_assoc($typesRes)) {
            $out[(int) $t['type_id']] = array_fill_keys($keys, true);
        }
        $res = mysqli_query($con, "SELECT type_id, setting_key, setting_value FROM client_type_profile_config");
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $tid = (int) $row['type_id'];
                if (isset($out[$tid]) && array_key_exists($row['setting_key'], $out[$tid])) {
                    $out[$tid][$row['setting_key']] = ((int) $row['setting_value'] === 1);
                }
            }
        }
        return $out;
    }
}

/* ---------- 4. RENDERER ---------------------------------------------- */
if (!function_exists('cpc_render_card')) {
    /*
       Emits the inner rows of a profile card (group labels + fields) ONLY.
       The page supplies the .card / .pd-card-header / .pd-card-body shell so
       it can append extra blocks (e.g. Associated Businesses) inside the body.

       $cardKey : 'personal' | 'company'
       $member  : the members row (assoc)
       $visible : map from cpc_type_field_config()
       $fixed   : map field_key => ready-to-print HTML for 'fixed' fields.
                  Omit a key (or pass '') to skip that fixed row.
    */
    function cpc_render_card($con, $client_id, $member, $cardKey, $visible, $fixed = [])
    {
        $catalog = cpc_catalog();
        if (!isset($catalog[$cardKey])) {
            return;
        }
        $card = $catalog[$cardKey];

        $printedGroup = false;
        foreach ($card['groups'] as $group) {
            $rows = '';
            foreach ($group['fields'] as $fk => $f) {
                $kind = $f['kind'] ?? 'data';

                if ($kind === 'fixed') {
                    // Structural/computed: HTML supplied by the page.
                    if (!empty($fixed[$fk])) {
                        $rows .= $fixed[$fk];
                    }
                    continue;
                }

                // data field — hidden by the manual?
                if (empty($visible[$fk])) {
                    continue;
                }

                // resolve value (single column or composite)
                if (isset($f['cols'])) {
                    $parts = [];
                    foreach ($f['cols'] as $c) {
                        $p = trim((string) ($member[$c] ?? ''));
                        if ($p !== '') {
                            $parts[] = $p;
                        }
                    }
                    $rawValue = trim(implode(' ', $parts));
                } else {
                    $rawValue = trim((string) ($member[$f['col']] ?? ''));
                }

                // hide when the client left it blank
                if ($rawValue === '' || $rawValue === '0000-00-00') {
                    continue;
                }

                if (!empty($f['note']) && function_exists('cf_render_field')) {
                    // field-notes widget (keeps the sticky-note feature)
                    ob_start();
                    cf_render_field($con, $client_id, $f['note'], $member[$f['col']], $f['label'], !empty($f['mailto']));
                    $rows .= ob_get_clean();
                } else {
                    $display = $rawValue;
                    if (($f['format'] ?? '') === 'date') {
                        $ts = strtotime($rawValue);
                        $display = $ts ? date('M d, Y', $ts) : $rawValue;
                    }
                    $display = htmlspecialchars($display);
                    if (!empty($f['mailto'])) {
                        $display = "<a href='mailto:" . htmlspecialchars($rawValue) . "'>" . $display . "</a>";
                    }
                    $rows .= '<div class="pd-field"><span class="pd-label">' . htmlspecialchars($f['label'])
                        . '</span><span class="pd-value">' . $display . '</span></div>';
                }
            }

            if ($rows !== '') {
                $mt = $printedGroup ? ' mt-3' : '';
                echo '<div class="pd-group-label' . $mt . '"><i class="' . htmlspecialchars($group['icon'])
                    . '"></i> ' . htmlspecialchars($group['label']) . '</div>';
                echo $rows;
                $printedGroup = true;
            }
        }
    }
}

if (!function_exists('cpc_hero_row')) {
    /* One field row for the profile's top summary panel (the "ch-detail" cards).
       Returns '' when the field is hidden by the manual ($key given + not
       visible for this type) or when the client left it blank — same rules as
       cpc_render_card. Pass $key = null for always-on fields (e.g. Client Type). */
    function cpc_hero_row($label, $rawValue, $visible = null, $key = null)
    {
        $rawValue = trim((string) $rawValue);
        if ($key !== null && empty($visible[$key])) {
            return '';
        }
        if ($rawValue === '' || $rawValue === '0000-00-00') {
            return '';
        }
        return '<div class="ch-detail-field"><b>' . htmlspecialchars($label) . ':</b> '
            . htmlspecialchars($rawValue) . '</div>';
    }
}

/* =====================================================================
   Everything below runs ONLY when this file is opened directly (the
   settings page). When include()'d, we stop here so the host page gets
   the engine without any output.
   ===================================================================== */
$cpm_self   = realpath(__FILE__);
$cpm_script = realpath($_SERVER['SCRIPT_FILENAME'] ?? '');
if (!$cpm_self || !$cpm_script || strcasecmp($cpm_self, $cpm_script) !== 0) {
    return;
}

/* Admin-only settings page — block direct access by non-admins even though
   the sidebar link is already hidden from them. */
if ($usertype != '1') {
    header("Location: index.php");
    exit;
}

/* ---------- SAVE ---------- */
$msg = '';
if (isset($_POST['save_type_config'])) {
    $type_id = (int) ($_POST['type_id'] ?? 0);

    if ($type_id > 0) {
        $iconPath = trim($_POST['icon'] ?? '');

        // A PNG/JPG uploaded from the PC wins over the gallery selection; it
        // lands in CPC_ICON_DIR so it becomes pickable for other types too.
        $upErr    = '';
        $uploaded = cpc_store_uploaded_icon($_FILES['icon_file'] ?? null, $upErr);
        if ($uploaded !== '') {
            $iconPath = $uploaded;
        } elseif ($iconPath !== '' && !in_array($iconPath, cpc_icon_gallery(), true)) {
            // The chosen icon must be one of the gallery images (empty = default).
            $iconPath = ''; // ignore anything outside the allowed folder
        }

        // One statement writes everything into the single config table.
        $up = $con->prepare("INSERT INTO client_type_profile_config (type_id, setting_key, setting_value)
                             VALUES (?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");

        // 1) the icon image path
        $iconKey = '__icon__';
        $up->bind_param("iss", $type_id, $iconKey, $iconPath);
        $up->execute();

        // 2) field visibility — checked boxes -> '1', everything else -> '0'
        $checked = (isset($_POST['field']) && is_array($_POST['field'])) ? array_flip($_POST['field']) : [];
        foreach (cpc_data_field_keys() as $fk) {
            $vis = isset($checked[$fk]) ? '1' : '0';
            $up->bind_param("iss", $type_id, $fk, $vis);
            $up->execute();
        }
        $up->close();

        if (function_exists('logActivity')) {
            logActivity($con, $_SESSION['user_id'], 'UPDATE', 'client_type_profile_config', $type_id, "Updated profile manual for client type #$type_id");
        }
        $msg = ($upErr !== '' ? "<div class='alert alert-warning'>" . htmlspecialchars($upErr) . " The image was not changed.</div>" : '')
            . "<div class='alert alert-success'>Profile settings saved for this client type.</div>";
    } else {
        $msg = "<div class='alert alert-danger'>Invalid client type.</div>";
    }
}

$catalog = cpc_catalog();
$types   = mysqli_query($con, "SELECT type_id, type_name, type_status FROM member_type ORDER BY type_name ASC");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Syed Professional Services - Client Type Manual</title>
    <?php include "includes/links.php"; ?>
    <style>
        .ctm-type-card {
            background: #fff;
            border: 1px solid #e8ecef;
            border-radius: 12px;
            margin-bottom: 16px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, .05);
        }

        .ctm-type-head {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px 20px;
            cursor: pointer;
            background: linear-gradient(135deg, rgba(111, 217, 67, .07), #fff);
        }

        .ctm-type-icon {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            background: rgba(111, 217, 67, .12);
            color: #4caf2f;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex: 0 0 auto;
        }

        .ctm-type-name {
            font-weight: 700;
            color: #2c3e50;
            font-size: 1.1rem;
            flex: 1;
        }

        .ctm-type-meta {
            font-size: .78rem;
            color: #8a94a6;
            font-weight: 600;
        }

        .ctm-chevron {
            transition: transform .25s ease;
            color: #98a2b3;
        }

        .ctm-type-card.open .ctm-chevron {
            transform: rotate(180deg);
        }

        .ctm-body {
            display: none;
            padding: 20px;
            border-top: 1px solid #f0f2f5;
        }

        .ctm-type-card.open .ctm-body {
            display: block;
        }

        .ctm-icon-row {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 14px;
            background: #f8fafb;
            border-radius: 10px;
            margin-bottom: 18px;
        }

        .ctm-icon-preview {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            background: #fff;
            border: 1px solid #e2e6ea;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #4caf2f;
        }

        .ctm-section {
            margin-bottom: 18px;
        }

        .ctm-section-title {
            font-weight: 700;
            color: #344054;
            font-size: .8rem;
            text-transform: uppercase;
            letter-spacing: .5px;
            margin-bottom: 6px;
            border-bottom: 1px solid #eef1f4;
            padding-bottom: 6px;
        }

        .ctm-group-label {
            font-size: .72rem;
            font-weight: 700;
            color: #6FD943;
            text-transform: uppercase;
            letter-spacing: .4px;
            margin: 8px 0 4px;
        }

        .ctm-fields {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
            gap: 6px 18px;
        }

        .ctm-field {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: .85rem;
            color: #475467;
            padding: 4px 0;
        }

        .ctm-field input {
            width: 16px;
            height: 16px;
            accent-color: #6FD943;
            cursor: pointer;
        }

        .ctm-bulk {
            font-size: .76rem;
            font-weight: 600;
        }

        .ctm-bulk a {
            color: #4caf2f;
            cursor: pointer;
            text-decoration: none;
            margin-left: 10px;
        }

        /* Icon picker overlay */
        #iconPicker {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, .5);
            z-index: 2000;
            display: none;
            align-items: center;
            justify-content: center;
        }

        #iconPicker.show {
            display: flex;
        }

        .ip-panel {
            background: #fff;
            width: min(560px, 92vw);
            max-height: 80vh;
            border-radius: 14px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(0, 0, 0, .3);
        }

        .ip-head {
            padding: 14px 18px;
            border-bottom: 1px solid #eef1f4;
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .ip-head h5 {
            margin: 0;
            font-weight: 700;
            color: #2c3e50;
            flex: 1;
        }

        .ip-search {
            padding: 12px 18px;
        }

        .ip-grid {
            padding: 4px 18px 18px;
            overflow-y: auto;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(64px, 1fr));
            gap: 8px;
        }

        .ip-item {
            aspect-ratio: 1;
            border: 1px solid #eef1f4;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: #475467;
            cursor: pointer;
            transition: all .15s ease;
        }

        .ip-item:hover {
            border-color: #6FD943;
            color: #4caf2f;
            background: rgba(111, 217, 67, .08);
            transform: translateY(-2px);
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
                                <h4 class="m-b-10">Client Type Manual</h4>
                            </div>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="client_types.php">Client Types</a></li>
                                <li class="breadcrumb-item">Manual</li>
                            </ul>
                        </div>
                        <div class="col">
                            <div class="float-end">
                                <a href="client_types.php" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-arrow-left"></i> Back to Client Types
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($msg) echo $msg; ?>

            <?php while ($t = mysqli_fetch_assoc($types)):
                $tid = (int) $t['type_id'];
                $ticon_src = cpc_type_icon_src($con, $tid);
                $ticon_url = $ticon_src !== '' ? $ticon_src : CPC_DEFAULT_ICON;
                $cfg = cpc_type_field_config($con, $tid);
                $visibleCount = count(array_filter($cfg));
                $totalCount = count($cfg);
            ?>
                <div class="ctm-type-card" id="card-<?= $tid ?>">
                    <div class="ctm-type-head" onclick="ctmToggle(<?= $tid ?>)">
                        <div class="ctm-type-icon"><img src="<?= htmlspecialchars($ticon_url) ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;"></div>
                        <div class="ctm-type-name"><?= htmlspecialchars($t['type_name']) ?></div>
                        <div class="ctm-type-meta">
                            <?= $visibleCount ?>/<?= $totalCount ?> fields shown
                            <?php if ($t['type_status'] == '1'): ?>
                                · <span class="text-danger">Disabled</span>
                            <?php endif; ?>
                        </div>
                        <i class="fas fa-chevron-down ctm-chevron"></i>
                    </div>

                    <div class="ctm-body">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="type_id" value="<?= $tid ?>">
                            <input type="hidden" name="icon" id="icon-input-<?= $tid ?>" value="<?= htmlspecialchars($ticon_src) ?>">
                            <input type="file" name="icon_file" id="icon-file-<?= $tid ?>" class="d-none"
                                accept=".png,.jpg,.jpeg,image/png,image/jpeg" onchange="ctmFilePicked(<?= $tid ?>, this)">

                            <!-- Icon image: pick one from Uploads/client_type_icon or upload a PNG/JPG from the PC -->
                            <div class="ctm-icon-row">
                                <div class="ctm-icon-preview">
                                    <img src="<?= htmlspecialchars($ticon_url) ?>" id="icon-preview-<?= $tid ?>" alt=""
                                        style="width:100%;height:100%;object-fit:cover;border-radius:10px;">
                                </div>
                                <div style="flex:1;">
                                    <div style="font-weight:700;color:#344054;">Profile Image</div>
                                    <div class="text-muted" style="font-size:.78rem;" id="icon-name-<?= $tid ?>">
                                        <?= $ticon_src !== '' ? htmlspecialchars(basename($ticon_src)) : 'Default image' ?>
                                    </div>
                                    <div class="text-muted" style="font-size:.72rem;">Shown on the client's business profile. PNG or JPG, max 2 MB.</div>
                                </div>
                                <div style="margin-left:auto; display:flex; gap:8px;">
                                    <button type="button" class="btn btn-sm btn-primary" onclick="ctmOpenPicker(<?= $tid ?>)">
                                        <i class="fas fa-images"></i> Choose Image
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="document.getElementById('icon-file-<?= $tid ?>').click()">
                                        <i class="fas fa-upload"></i> Upload from PC
                                    </button>
                                    <button type="button" class="btn btn-sm btn-light" onclick="ctmClearIcon(<?= $tid ?>)">Clear</button>
                                </div>
                            </div>

                            <!-- Fields per card / section -->
                            <?php foreach ($catalog as $cardKey => $card): ?>
                                <div class="ctm-section">
                                    <div class="ctm-section-title">
                                        <i class="<?= htmlspecialchars($card['icon']) ?>"></i>
                                        <?= htmlspecialchars($card['title']) ?>
                                        <span class="ctm-bulk float-end">
                                            <a onclick="ctmBulk(<?= $tid ?>,'<?= $cardKey ?>',true)">Select all</a>
                                            <a onclick="ctmBulk(<?= $tid ?>,'<?= $cardKey ?>',false)">Clear</a>
                                        </span>
                                    </div>
                                    <?php foreach ($card['groups'] as $group):
                                        $dataFields = array_filter($group['fields'], fn($f) => ($f['kind'] ?? 'data') === 'data');
                                        if (!$dataFields) continue;
                                    ?>
                                        <div class="ctm-group">
                                            <div class="ctm-group-label"><i class="<?= htmlspecialchars($group['icon']) ?>"></i> <?= htmlspecialchars($group['label']) ?></div>
                                            <div class="ctm-fields">
                                                <?php foreach ($dataFields as $fk => $f): ?>
                                                    <label class="ctm-field">
                                                        <input type="checkbox" name="field[]" value="<?= htmlspecialchars($fk) ?>"
                                                            data-card="<?= $cardKey ?>" data-tid="<?= $tid ?>"
                                                            <?= !empty($cfg[$fk]) ? 'checked' : '' ?>>
                                                        <span><?= htmlspecialchars($f['label']) ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>

                            <div class="text-end mt-2">
                                <button type="submit" name="save_type_config" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>

        </div>
    </div>

    <!-- Shared image picker (images from Uploads/client_type_icon) -->
    <div id="iconPicker">
        <div class="ip-panel">
            <div class="ip-head">
                <h5><i class="fas fa-images"></i> Choose an image</h5>
                <button type="button" class="btn btn-sm btn-light" onclick="ctmClosePicker()"><i class="fas fa-times"></i></button>
            </div>
            <div class="ip-grid" id="ipGrid"></div>
        </div>
    </div>

    <?php include "includes/footer.php"; ?>
    <?php include "includes/scripts.php"; ?>

    <script>
        // Icon images available in Uploads/client_type_icon
        const CTM_ICONS = <?= json_encode(cpc_icon_gallery()) ?>;
        const CTM_DEFAULT = <?= json_encode(CPC_DEFAULT_ICON) ?>;
        let ctmActiveTid = null;

        function ctmToggle(tid) {
            document.getElementById('card-' + tid).classList.toggle('open');
        }

        function ctmBulk(tid, cardKey, val) {
            document.querySelectorAll('input[name="field[]"][data-tid="' + tid + '"][data-card="' + cardKey + '"]')
                .forEach(cb => cb.checked = val);
        }

        function ctmOpenPicker(tid) {
            ctmActiveTid = tid;
            document.getElementById('iconPicker').classList.add('show');
            ctmRenderIcons();
        }

        function ctmClosePicker() {
            document.getElementById('iconPicker').classList.remove('show');
            ctmActiveTid = null;
        }

        function ctmRenderIcons() {
            const grid = document.getElementById('ipGrid');
            grid.innerHTML = '';
            CTM_ICONS.forEach(src => {
                const d = document.createElement('div');
                d.className = 'ip-item';
                d.title = src.split('/').pop();
                d.innerHTML = '<img src="' + src + '" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:8px;">';
                d.onclick = () => ctmPickIcon(src);
                grid.appendChild(d);
            });
            if (!grid.children.length) {
                grid.innerHTML = '<div class="text-muted" style="grid-column:1/-1;text-align:center;padding:20px;">No images found in Uploads/client_type_icon.</div>';
            }
        }

        function ctmPickIcon(src) {
            const tid = ctmActiveTid;
            if (tid === null) return;
            document.getElementById('icon-input-' + tid).value = src;
            document.getElementById('icon-preview-' + tid).src = src;
            document.getElementById('icon-file-' + tid).value = ''; // gallery pick drops any staged upload
            const nm = document.getElementById('icon-name-' + tid);
            if (nm) nm.textContent = src.split('/').pop();
            ctmClosePicker();
        }

        // A PNG/JPG chosen from the PC — preview it now, upload it on Save.
        function ctmFilePicked(tid, input) {
            const f = input.files && input.files[0];
            if (!f) return;
            if (!/^image\/(png|jpeg)$/.test(f.type)) {
                alert('Only PNG or JPG images are allowed.');
                input.value = '';
                return;
            }
            if (f.size > 2 * 1024 * 1024) {
                alert('Image is too large. Maximum size is 2 MB.');
                input.value = '';
                return;
            }
            document.getElementById('icon-preview-' + tid).src = URL.createObjectURL(f);
            const nm = document.getElementById('icon-name-' + tid);
            if (nm) nm.textContent = f.name + ' — click Save Settings to apply';
        }

        function ctmClearIcon(tid) {
            document.getElementById('icon-input-' + tid).value = '';
            document.getElementById('icon-file-' + tid).value = '';
            document.getElementById('icon-preview-' + tid).src = CTM_DEFAULT;
            const nm = document.getElementById('icon-name-' + tid);
            if (nm) nm.textContent = 'Default image';
        }

        document.getElementById('iconPicker').addEventListener('click', e => {
            if (e.target.id === 'iconPicker') ctmClosePicker();
        });
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') ctmClosePicker();
        });
    </script>
</body>

</html>
