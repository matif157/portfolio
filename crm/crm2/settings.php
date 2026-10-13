<?php
/*  SYSTEM SETTINGS  ─  admin control panel
    -------------------------------------------------------------------------
    Unified admin control panel: Sync/Deploy, Stripe, SMTP, Twilio, Pusher,
    Company Info, Payment Methods.  Accessible to admin (user_type=1).
    Gracefully degrades when DB tables or files are missing.
    ------------------------------------------------------------------------- */
include "includes/db.php";
include "includes/auth.php";

if ($usertype != '1') { header("Location: index.php"); exit; }

require_once __DIR__ . '/loadEnv.php';

/* ── .env read/write (must exist before AJAX handlers) ──────────────── */
$envFile  = __DIR__ . '/.env';
$envData  = [];
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line[0] === '#' || strpos($line, '=') === false) continue;
        list($k, $v) = explode('=', $line, 2);
        $envData[trim($k)] = trim($v);
    }
}
function env_get($k, $d = '') { global $envData; return $envData[$k] ?? $d; }
function env_set($k, $v) {
    global $envData, $envFile;
    $envData[$k] = $v;
    $lines = [];
    $written = false;
    if (file_exists($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (strpos($line, $k . '=') === 0) { $lines[] = $k . '=' . $v; $written = true; }
            else { $lines[] = $line; }
        }
    }
    if (!$written) $lines[] = $k . '=' . $v;
    file_put_contents($envFile, implode("\n", $lines) . "\n");
}

/* ── AJAX test handlers (must run BEFORE any page output) ───────────── */
function _aj($ok, $msg) { header('Content-Type: application/json'); echo json_encode(['ok'=>$ok,'msg'=>$msg]); exit; }
if (isset($_POST['sync_test'])) {
    $url   = @trim($_POST['sync_url']   ?? '');
    $token = @trim($_POST['sync_token'] ?? '');
    if ($url === '' || $token === '') _aj(false, 'Server URL and token are required.');
    $pingUrl = rtrim($url, '/?') . '?token=' . urlencode($token) . '&type=ping';
    $ctx = stream_context_create(['http'=>['timeout'=>10,'ignore_errors'=>true]]);
    $resp = @file_get_contents($pingUrl, false, $ctx);
    if ($resp !== false && trim($resp) === 'ok') {
        $msg = 'PING ok — endpoint reachable and token accepted.';
        $dbUrl = rtrim($url, '/?') . '?token=' . urlencode($token) . '&type=db';
        $dbResp = @file_get_contents($dbUrl, false, stream_context_create(['http'=>['timeout'=>15,'ignore_errors'=>true]]));
        if ($dbResp !== false && strlen($dbResp) > 100) $msg .= ' DB dump: ' . number_format(strlen($dbResp)) . ' bytes.';
        $fUrl = rtrim($url, '/?') . '?token=' . urlencode($token) . '&type=files';
        $hdrs = @get_headers($fUrl);
        if ($hdrs && strpos($hdrs[0], '200') !== false) $msg .= ' Files: reachable.';
        _aj(true, $msg);
    }
    _aj(false, 'PING failed — ' . ($resp ?: 'timeout or 404'));
}
if (isset($_POST['stripe_test'])) {
    $sk = env_get('STRIPE_SECRET_KEY', '');
    if ($sk === '') _aj(false, 'No Stripe secret key configured.');
    $ch = curl_init('https://api.stripe.com/v1/account');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_USERPWD=>$sk.':', CURLOPT_TIMEOUT=>15]);
    $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($code === 200) { $d = json_decode($resp, true); _aj(true, 'Stripe connected — ' . ($d['display_name'] ?? $d['id'] ?? 'ok')); }
    _aj(false, 'Stripe error (HTTP ' . $code . '): ' . substr($resp, 0, 200));
}
if (isset($_POST['smtp_test'])) {
    $to = trim($_POST['smtp_test_to'] ?? '');
    if ($to === '') _aj(false, 'Enter a test email address.');
require_once __DIR__ . '/PHPMailer/PHPMailerAutoload.php';
    $m = new PHPMailer(true);
    try {
        $m->isSMTP();
        $m->Host       = trim($_POST['smtp_host'] ?? '');
        $m->Port       = (int)($_POST['smtp_port'] ?? 587);
        $m->SMTPAuth   = true;
        $m->Username   = trim($_POST['smtp_username'] ?? '');
        $m->Password   = trim($_POST['smtp_password'] ?? '');
        $m->SMTPSecure = trim($_POST['smtp_secure'] ?? 'tls');
        $m->setFrom(trim($_POST['smtp_from_email'] ?? ''), trim($_POST['smtp_from_name'] ?? 'SPS CRM'));
        $m->addAddress($to);
        $m->isHTML(true);
        $m->Subject = 'SPS CRM — SMTP Test';
        $m->Body    = '<b>SPS CRM SMTP test</b><br>Email is configured correctly.';
        $m->send();
        _aj(true, 'Test email sent to ' . htmlspecialchars($to) . '.');
    } catch (Exception $e) { _aj(false, 'SMTP error: ' . $e->getMessage()); }
}
if (isset($_POST['twilio_test'])) {
    $sid = trim($_POST['twilio_account_sid'] ?? '');
    $tok = trim($_POST['twilio_auth_token']  ?? '');
    if ($sid === '' || $tok === '') _aj(false, 'SID and token are required.');
    $ch = curl_init("https://api.twilio.com/2010-04-01/Accounts/$sid.json");
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_USERPWD=>"$sid:$tok", CURLOPT_TIMEOUT=>15]);
    $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($code === 200) { $d = json_decode($resp, true); _aj(true, 'Twilio connected — ' . ($d['friendly_name'] ?? $sid)); }
    _aj(false, 'Twilio error (HTTP ' . $code . '): ' . substr($resp, 0, 200));
}

$msg = '';
$msgType = '';
$tab = $_GET['tab'] ?? 'sync';

/* ── helpers ────────────────────────────────────────────────────────── */
function s_get($con, $key, $default = '') {
    $key = mysqli_real_escape_string($con, $key);
    $q = @mysqli_query($con, "SELECT marker_value FROM settings_marker WHERE marker_key='$key'");
    return ($q && mysqli_num_rows($q) > 0) ? mysqli_fetch_assoc($q)['marker_value'] : $default;
}
function s_set($con, $key, $value) {
    $key   = mysqli_real_escape_string($con, $key);
    $value = mysqli_real_escape_string($con, $value);
    @mysqli_query($con, "INSERT INTO settings_marker (marker_key, marker_value) VALUES ('$key','$value')
                         ON DUPLICATE KEY UPDATE marker_value = VALUES(marker_value)");
}
function esc($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function mask_key($k, $show = 6) {
    if ($k === '') return '';
    return substr($k, 0, $show) . str_repeat('*', max(0, strlen($k) - $show - 4)) . substr($k, -4);
}

/* ── billing_config ─────────────────────────────────────────────────── */
$bc = ['phone'=>'','fax'=>'','email'=>'','website'=>'','street'=>'','city'=>'','state'=>'','zip'=>''];
$bcQ = @mysqli_query($con, "SELECT phone,fax,email,website,street,city,state,zip FROM billing_config LIMIT 1");
if ($bcQ && mysqli_num_rows($bcQ) > 0) $bc = mysqli_fetch_assoc($bcQ);

/* ── payment types ──────────────────────────────────────────────────── */
$payTypes = [];
$ptQ = @mysqli_query($con, "SELECT payment_type_id, payment_type_detail FROM billing_payment_type ORDER BY payment_type_id");
if ($ptQ) { while ($row = mysqli_fetch_assoc($ptQ)) { $payTypes[] = $row; } }

/* ── SMTP (email_module) ────────────────────────────────────────────── */
$smtp = ['from_email'=>'','from_name'=>'','host'=>'smtp.office365.com','port'=>'587','secure'=>'tls','username'=>'','password'=>''];
$emQ = @mysqli_query($con, "SELECT config FROM email_module WHERE kind='account' AND id=1 LIMIT 1");
if ($emQ && mysqli_num_rows($emQ) > 0) {
    $cfg = json_decode(mysqli_fetch_assoc($emQ)['config'], true);
    if (is_array($cfg)) { foreach ($smtp as $k => $v) { $smtp[$k] = $cfg[$k] ?? $v; } }
}

/* ── Twilio (sip.php defines) ───────────────────────────────────────── */
$tw = ['TWILIO_ACCOUNT_SID'=>'', 'TWILIO_AUTH_TOKEN'=>'', 'TWILIO_PHONE_NUMBER'=>''];
$sipPath = __DIR__ . '/sip.php';
if (file_exists($sipPath)) {
    $content = file_get_contents($sipPath);
    preg_match_all("/define\\('([A-Z0-9_]+)'\\s*,\\s*'([^']*)'\\)/", $content, $m);
    foreach ($m[1] as $i => $k) {
        if (isset($tw[$k])) $tw[$k] = $m[2][$i];
    }
}

/* ══════════════════════════════════════════════════════════════════════
   SAVE HANDLERS
   ══════════════════════════════════════════════════════════════════════ */

/* ── Sync ───────────────────────────────────────────────────────────── */
if (isset($_POST['sync_save'])) {
    s_set($con, 'crm_sync_url',  trim($_POST['sync_url']  ?? ''));
    s_set($con, 'crm_sync_token', trim($_POST['sync_token'] ?? ''));
    s_set($con, 'crm_sync_auto',  (isset($_POST['sync_auto']) && $_POST['sync_auto'] === '1') ? '1' : '0');
    $msg = 'Sync settings saved.'; $msgType = 'success'; $tab = 'sync';
}

/* ── Stripe ─────────────────────────────────────────────────────────── */
if (isset($_POST['stripe_save'])) {
    $sk = trim($_POST['stripe_secret'] ?? '');
    $pk = trim($_POST['stripe_publishable'] ?? '');
    if ($sk !== '' && strpos($sk, '***') === false) env_set('STRIPE_SECRET_KEY', $sk);
    if ($pk !== '' && strpos($pk, '***') === false) env_set('STRIPE_PUBLISHABLE_KEY', $pk);
    env_set('STRIPE_CURRENCY', preg_match('/^[a-z]{3}$/', $_POST['stripe_currency'] ?? '') ? strtolower($_POST['stripe_currency']) : 'usd');
    /* patch $currency in Stripe PHP pages */
    $patched = 0;
    foreach (['stripe_payment.php', 'stripe_payment_ins.php'] as $f) {
        $path = __DIR__ . '/' . $f;
        if (!file_exists($path)) continue;
        $t = file_get_contents($path);
        $new = preg_replace('/(\\$currency\\s*=\\s*)\'[a-zA-Z]{3}\';/', '$1$_ENV[\'STRIPE_CURRENCY\'] ?? \'' . env_get('STRIPE_CURRENCY', 'usd') . '\';', $t, -1, $cnt);
        if ($cnt > 0) { file_put_contents($path, $new); $patched += $cnt; }
    }
    $msg = 'Stripe settings saved.' . ($patched > 0 ? " ($patched file(s) patched)" : ''); $msgType = 'success'; $tab = 'stripe';
}

/* ── SMTP ───────────────────────────────────────────────────────────── */
if (isset($_POST['smtp_save'])) {
    $j = json_encode([
        'from_email' => trim($_POST['smtp_from_email'] ?? ''),
        'from_name'  => trim($_POST['smtp_from_name']  ?? 'Syed Professional Services'),
        'host'       => trim($_POST['smtp_host']       ?? 'smtp.office365.com'),
        'port'       => (int)($_POST['smtp_port'] ?? 587),
        'secure'     => trim($_POST['smtp_secure']     ?? 'tls'),
        'username'   => trim($_POST['smtp_username']   ?? ''),
        'password'   => trim($_POST['smtp_password']    ?? ''),
    ]);
    $ej = mysqli_real_escape_string($con, $j);
    $q = @mysqli_query($con, "UPDATE email_module SET config='$ej' WHERE kind='account' AND id=1");
    if ($q && mysqli_affected_rows($con) === 0) {
        @mysqli_query($con, "INSERT INTO email_module (kind,title,config,is_default,is_active) VALUES ('account','Invoices','$ej',1,1)");
    }
    /* patch hardcoded SMTP in PHP mailer scripts */
    $smtpFiles = ['ajax_run_automation.php','bulk_email.php','email_history_handler.php',
        'stripe_payment.php','stripe_payment_ins.php','send_services_email.php',
        'send_quotation_reminder.php','send_inv_reminder.php','send_inst_inv_reminder.php'];
    $patched = 0;
    $host = mysqli_real_escape_string($con, trim($_POST['smtp_host'] ?? ''));
    $user = mysqli_real_escape_string($con, trim($_POST['smtp_username'] ?? ''));
    $pass = mysqli_real_escape_string($con, trim($_POST['smtp_password'] ?? ''));
    $from = mysqli_real_escape_string($con, trim($_POST['smtp_from_email'] ?? ''));
    $name = mysqli_real_escape_string($con, trim($_POST['smtp_from_name']  ?? 'Syed Professional Services'));
    foreach ($smtpFiles as $f) {
        $path = __DIR__ . '/' . $f;
        if (!file_exists($path)) continue;
        $t = file_get_contents($path);
        $b = $t;
        $t = preg_replace("/\\$mail->Host\\s*=\\s*'[^']*';/", "\$mail->Host = '$host';", $t);
        $t = preg_replace("/\\$mail->Username\\s*=\\s*'[^']*';/", "\$mail->Username = '$user';", $t);
        $t = preg_replace("/\\$mail->Password\\s*=\\s*'[^']*'\\s*(\\/\\/[^\\r\\n]*)?;/", "\$mail->Password = '$pass';", $t);
        $t = preg_replace("/setFrom\\s*\\(\\s*'[^']*'\\s*,\\s*'Syed[^']*'\\s*\\)/", "setFrom('$from', '$name')", $t);
        $t = preg_replace("/From:\\s*Syed Professional Services\\s*<[^>]+>/", "From: $name <$from>", $t);
        if ($t !== $b) { file_put_contents($path, $t); $patched++; }
    }
    $msg = 'SMTP settings saved.' . ($patched > 0 ? " ($patched PHP file(s) patched)" : ''); $msgType = 'success'; $tab = 'smtp';
}

/* ── Twilio ─────────────────────────────────────────────────────────── */
if (isset($_POST['twilio_save'])) {
    $path = __DIR__ . '/sip.php';
    if (file_exists($path)) {
        $t = file_get_contents($path);
        foreach (['TWILIO_ACCOUNT_SID', 'TWILIO_AUTH_TOKEN', 'TWILIO_PHONE_NUMBER'] as $const) {
            $v = mysqli_real_escape_string($con, trim($_POST[strtolower($const)] ?? ''));
            $t = preg_replace("/define\\('$const'\\s*,\\s*'[^']*'\\);/", "define('$const', '$v');", $t);
        }
        file_put_contents($path, $t);
    }
    $msg = 'Twilio settings saved.'; $msgType = 'success'; $tab = 'twilio';
}

/* ── Pusher ─────────────────────────────────────────────────────────── */
if (isset($_POST['pusher_save'])) {
    foreach (['PUSHER_APP_ID','PUSHER_KEY','PUSHER_SECRET','PUSHER_CLUSTER'] as $k) {
        $v = trim($_POST[strtolower($k)] ?? '');
        if ($v !== '' && strpos($v, '***') === false) env_set($k, $v);
    }
    $msg = 'Pusher settings saved.'; $msgType = 'success'; $tab = 'pusher';
}

/* ── Appearance (themes) ──────────────────────────────────────────────── */
if (isset($_POST['theme_save'])) {
    require_once __DIR__ . '/includes/theme.php';
    /* collect + sanitize */
    $presets = sps_theme_preset_ids();
    $v = $_POST;
    $save = function ($k, $default) use ($v) { return trim($v[$k] ?? $default); };
    $payload = [
        'preset'       => in_array($v['theme_preset'] ?? '', $presets) ? $v['theme_preset'] : 'classic',
        'accent'       => (($a = trim($v['theme_accent'] ?? '')) === '' ? '' : (ctype_digit($a) && (int)$a >= 1 && (int)$a <= 9 ? $a : '')),
        'accent_custom' => preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $v['theme_accent_custom'] ?? '') ? strtolower($v['theme_accent_custom']) : '',
        'bg'           => in_array($v['theme_bg'] ?? '', ['light','dark'], true) ? $v['theme_bg'] : 'light',
        'radius'       => in_array($v['theme_radius'] ?? '', ['default','square','rounded'], true) ? $v['theme_radius'] : 'default',
        'compact'      => ($v['theme_compact'] ?? '0') === '1' ? '1' : '0',
        'font'         => ($v['theme_font'] ?? '') === 'large' ? 'large' : 'normal',
        'effects'      => ($v['theme_effects'] ?? '0') === '1' ? '1' : '0',
        'cursor'       => in_array($v['theme_cursor'] ?? '', ['off','dot','ring','aura','cross','comet','pulse'], true) ? $v['theme_cursor'] : 'ring',
        'motion'       => ($v['theme_motion'] ?? 'on') === 'on' ? 'on' : 'off',
        'sidebar'      => in_array($v['theme_sidebar'] ?? '', ['auto','light','dark'], true) ? $v['theme_sidebar'] : 'auto',
        'header'       => in_array($v['theme_header'] ?? '', ['auto','light','dark','transparent'], true) ? $v['theme_header'] : 'auto',
        'glow'         => ($v['theme_glow'] ?? '0') === '1' ? '1' : '0',
        'heading_color' => (($h = trim($v['theme_heading_color'] ?? '')) !== '' && preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $h)) ? $h : '',
        'link_color'   => (($h = trim($v['theme_link_color'] ?? '')) !== '' && preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $h)) ? $h : '',
        'link_hover'   => (($h = trim($v['theme_link_hover'] ?? '')) !== '' && preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $h)) ? $h : '',
        'muted_color'  => (($h = trim($v['theme_muted_color'] ?? '')) !== '' && preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $h)) ? $h : '',
        'code_color'   => (($h = trim($v['theme_code_color'] ?? '')) !== '' && preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $h)) ? $h : '',
        'card_bg'      => (($h = trim($v['theme_card_bg'] ?? '')) !== '' && preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $h)) ? $h : '',
        'sidebar_bg_c' => (($h = trim($v['theme_sidebar_bg_c'] ?? '')) !== '' && preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $h)) ? $h : '',
        'header_bg_c'  => (($h = trim($v['theme_header_bg_c'] ?? '')) !== '' && preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $h)) ? $h : '',
        'font_family'  => in_array($v['theme_font_family'] ?? '', ['system','open-sans','inter','poppins','roboto','nunito'], true) ? $v['theme_font_family'] : 'system',
        'font_size'    => in_array($v['theme_font_size'] ?? '', ['xs','sm','normal','lg','xl'], true) ? $v['theme_font_size'] : 'normal',
        'heading_weight' => in_array($v['theme_heading_weight'] ?? '', ['300','400','500','600','700'], true) ? $v['theme_heading_weight'] : '600',
        'line_height'  => in_array($v['theme_line_height'] ?? '', ['tight','normal','relaxed'], true) ? $v['theme_line_height'] : 'normal',
        'letter_spacing' => in_array($v['theme_letter_spacing'] ?? '', ['tight','normal','wide'], true) ? $v['theme_letter_spacing'] : 'normal',
    ];
    foreach ($payload as $k => $value) s_set($con, "crm_theme_$k", (string)$value);
    $msg = 'Appearance saved — your theme applies to the whole CRM instantly.'; $msgType = 'success'; $tab = 'appearance';
}

/* ── Company ────────────────────────────────────────────────────────── */
if (isset($_POST['company_save'])) {
    $fields = ['phone','fax','email','website','street','city','state','zip'];
    $vals = [];
    foreach ($fields as $f) { $vals[$f] = mysqli_real_escape_string($con, trim($_POST["cp_$f"] ?? '')); }
    $sets = implode(', ', array_map(function($f) use ($vals) { return "$f='$vals[$f]'"; }, $fields));
    $q = @mysqli_query($con, "UPDATE billing_config SET $sets WHERE id=1");
    if ($q && mysqli_affected_rows($con) === 0) {
        $cols = implode(',', $fields);
        $vstr = implode(',', array_map(function($f) use ($vals) { return "'$vals[$f]'"; }, $fields));
        @mysqli_query($con, "INSERT INTO billing_config ($cols) VALUES ($vstr)");
    }
    $msg = 'Company info saved.'; $msgType = 'success'; $tab = 'company';
}

/* ── Payment Types ──────────────────────────────────────────────────── */
if (isset($_POST['pay_save'])) {
    $names = $_POST['pay_name'] ?? [];
    $ids   = $_POST['pay_id']   ?? [];
    foreach ($ids as $i => $pid) {
        $pid  = (int)$pid;
        $name = mysqli_real_escape_string($con, trim($names[$i] ?? ''));
        if ($name === '') continue;
        if ($pid > 0) {
            @mysqli_query($con, "UPDATE billing_payment_type SET payment_type_detail='$name' WHERE payment_type_id=$pid");
        } else {
            @mysqli_query($con, "INSERT INTO billing_payment_type (payment_type_detail) VALUES ('$name')");
        }
    }
    $msg = 'Payment methods saved.'; $msgType = 'success'; $tab = 'payments';
}

/* ── Endpoint Generate ──────────────────────────────────────────────── */
if (isset($_POST['ep_gen'])) {
    $host = trim($_POST['ep_host'] ?? 'localhost');
    $port = trim($_POST['ep_port'] ?? '3306');
    $user = trim($_POST['ep_user'] ?? 'root');
    $pass = trim($_POST['ep_pass'] ?? '');
    $db   = trim($_POST['ep_db']   ?? 'syedpro_app');
    $dump = trim($_POST['ep_dump'] ?? 'C:\\xampp\\mysql\\bin\\mysqldump.exe');
    $tkn  = trim($_POST['ep_token'] ?? s_get($con, 'crm_sync_token', 'sps-sync-2026'));
    $zip  = isset($_POST['ep_fullzip']) && $_POST['ep_fullzip'] === '1';
    $dirs = $zip
        ? "'storage', 'uploads', 'invoices', 'instant_invoices',\n    'instant_invoice_history', 'invoice_history', 'quotations', 'chats',\n    'images', 'images/doc', 'images/documents', 'images/clients'"
        : "'storage', 'uploads'";
    $php = '<?php
/* SPS CRM - MASTER SYNC ENDPOINT
   Generated by System Settings (Deploy tab).
   Deploy this file into the web root of any server.
   ============================================================ */
set_time_limit(0);
ini_set(\'memory_limit\', \'1024M\');
define(\'SYNC_TOKEN\', \'' . $tkn . '\');
define(\'MASTER_DB\', \'' . mysqli_real_escape_string($con, $db) . '\');
define(\'MASTER_MYSQLDUMP\', \'' . addslashes($dump) . '\');
$MASTER_DB_USER = \'' . addslashes($user) . '\';
$MASTER_DB_PASS = \'' . addslashes($pass) . '\';
$MASTER_DB_HOST = \'' . addslashes($host) . '\';
$MASTER_DB_PORT = \'' . addslashes($port) . '\';
putenv(\'MYSQL_PWD=\' . $MASTER_DB_PASS);

function sync_fail($code, $msg) {
    http_response_code($code);
    header(\'Content-Type: text/plain; charset=utf-8\');
    echo $msg; exit;
}
if (!isset($_GET[\'token\']) || $_GET[\'token\'] !== SYNC_TOKEN) { sync_fail(403, \'forbidden\'); }
$type = $_GET[\'type\'] ?? \'ping\';
if ($type === \'ping\') { header(\'Content-Type: text/plain\'); echo \'ok\'; exit; }

if ($type === \'db\') {
    $args = \'-u \' . $MASTER_DB_USER . \' --host=\' . $MASTER_DB_HOST . \' --port=\' . $MASTER_DB_PORT
          . \' --single-transaction --routines --triggers --databases \' . MASTER_DB;
    $cmd = escapeshellarg(MASTER_MYSQLDUMP) . \' \' . $args;
    header(\'Content-Type: application/sql; charset=utf-8\');
    header(\'Content-Disposition: attachment; filename=\' . MASTER_DB . \'_dump.sql\');
    header(\'X-Content-Type-Options: nosniff\');
    passthru($cmd); exit;
}

if ($type === \'files\') {
    $root = dirname(__DIR__);
    $zipPath = tempnam(sys_get_temp_dir(), \'sps\') . \'.zip\';
    $za = new ZipArchive();
    if ($za->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) { sync_fail(500, \'cannot create zip\'); }
    foreach ([' . $dirs . '] as $d) {
        $src = $root . \'/\' . $d;
        if (is_dir($src)) {
            $za->addEmptyDir($d);
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) { $rel = $d . DIRECTORY_SEPARATOR . $it->getSubPathname(); $za->addFile($f->getPathname(), $rel); $za->setCompressionName($rel, ZipArchive::CM_STORE); }
        }
    }
    $za->close();
    if (!is_file($zipPath)) { sync_fail(500, \'zip file not produced\'); }
    header(\'Content-Type: application/zip\');
    header(\'Content-Disposition: attachment; filename=sps_data_files.zip\');
    header(\'X-Content-Type-Options: nosniff\');
    header(\'Content-Length: \' . filesize($zipPath));
    readfile($zipPath); @unlink($zipPath); exit;
}
sync_fail(400, \'unknown type\');
?>';
    $epDir = __DIR__ . '/deploy';
    if (!is_dir($epDir)) @mkdir($epDir, 0755, true);
    $epPath = $epDir . '/sync_export.php';
    file_put_contents($epPath, $php);
    /* also drop a copy into the CRM root for local use */
    file_put_contents(__DIR__ . '/sync_export.php', $php);
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="sync_export.php"');
    header('Content-Length: ' . strlen($php));
    echo $php; exit;
}

/* ── Endpoint Download (from existing generated file) ───────────────── */
if (isset($_POST['ep_download'])) {
    $epPath = __DIR__ . '/deploy/sync_export.php';
    if (!file_exists($epPath)) { $msg = 'No generated endpoint found. Generate one first.'; $msgType = 'warning'; $tab = 'deploy'; }
    else {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="sync_export.php"');
        header('Content-Length: ' . filesize($epPath));
        readfile($epPath); exit;
    }
}

/* ── load values for display ────────────────────────────────────────── */
require_once __DIR__ . '/includes/theme.php';
$thCfg = sps_theme_config($con);
$syncUrl   = s_get($con, 'crm_sync_url', '');
$syncToken = s_get($con, 'crm_sync_token', 'sps-sync-2026');
$syncAuto  = s_get($con, 'crm_sync_auto', '0') === '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Syed Professional Services - System Settings</title>
    <?php include "includes/links.php"; ?>
    <style>
        .set-hero{background:linear-gradient(120deg,#0f172a 0%,#1456b8 70%,#3b82f6 100%);color:#fff;border-radius:14px;padding:20px 26px;margin-bottom:20px;box-shadow:0 8px 20px rgba(15,23,42,.3)}
        .set-hero h3{margin:0}.set-hero p{margin:6px 0 0;opacity:.92}
        .nav-pills .nav-link{font-weight:600;color:#475569;border-radius:8px;margin-bottom:2px}
        .nav-pills .nav-link.active{background:#1456b8;color:#fff}
        .nav-pills .nav-link i{margin-right:6px}
        .switch{position:relative;display:inline-block;width:52px;height:28px}
        .switch input{opacity:0;width:0;height:0}
        .slider{position:absolute;cursor:pointer;inset:0;background:#cbd5e1;transition:.3s;border-radius:34px}
        .slider:before{position:absolute;content:"";height:22px;width:22px;left:3px;top:3px;background:#fff;transition:.3s;border-radius:50%;box-shadow:0 1px 3px rgba(0,0,0,.3)}
        .switch input:checked+.slider{background:#1456b8}
        .switch input:checked+.slider:before{transform:translateX(24px)}
        .test-result{margin-top:10px;padding:10px 14px;border-radius:8px;display:none;font-size:14px}
        .test-result.ok{display:block;background:#dcfce7;color:#166534;border:1px solid #bbf7d0}
        .test-result.err{display:block;background:#fee2e2;color:#991b1b;border:1px solid #fecaca}
        .ep-output{background:#1e293b;color:#e2e8f0;padding:14px;border-radius:8px;font-family:monospace;font-size:12px;max-height:300px;overflow:auto;white-space:pre-wrap;display:none;margin-top:10px}
        .card .card-header h5{margin:0;font-size:16px}
    </style>
</head>
<body class="theme-3">
    <div class="loader-bg"><div class="loader-track"><div class="loader-fill"></div></div></div>
    <?php include "includes/sidebar.php"; ?>
    <?php include "includes/header.php"; ?>

    <div class="dash-container">
        <div class="dash-content">
            <div class="page-header">
                <div class="page-block">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <div class="page-header-title"><h4 class="m-b-10">System Settings</h4></div>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                <li class="breadcrumb-item">System Settings</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($msg !== ''): ?>
                <div class="alert alert-<?= $msgType ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($msg) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="set-hero">
                <h3><i class="ti ti-settings-cog"></i> System Settings</h3>
                <p>Configure every integration, API key, and deployment option from one place. All changes are idempotent and safe to re-apply.</p>
            </div>

            <div class="row">
                <!-- ── Tab nav ── -->
                <div class="col-md-3 mb-3">
                    <div class="nav flex-column nav-pills" id="settingsTabs" role="tablist">
                        <a class="nav-link <?= $tab==='sync'?'active':'' ?>" data-bs-toggle="pill" href="#tab-sync"><i class="ti ti-cloud-download"></i> Sync &amp; Deploy</a>
                        <a class="nav-link <?= $tab==='stripe'?'active':'' ?>" data-bs-toggle="pill" href="#tab-stripe"><i class="ti ti-credit-card"></i> Stripe</a>
                        <a class="nav-link <?= $tab==='smtp'?'active':'' ?>" data-bs-toggle="pill" href="#tab-smtp"><i class="ti ti-mail"></i> SMTP / Email</a>
                        <a class="nav-link <?= $tab==='twilio'?'active':'' ?>" data-bs-toggle="pill" href="#tab-twilio"><i class="ti ti-phone"></i> Twilio / SMS</a>
                        <a class="nav-link <?= $tab==='pusher'?'active':'' ?>" data-bs-toggle="pill" href="#tab-pusher"><i class="ti ti-broadcast"></i> Pusher</a>
                        <a class="nav-link <?= $tab==='company'?'active':'' ?>" data-bs-toggle="pill" href="#tab-company"><i class="ti ti-building"></i> Company Info</a>
                        <a class="nav-link <?= $tab==='payments'?'active':'' ?>" data-bs-toggle="pill" href="#tab-payments"><i class="ti ti-wallet"></i> Payment Methods</a>
                        <a class="nav-link <?= $tab==='appearance'?'active':'' ?>" data-bs-toggle="pill" href="#tab-appearance"><i class="ti ti-palette"></i> Appearance</a>
                    </div>
                </div>

                <!-- ── Tab content ── -->
                <div class="col-md-9">
                    <div class="tab-content">

                        <!-- ═══ SYNC & DEPLOY ═══ -->
                        <div class="tab-pane fade <?= $tab==='sync'?'show active':'' ?>" id="tab-sync">
                            <div class="card mb-3">
                                <div class="card-header"><h5><i class="ti ti-settings"></i> Sync Configuration</h5></div>
                                <div class="card-body">
                                    <form method="post">
                                        <div class="mb-3">
                                            <label class="form-label"><b>Office Master Server URL</b></label>
                                            <input type="text" class="form-control" name="sync_url" value="<?= esc($syncUrl) ?>" placeholder="http://192.168.15.29/crm/sync_export.php">
                                            <div class="form-text">Full URL to the sync_export.php endpoint on the master server.</div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-md-8">
                                                <label class="form-label"><b>Sync Token</b></label>
                                                <input type="text" class="form-control" name="sync_token" value="<?= esc($syncToken) ?>" placeholder="sps-sync-2026">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label"><b>Auto-sync on startup</b></label>
                                                <div class="d-flex align-items-center" style="height:38px">
                                                    <label class="switch"><input type="checkbox" name="sync_auto" value="1"<?= $syncAuto ? ' checked' : '' ?>><span class="slider"></span></label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <button type="submit" name="sync_save" value="1" class="btn btn-primary"><i class="ti ti-device-floppy"></i> Save Sync Settings</button>
                                            <button type="button" class="btn btn-outline-success" id="syncTestBtn"><i class="ti ti-flask"></i> Test Connection</button>
                                        </div>
                                        <div class="test-result" id="syncTestResult"></div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- ═══ DEPLOY ═══ -->
                        <div class="tab-pane fade <?= $tab==='sync'?'show active':'' ?>" id="tab-deploy" style="display:<?= $tab==='sync'?'block':'none' ?>">
                            <div class="card mb-3">
                                <div class="card-header"><h5><i class="ti ti-file-code"></i> Generate Sync Endpoint</h5></div>
                                <div class="card-body">
                                    <p class="text-muted small mb-3">Generate a <code>sync_export.php</code> file for ANY server or website. Deploy the downloaded file into the server's web root (e.g. <code>C:\xampp\htdocs\crm\sync_export.php</code>) and the desktop app can pull data from it.</p>
                                    <form method="post" id="epGenForm">
                                        <div class="row mb-2">
                                            <div class="col-md-4">
                                                <label class="form-label"><b>DB Host</b></label>
                                                <input type="text" class="form-control" name="ep_host" value="localhost">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label"><b>DB Port</b></label>
                                                <input type="text" class="form-control" name="ep_port" value="3306">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label"><b>DB User</b></label>
                                                <input type="text" class="form-control" name="ep_user" value="root">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label"><b>DB Pass</b></label>
                                                <input type="password" class="form-control" name="ep_pass" value="">
                                            </div>
                                        </div>
                                        <div class="row mb-2">
                                            <div class="col-md-5">
                                                <label class="form-label"><b>Database Name</b></label>
                                                <input type="text" class="form-control" name="ep_db" value="syedpro_app">
                                            </div>
                                            <div class="col-md-5">
                                                <label class="form-label"><b>mysqldump Path</b></label>
                                                <input type="text" class="form-control" name="ep_dump" value="C:\xampp\mysql\bin\mysqldump.exe">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label"><b>Full ZIP</b></label>
                                                <div class="d-flex align-items-center" style="height:38px">
                                                    <label class="switch"><input type="checkbox" name="ep_fullzip" value="1"><span class="slider"></span></label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label"><b>Sync Token</b></label>
                                            <input type="text" class="form-control" name="ep_token" value="<?= esc($syncToken) ?>">
                                            <div class="form-text">Must match the token in the desktop app's sync-config.ini.</div>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <button type="submit" name="ep_gen" value="1" class="btn btn-primary"><i class="ti ti-file-download"></i> Generate &amp; Download</button>
                                            <button type="submit" name="ep_download" value="1" class="btn btn-outline-secondary"><i class="ti ti-download"></i> Re-download Last Generated</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- ═══ STRIPE ═══ -->
                        <div class="tab-pane fade <?= $tab==='stripe'?'show active':'' ?>" id="tab-stripe">
                            <div class="card mb-3">
                                <div class="card-header"><h5><i class="ti ti-credit-card"></i> Stripe Payment Gateway</h5></div>
                                <div class="card-body">
                                    <form method="post">
                                        <div class="mb-3">
                                            <label class="form-label"><b>Secret Key</b></label>
                                            <input type="password" class="form-control" name="stripe_secret" value="<?= esc(env_get('STRIPE_SECRET_KEY')) ?>" placeholder="sk_live_..." autocomplete="off">
                                            <div class="form-text">Found at <a href="https://dashboard.stripe.com/apikeys" target="_blank">dashboard.stripe.com/apikeys</a>.</div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label"><b>Publishable Key</b></label>
                                            <input type="text" class="form-control" name="stripe_publishable" value="<?= esc(env_get('STRIPE_PUBLISHABLE_KEY')) ?>" placeholder="pk_live_..." autocomplete="off">
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-md-4">
                                                <label class="form-label"><b>Currency</b></label>
                                                <input type="text" class="form-control" name="stripe_currency" value="<?= esc(env_get('STRIPE_CURRENCY', 'usd')) ?>" maxlength="3" style="text-transform:lowercase">
                                            </div>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <button type="submit" name="stripe_save" value="1" class="btn btn-primary"><i class="ti ti-device-floppy"></i> Save Stripe Settings</button>
                                            <button type="button" class="btn btn-outline-success" id="stripeTestBtn"><i class="ti ti-flask"></i> Test Stripe</button>
                                        </div>
                                        <div class="test-result" id="stripeTestResult"></div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- ═══ SMTP ═══ -->
                        <div class="tab-pane fade <?= $tab==='smtp'?'show active':'' ?>" id="tab-smtp">
                            <div class="card mb-3">
                                <div class="card-header"><h5><i class="ti ti-mail"></i> SMTP / Email Settings</h5></div>
                                <div class="card-body">
                                    <p class="text-muted small mb-3">Configures the outgoing email account used by invoices, reminders, bulk email, and all notification scripts.</p>
                                    <form method="post" id="smtpForm">
                                        <div class="row mb-2">
                                            <div class="col-md-6">
                                                <label class="form-label"><b>From Email</b></label>
                                                <input type="email" class="form-control" name="smtp_from_email" value="<?= esc($smtp['from_email']) ?>" placeholder="invoice@syedpro.com">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label"><b>From Name</b></label>
                                                <input type="text" class="form-control" name="smtp_from_name" value="<?= esc($smtp['from_name']) ?>">
                                            </div>
                                        </div>
                                        <div class="row mb-2">
                                            <div class="col-md-5">
                                                <label class="form-label"><b>SMTP Host</b></label>
                                                <input type="text" class="form-control" name="smtp_host" value="<?= esc($smtp['host']) ?>">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label"><b>Port</b></label>
                                                <input type="number" class="form-control" name="smtp_port" value="<?= esc($smtp['port']) ?>">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label"><b>Security</b></label>
                                                <select class="form-select" name="smtp_secure">
                                                    <option value="tls"<?= $smtp['secure']==='tls'?' selected':'' ?>>TLS</option>
                                                    <option value="ssl"<?= $smtp['secure']==='ssl'?' selected':'' ?>>SSL</option>
                                                    <option value=""<?= $smtp['secure']===''?' selected':'' ?>>None</option>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label"><b>Username</b></label>
                                                <input type="text" class="form-control" name="smtp_username" value="<?= esc($smtp['username']) ?>">
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label"><b>Password</b></label>
                                                <input type="password" class="form-control" name="smtp_password" value="<?= esc($smtp['password']) ?>" autocomplete="off">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label"><b>Send test email to</b></label>
                                                <div class="input-group">
                                                    <input type="email" class="form-control" name="smtp_test_to" placeholder="you@example.com">
                                                    <button type="button" class="btn btn-outline-success" id="smtpTestBtn"><i class="ti ti-flask"></i> Test</button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <button type="submit" name="smtp_save" value="1" class="btn btn-primary"><i class="ti ti-device-floppy"></i> Save SMTP Settings</button>
                                        </div>
                                        <div class="test-result" id="smtpTestResult"></div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- ═══ TWILIO ═══ -->
                        <div class="tab-pane fade <?= $tab==='twilio'?'show active':'' ?>" id="tab-twilio">
                            <div class="card mb-3">
                                <div class="card-header"><h5><i class="ti ti-phone"></i> Twilio / SMS Settings</h5></div>
                                <div class="card-body">
                                    <form method="post" id="twilioForm">
                                        <div class="mb-3">
                                            <label class="form-label"><b>Account SID</b></label>
                                            <input type="text" class="form-control" name="twilio_account_sid" value="<?= esc($tw['TWILIO_ACCOUNT_SID']) ?>" placeholder="AC...">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label"><b>Auth Token</b></label>
                                            <input type="password" class="form-control" name="twilio_auth_token" value="<?= esc($tw['TWILIO_AUTH_TOKEN']) ?>" autocomplete="off">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label"><b>Phone Number</b></label>
                                            <input type="text" class="form-control" name="twilio_phone_number" value="<?= esc($tw['TWILIO_PHONE_NUMBER']) ?>" placeholder="+1...">
                                        </div>
                                        <div class="d-flex gap-2">
                                            <button type="submit" name="twilio_save" value="1" class="btn btn-primary"><i class="ti ti-device-floppy"></i> Save Twilio Settings</button>
                                            <button type="button" class="btn btn-outline-success" id="twilioTestBtn"><i class="ti ti-flask"></i> Test Twilio</button>
                                        </div>
                                        <div class="test-result" id="twilioTestResult"></div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- ═══ PUSHER ═══ -->
                        <div class="tab-pane fade <?= $tab==='pusher'?'show active':'' ?>" id="tab-pusher">
                            <div class="card mb-3">
                                <div class="card-header"><h5><i class="ti ti-broadcast"></i> Pusher / Real-time Events</h5></div>
                                <div class="card-body">
                                    <form method="post" id="pusherForm">
                                        <div class="row mb-2">
                                            <div class="col-md-6">
                                                <label class="form-label"><b>App ID</b></label>
                                                <input type="text" class="form-control" name="pusher_app_id" value="<?= esc(env_get('PUSHER_APP_ID')) ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label"><b>Cluster</b></label>
                                                <input type="text" class="form-control" name="pusher_cluster" value="<?= esc(env_get('PUSHER_CLUSTER')) ?>" placeholder="us2">
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label"><b>Key</b></label>
                                                <input type="text" class="form-control" name="pusher_key" value="<?= esc(env_get('PUSHER_KEY')) ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label"><b>Secret</b></label>
                                                <input type="password" class="form-control" name="pusher_secret" value="<?= esc(env_get('PUSHER_SECRET')) ?>" autocomplete="off">
                                            </div>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <button type="submit" name="pusher_save" value="1" class="btn btn-primary"><i class="ti ti-device-floppy"></i> Save Pusher Settings</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- ═══ COMPANY ═══ -->
                        <div class="tab-pane fade <?= $tab==='company'?'show active':'' ?>" id="tab-company">
                            <div class="card mb-3">
                                <div class="card-header"><h5><i class="ti ti-building"></i> Company Information</h5></div>
                                <div class="card-body">
                                    <form method="post">
                                        <div class="row mb-2">
                                            <div class="col-md-6"><label class="form-label"><b>Phone</b></label><input type="text" class="form-control" name="cp_phone" value="<?= esc($bc['phone']) ?>"></div>
                                            <div class="col-md-6"><label class="form-label"><b>Fax</b></label><input type="text" class="form-control" name="cp_fax" value="<?= esc($bc['fax']) ?>"></div>
                                        </div>
                                        <div class="row mb-2">
                                            <div class="col-md-6"><label class="form-label"><b>Email</b></label><input type="email" class="form-control" name="cp_email" value="<?= esc($bc['email']) ?>"></div>
                                            <div class="col-md-6"><label class="form-label"><b>Website</b></label><input type="text" class="form-control" name="cp_website" value="<?= esc($bc['website']) ?>"></div>
                                        </div>
                                        <div class="mb-2"><label class="form-label"><b>Street</b></label><input type="text" class="form-control" name="cp_street" value="<?= esc($bc['street']) ?>"></div>
                                        <div class="row mb-3">
                                            <div class="col-md-5"><label class="form-label"><b>City</b></label><input type="text" class="form-control" name="cp_city" value="<?= esc($bc['city']) ?>"></div>
                                            <div class="col-md-2"><label class="form-label"><b>State</b></label><input type="text" class="form-control" name="cp_state" value="<?= esc($bc['state']) ?>"></div>
                                            <div class="col-md-5"><label class="form-label"><b>ZIP</b></label><input type="text" class="form-control" name="cp_zip" value="<?= esc($bc['zip']) ?>"></div>
                                        </div>
                                        <button type="submit" name="company_save" value="1" class="btn btn-primary"><i class="ti ti-device-floppy"></i> Save Company Info</button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- ═══ PAYMENT METHODS ═══ -->
                        <div class="tab-pane fade <?= $tab==='payments'?'show active':'' ?>" id="tab-payments">
                            <div class="card mb-3">
                                <div class="card-header"><h5><i class="ti ti-wallet"></i> Payment Method Names</h5></div>
                                <div class="card-body">
                                    <form method="post">
                                        <div id="payRows">
                                        <?php foreach ($payTypes as $pt): ?>
                                            <div class="row mb-2 pay-row">
                                                <input type="hidden" name="pay_id[]" value="<?= (int)$pt['payment_type_id'] ?>">
                                                <div class="col-md-10"><input type="text" class="form-control" name="pay_name[]" value="<?= esc($pt['payment_type_detail']) ?>"></div>
                                                <div class="col-md-2"><button type="button" class="btn btn-outline-danger btn-sm remove-pay"><i class="ti ti-trash"></i></button></div>
                                            </div>
                                        <?php endforeach; ?>
                                        </div>
                                        <div class="d-flex gap-2 mt-2">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" id="addPay"><i class="ti ti-plus"></i> Add Method</button>
                                        </div>
                                        <div class="mt-3">
                                            <button type="submit" name="pay_save" value="1" class="btn btn-primary"><i class="ti ti-device-floppy"></i> Save Payment Methods</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- ═══ APPEARANCE (THEMES) ═══ -->
                        <div class="tab-pane fade <?= $tab==='appearance'?'show active':'' ?>" id="tab-appearance">
                            <style>
                            .th-pick{display:flex;flex-wrap:wrap;gap:10px}
                            .th-swat{width:52px;height:52px;border-radius:14px;cursor:pointer;border:3px solid transparent;position:relative}
                            .th-swat input{position:absolute;opacity:0;pointer-events:none}
                            .th-swat.sel{border-color:#1c232f;box-shadow:0 0 0 3px #fff,0 0 0 5px #1c232f}
                            .th-opt{border:2px solid #e2e6f0;border-radius:12px;padding:10px 14px;cursor:pointer;font-size:13px;font-weight:600}
                            .th-opt.sel{border-color:#1456b8;background:#eef3ff}
                            .th-opt input{display:none}
                            </style>
                            <style>
                            .th-pick{display:flex;flex-wrap:wrap;gap:10px}.th-swat{width:52px;height:52px;border-radius:14px;cursor:pointer;border:3px solid transparent;position:relative}.th-swat input{position:absolute;opacity:0;pointer-events:none}.th-swat.sel{border-color:#1c232f;box-shadow:0 0 0 3px #fff,0 0 0 5px #1c232f}.th-opt{border:2px solid #e2e6f0;border-radius:12px;padding:10px 14px;cursor:pointer;font-size:13px;font-weight:600}.th-opt.sel{border-color:#1456b8;background:#eef3ff}.th-opt input{display:none}
                            .preset-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(148px,1fr));gap:12px}
                            .preset-card{cursor:pointer;border:3px solid #e5e7eb;border-radius:14px;padding:10px 12px 12px;position:relative;transition:border-color .15s,box-shadow .15s}
                            .preset-card input{position:absolute;opacity:0;pointer-events:none}
                            .preset-card.sel{border-color:#1456b8;box-shadow:0 0 0 3px #eef3ff,0 0 0 5px #1456b8}
                            .preset-card .pc-prev{height:52px;border-radius:8px;border:1px solid #e5e7eb;overflow:hidden;display:flex;margin-bottom:8px}
                            .preset-card .pc-sidebar{width:20%;height:100%}
                            .preset-card .pc-body{flex:1;display:flex;flex-direction:column;gap:6px;padding:7px 8px}
                            .preset-card .pc-dot{width:14px;height:14px;border-radius:50%;margin-top:auto}
                            .preset-card .pc-bar{height:5px;border-radius:3px}
                            .preset-card .pc-name{font-size:12.5px;font-weight:700}
                            .preset-card .pc-tag{font-size:11px;color:#6b7280;line-height:1.25}
                            .th-sec{border-top:1px solid #eef0f4;padding-top:16px;margin-top:18px}
                            .th-row{display:flex;gap:20px;flex-wrap:wrap}
                            .th-row>div{flex:1;min-width:180px}
                            .th-row label.form-label{margin-bottom:6px}
                            .preset-card[data-preset="classic"] .pc-sidebar{background:#ffffff;border-right:1px solid #e6ebe8}.preset-card[data-preset="classic"] .pc-body{background:#f0f4f3}.preset-card[data-preset="classic"] .pc-dot{background:#6FD943}.preset-card[data-preset="classic"] .pc-bar{background:#6FD943}
                            .preset-card[data-preset="sleek"] .pc-sidebar{background:#ffffff;border-right:1px solid #e3e8ef;border-radius:8px !important}.preset-card[data-preset="sleek"] .pc-prev{background:#f4f6fa;padding:6px}.preset-card[data-preset="sleek"] .pc-sidebar{border:1px solid #e3e8ef;border-radius:6px}.preset-card[data-preset="sleek"] .pc-body{background:#f4f6fa;border:1px solid #e3e8ef;border-radius:6px}.preset-card[data-preset="sleek"] .pc-dot{background:#2f7cf6}.preset-card[data-preset="sleek"] .pc-bar{background:#2f7cf6}
                            .preset-card[data-preset="modern"] .pc-sidebar{background:#ffffff;border-right:1px solid #e7eaf3}.preset-card[data-preset="modern"] .pc-body{background:#f3f5fb}.preset-card[data-preset="modern"] .pc-dot{background:#4361ee}.preset-card[data-preset="modern"] .pc-bar{background:#4361ee}
                            .preset-card[data-preset="executive"] .pc-sidebar{background:#14263B}.preset-card[data-preset="executive"] .pc-body{background:#f1eee6}.preset-card[data-preset="executive"] .pc-dot{background:#B08D3E}.preset-card[data-preset="executive"] .pc-bar{background:#B08D3E}
                            .preset-card[data-preset="night"] .pc-sidebar{background:#181511}.preset-card[data-preset="night"] .pc-body{background:#14120e}.preset-card[data-preset="night"] .pc-dot{background:#D4A553}.preset-card[data-preset="night"] .pc-bar{background:#D4A553}
                            .preset-card[data-preset="midnight"] .pc-sidebar{background:#0d1322}.preset-card[data-preset="midnight"] .pc-body{background:#0a0e18}.preset-card[data-preset="midnight"] .pc-dot{background:#38bdf8}.preset-card[data-preset="midnight"] .pc-bar{background:#38bdf8}
                            .preset-card[data-preset="aurora"] .pc-sidebar{background:rgba(10,14,26,.82)}.preset-card[data-preset="aurora"] .pc-body{background:linear-gradient(135deg,#0d1126,#0a0e1a)}.preset-card[data-preset="aurora"] .pc-dot{background:#8B5CF6}.preset-card[data-preset="aurora"] .pc-bar{background:#8B5CF6}
                            .preset-card[data-preset="neon"] .pc-sidebar{background:#0c0c18}.preset-card[data-preset="neon"] .pc-body{background:#08080f}.preset-card[data-preset="neon"] .pc-dot{background:#00E5FF;box-shadow:0 0 8px rgba(0,229,255,.6)}.preset-card[data-preset="neon"] .pc-bar{background:#00E5FF;box-shadow:0 0 6px rgba(0,229,255,.4)}
                            .preset-card[data-preset="custom"] .pc-sidebar{background:#fff;border-right:1px solid #e5e7eb}.preset-card[data-preset="custom"] .pc-body{background:#f9fafb}.preset-card[data-preset="custom"] .pc-dot{background:conic-gradient(#ff3a6e,#4361ee,#6FD943,#B08D3E,#ff3a6e)}.preset-card[data-preset="custom"] .pc-bar{background:linear-gradient(90deg,#ff3a6e,#4361ee,#6FD943,#B08D3E,#922C88)}
                            .hex-input{width:72px;padding:6px 8px;text-align:center;font-weight:700;border:2px solid #e2e6f0;border-radius:10px;letter-spacing:.5px}
                            .hex-input:focus{outline:none;border-color:#1456b8}
                            </style>
                            <form method="post" id="appearanceForm">
                            <div class="card mb-3">
                                <div class="card-header"><h5><i class="ti ti-palette"></i> Theme Presets</h5></div>
                                <div class="card-body">
                                    <p class="text-muted small mb-3">Pick a visual preset. Your accent color, density, and effects carry over — every change previews live.</p>
                                    <?php $presets = sps_theme_presets(); $pKeys = ['classic','sleek','modern','executive','night','midnight','aurora','neon','custom']; ?>
                                    <div class="preset-grid">
                                    <?php foreach ($pKeys as $pk): $p = $presets[$pk]; ?>
                                        <label class="preset-card <?= $thCfg['preset']===$pk?'sel':'' ?>" data-preset="<?= $pk ?>">
                                            <input type="radio" name="theme_preset" value="<?= $pk ?>" <?= $thCfg['preset']===$pk?'checked':'' ?>>
                                            <div class="pc-prev"><div class="pc-sidebar"></div><div class="pc-body"><div class="pc-bar"></div><div class="pc-dot"></div></div></div>
                                            <div class="pc-name"><?= esc($p['name']) ?></div>
                                            <div class="pc-tag"><?= esc($p['tag']) ?></div>
                                        </label>
                                    <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="card mb-3">
                                <div class="card-header"><h5><i class="ti ti-typography"></i> Typography & Text</h5></div>
                                <div class="card-body">
                                    <div class="th-row">
                                        <div>
                                            <label class="form-label fw-semibold">Font family</label>
                                            <select name="theme_font_family" class="form-select">
                                                <?php
                                                $famOpts = ['system'=>'System Default', 'open-sans'=>'Open Sans', 'inter'=>'Inter', 'poppins'=>'Poppins', 'roboto'=>'Roboto', 'nunito'=>'Nunito'];
                                                foreach ($famOpts as $fv => $fl): ?>
                                                <option value="<?= $fv ?>" <?= $thCfg['font_family']===$fv?'selected':'' ?>><?= $fl ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="form-label fw-semibold">Base text size</label>
                                            <select name="theme_font_size" class="form-select">
                                                <?php
                                                $szOpts = ['xs'=>'Small · 12px', 'sm'=>'Default small · 13px', 'normal'=>'Default · 14px', 'lg'=>'Large · 15.5px', 'xl'=>'Extra large · 17px'];
                                                foreach ($szOpts as $sv => $sl): ?>
                                                <option value="<?= $sv ?>" <?= $thCfg['font_size']===$sv?'selected':'' ?>><?= $sl ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="form-label fw-semibold">Heading weight</label>
                                            <select name="theme_heading_weight" class="form-select">
                                                <?php
                                                $hwOpts = ['300'=>'Light · 300', '400'=>'Regular · 400', '500'=>'Medium · 500', '600'=>'Semibold · 600', '700'=>'Bold · 700'];
                                                foreach ($hwOpts as $hv => $hl): ?>
                                                <option value="<?= $hv ?>" <?= $thCfg['heading_weight']===(string)$hv?'selected':'' ?>><?= $hl ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="th-row mt-3">
                                        <div>
                                            <label class="form-label fw-semibold">Line height</label>
                                            <div class="th-pick mb-2">
                                                <label class="th-opt <?= $thCfg['line_height']==='tight'?'sel':'' ?>"><input type="radio" name="theme_line_height" value="tight" <?= $thCfg['line_height']==='tight'?'checked':'' ?>> Tight</label>
                                                <label class="th-opt <?= $thCfg['line_height']==='normal'?'sel':'' ?>"><input type="radio" name="theme_line_height" value="normal" <?= $thCfg['line_height']==='normal'?'checked':'' ?>> Normal</label>
                                                <label class="th-opt <?= $thCfg['line_height']==='relaxed'?'sel':'' ?>"><input type="radio" name="theme_line_height" value="relaxed" <?= $thCfg['line_height']==='relaxed'?'checked':'' ?>> Relaxed</label>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="form-label fw-semibold">Letter spacing</label>
                                            <div class="th-pick mb-2">
                                                <label class="th-opt <?= $thCfg['letter_spacing']==='tight'?'sel':'' ?>"><input type="radio" name="theme_letter_spacing" value="tight" <?= $thCfg['letter_spacing']==='tight'?'checked':'' ?>> Tight</label>
                                                <label class="th-opt <?= $thCfg['letter_spacing']==='normal'?'sel':'' ?>"><input type="radio" name="theme_letter_spacing" value="normal" <?= $thCfg['letter_spacing']==='normal'?'checked':'' ?>> Normal</label>
                                                <label class="th-opt <?= $thCfg['letter_spacing']==='wide'?'sel':'' ?>"><input type="radio" name="theme_letter_spacing" value="wide" <?= $thCfg['letter_spacing']==='wide'?'checked':'' ?>> Wide</label>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="form-label fw-semibold">Text mode</label>
                                            <div class="th-pick mb-2">
                                                <label class="th-opt <?= $thCfg['font']==='normal'?'sel':'' ?>"><input type="radio" name="theme_font" value="normal" <?= $thCfg['font']==='normal'?'checked':'' ?>> Normal</label>
                                                <label class="th-opt <?= $thCfg['font']==='large'?'sel':'' ?>"><input type="radio" name="theme_font" value="large" <?= $thCfg['font']==='large'?'checked':'' ?>> Boosted</label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="th-sec">
                                        <label class="form-label fw-semibold">Text colors — leave a color empty to inherit the preset</label>
                                        <div class="row g-3">
                                            <div class="col-sm-6 col-lg-4">
                                                <label class="form-label small">Headings</label>
                                                <div class="d-flex align-items-center gap-2">
                                                    <input type="color" name="theme_heading_color" value="<?= $thCfg['heading_color'] ?: '#060606' ?>" class="form-control form-control-color form-control-sm" style="width:36px;height:36px;border-radius:10px;border:2px solid #e2e6f0">
                                                    <span class="text-muted small">default <code>#060606</code></span>
                                                </div>
                                            </div>
                                            <div class="col-sm-6 col-lg-4">
                                                <label class="form-label small">Links</label>
                                                <div class="d-flex align-items-center gap-2">
                                                    <input type="color" name="theme_link_color" value="<?= $thCfg['link_color'] ?: '#51459d' ?>" class="form-control form-control-color form-control-sm" style="width:36px;height:36px;border-radius:10px;border:2px solid #e2e6f0">
                                                    <span class="text-muted small">default <code>#51459d</code></span>
                                                </div>
                                            </div>
                                            <div class="col-sm-6 col-lg-4">
                                                <label class="form-label small">Link hover</label>
                                                <div class="d-flex align-items-center gap-2">
                                                    <input type="color" name="theme_link_hover" value="<?= $thCfg['link_hover'] ?: '#51459d' ?>" class="form-control form-control-color form-control-sm" style="width:36px;height:36px;border-radius:10px;border:2px solid #e2e6f0">
                                                    <span class="text-muted small">default <code>#51459d</code></span>
                                                </div>
                                            </div>
                                            <div class="col-sm-6 col-lg-4">
                                                <label class="form-label small">Muted / secondary</label>
                                                <div class="d-flex align-items-center gap-2">
                                                    <input type="color" name="theme_muted_color" value="<?= $thCfg['muted_color'] ?: '#6c757d' ?>" class="form-control form-control-color form-control-sm" style="width:36px;height:36px;border-radius:10px;border:2px solid #e2e6f0">
                                                    <span class="text-muted small">default <code>#6c757d</code></span>
                                                </div>
                                            </div>
                                            <div class="col-sm-6 col-lg-4">
                                                <label class="form-label small">Code</label>
                                                <div class="d-flex align-items-center gap-2">
                                                    <input type="color" name="theme_code_color" value="<?= $thCfg['code_color'] ?: '#e83e8c' ?>" class="form-control form-control-color form-control-sm" style="width:36px;height:36px;border-radius:10px;border:2px solid #e2e6f0">
                                                    <span class="text-muted small">default <code>#e83e8c</code></span>
                                                </div>
                                            </div>
                                        </div>
                                        <p class="text-muted small mt-2 mb-0"><i class="ti ti-info-circle"></i> Every picker ships with a "default" hint — an empty stored value falls back to the active preset's palette.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="card mb-3">
                                <div class="card-header"><h5><i class="ti ti-adjust"></i> Customize Everything</h5></div>
                                <div class="card-body">
                                    <label class="form-label fw-semibold">Accent color</label>
                                    <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                                        <div class="d-flex gap-1">
                                        <?php $thColors = [1=>'#0CAF60',2=>'#584ED2',3=>'#6FD943',4=>'#145388',5=>'#B9406B',6=>'#008ECC',7=>'#922C88',8=>'#C0A145',9=>'#48494B'];
                                        $thNames  = sps_theme_accent_names();
                                        foreach ($thColors as $n => $hex): ?>
                                            <label class="th-swat <?= (int)$thCfg['accent']===$n?'sel':'' ?>" title="<?= esc($thNames[$n]) ?>" style="background:<?= $hex ?>">
                                                <input type="radio" name="theme_accent" value="<?= $n ?>" <?= (int)$thCfg['accent']===$n?'checked':'' ?>>
                                            </label>
                                        <?php endforeach; ?>
                                        </div>
                                        <div class="d-flex align-items-center gap-1">
                                            <label class="form-label mb-0 small fw-semibold">Custom</label>
                                            <input type="color" name="theme_accent_custom" value="<?= $thCfg['accent_custom'] ?: '#ffffff' ?>" class="form-control form-control-color form-control-sm p-0" style="width:34px;height:34px;border-radius:10px;border:2px solid #e2e6f0" title="Pick any custom accent">
                                        </div>
                                    </div>

                                    <div class="th-sec">
                                        <label class="form-label fw-semibold">Surface colors — leave empty to auto-adapt to the active theme</label>
                                        <div class="row g-3">
                                            <div class="col-sm-6 col-lg-4">
                                                <label class="form-label small">Cards & panels</label>
                                                <div class="d-flex align-items-center gap-2">
                                                    <input type="color" name="theme_card_bg" value="<?= $thCfg['card_bg'] ?: '#ffffff' ?>" class="form-control form-control-color form-control-sm" style="width:36px;height:36px;border-radius:10px;border:2px solid #e2e6f0">
                                                    <span class="text-muted small">auto</span>
                                                </div>
                                            </div>
                                            <div class="col-sm-6 col-lg-4">
                                                <label class="form-label small">Sidebar background</label>
                                                <div class="d-flex align-items-center gap-2">
                                                    <input type="color" name="theme_sidebar_bg_c" value="<?= $thCfg['sidebar_bg_c'] ?: '#ffffff' ?>" class="form-control form-control-color form-control-sm" style="width:36px;height:36px;border-radius:10px;border:2px solid #e2e6f0">
                                                    <span class="text-muted small">auto</span>
                                                </div>
                                            </div>
                                            <div class="col-sm-6 col-lg-4">
                                                <label class="form-label small">Header background</label>
                                                <div class="d-flex align-items-center gap-2">
                                                    <input type="color" name="theme_header_bg_c" value="<?= $thCfg['header_bg_c'] ?: '#ffffff' ?>" class="form-control form-control-color form-control-sm" style="width:36px;height:36px;border-radius:10px;border:2px solid #e2e6f0">
                                                    <span class="text-muted small">auto</span>
                                                </div>
                                            </div>
                                        </div>
                                        <p class="text-muted small mt-2 mb-2"><i class="ti ti-info-circle"></i> These override the preset on top — the layout, icons, and hover behavior stay fully theme-aware.</p>
                                    </div>

                                    <div class="th-sec">
                                    <div class="th-row">
                                        <div>
                                            <label class="form-label fw-semibold">Background</label>
                                            <div class="th-pick mb-2">
                                                <label class="th-opt <?= $thCfg['bg']==='light'?'sel':'' ?>"><input type="radio" name="theme_bg" value="light" <?= $thCfg['bg']==='light'?'checked':'' ?>> Light</label>
                                                <label class="th-opt <?= $thCfg['bg']==='dark'?'sel':'' ?>"><input type="radio" name="theme_bg" value="dark" <?= $thCfg['bg']==='dark'?'checked':'' ?>> Dark</label>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="form-label fw-semibold">Sidebar</label>
                                            <div class="th-pick mb-2">
                                                <label class="th-opt <?= $thCfg['sidebar']==='auto'?'sel':'' ?>"><input type="radio" name="theme_sidebar" value="auto" <?= $thCfg['sidebar']==='auto'?'checked':'' ?>> Auto</label>
                                                <label class="th-opt <?= $thCfg['sidebar']==='light'?'sel':'' ?>"><input type="radio" name="theme_sidebar" value="light" <?= $thCfg['sidebar']==='light'?'checked':'' ?>> Light</label>
                                                <label class="th-opt <?= $thCfg['sidebar']==='dark'?'sel':'' ?>"><input type="radio" name="theme_sidebar" value="dark" <?= $thCfg['sidebar']==='dark'?'checked':'' ?>> Dark</label>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="form-label fw-semibold">Header</label>
                                            <div class="th-pick mb-2">
                                                <label class="th-opt <?= $thCfg['header']==='auto'?'sel':'' ?>"><input type="radio" name="theme_header" value="auto" <?= $thCfg['header']==='auto'?'checked':'' ?>> Auto</label>
                                                <label class="th-opt <?= $thCfg['header']==='light'?'sel':'' ?>"><input type="radio" name="theme_header" value="light" <?= $thCfg['header']==='light'?'checked':'' ?>> Light</label>
                                                <label class="th-opt <?= $thCfg['header']==='dark'?'sel':'' ?>"><input type="radio" name="theme_header" value="dark" <?= $thCfg['header']==='dark'?'checked':'' ?>> Dark</label>
                                                <label class="th-opt <?= $thCfg['header']==='transparent'?'sel':'' ?>"><input type="radio" name="theme_header" value="transparent" <?= $thCfg['header']==='transparent'?'checked':'' ?>> Clear</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="th-row">
                                        <div>
                                            <label class="form-label fw-semibold">Corners</label>
                                            <div class="th-pick mb-2">
                                                <label class="th-opt <?= $thCfg['radius']==='default'?'sel':'' ?>"><input type="radio" name="theme_radius" value="default" <?= $thCfg['radius']==='default'?'checked':'' ?>> Default</label>
                                                <label class="th-opt <?= $thCfg['radius']==='rounded'?'sel':'' ?>"><input type="radio" name="theme_radius" value="rounded" <?= $thCfg['radius']==='rounded'?'checked':'' ?>> Rounded</label>
                                                <label class="th-opt <?= $thCfg['radius']==='square'?'sel':'' ?>"><input type="radio" name="theme_radius" value="square" <?= $thCfg['radius']==='square'?'checked':'' ?>> Square</label>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="form-label fw-semibold">Density</label>
                                            <div class="th-pick mb-2">
                                                <label class="th-opt <?= $thCfg['compact']==='0'?'sel':'' ?>"><input type="radio" name="theme_compact" value="0" <?= $thCfg['compact']==='0'?'checked':'' ?>> Standard</label>
                                                <label class="th-opt <?= $thCfg['compact']==='1'?'sel':'' ?>"><input type="radio" name="theme_compact" value="1" <?= $thCfg['compact']==='1'?'checked':'' ?>> Compact</label>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="form-label fw-semibold">Text size</label>
                                            <div class="th-pick mb-2">
                                                <label class="th-opt <?= $thCfg['font']==='normal'?'sel':'' ?>"><input type="radio" name="theme_font" value="normal" <?= $thCfg['font']==='normal'?'checked':'' ?>> Normal</label>
                                                <label class="th-opt <?= $thCfg['font']==='large'?'sel':'' ?>"><input type="radio" name="theme_font" value="large" <?= $thCfg['font']==='large'?'checked':'' ?>> Large</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="th-row">
                                        <div>
                                            <label class="form-label fw-semibold">Effects master</label>
                                            <div class="th-pick mb-2">
                                                <label class="th-opt <?= $thCfg['effects']==='1'?'sel':'' ?>"><input type="radio" name="theme_effects" value="1" <?= $thCfg['effects']==='1'?'checked':'' ?>> On</label>
                                                <label class="th-opt <?= $thCfg['effects']==='0'?'sel':'' ?>"><input type="radio" name="theme_effects" value="0" <?= $thCfg['effects']==='0'?'checked':'' ?>> Off</label>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="form-label fw-semibold">Cursor style</label>
                                            <div class="th-pick mb-2">
                                                <label class="th-opt <?= $thCfg['cursor']==='off'?'sel':'' ?>"><input type="radio" name="theme_cursor" value="off" <?= $thCfg['cursor']==='off'?'checked':'' ?>> Off</label>
                                                <label class="th-opt <?= $thCfg['cursor']==='dot'?'sel':'' ?>"><input type="radio" name="theme_cursor" value="dot" <?= $thCfg['cursor']==='dot'?'checked':'' ?>> Dot</label>
                                                <label class="th-opt <?= $thCfg['cursor']==='ring'?'sel':'' ?>"><input type="radio" name="theme_cursor" value="ring" <?= $thCfg['cursor']==='ring'?'checked':'' ?>> Ring</label>
                                                <label class="th-opt <?= $thCfg['cursor']==='aura'?'sel':'' ?>"><input type="radio" name="theme_cursor" value="aura" <?= $thCfg['cursor']==='aura'?'checked':'' ?>> Aura</label>
                                                <label class="th-opt <?= $thCfg['cursor']==='cross'?'sel':'' ?>"><input type="radio" name="theme_cursor" value="cross" <?= $thCfg['cursor']==='cross'?'checked':'' ?>> Cross</label>
                                                <label class="th-opt <?= $thCfg['cursor']==='comet'?'sel':'' ?>"><input type="radio" name="theme_cursor" value="comet" <?= $thCfg['cursor']==='comet'?'checked':'' ?>> Comet</label>
                                                <label class="th-opt <?= $thCfg['cursor']==='pulse'?'sel':'' ?>"><input type="radio" name="theme_cursor" value="pulse" <?= $thCfg['cursor']==='pulse'?'checked':'' ?>> Pulse</label>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="form-label fw-semibold">Page animation</label>
                                            <div class="th-pick mb-2">
                                                <label class="th-opt <?= $thCfg['motion']==='on'?'sel':'' ?>"><input type="radio" name="theme_motion" value="on" <?= $thCfg['motion']==='on'?'checked':'' ?>> On</label>
                                                <label class="th-opt <?= $thCfg['motion']==='off'?'sel':'' ?>"><input type="radio" name="theme_motion" value="off" <?= $thCfg['motion']==='off'?'checked':'' ?>> Off</label>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="form-label fw-semibold">Glow / Neon</label>
                                            <div class="th-pick mb-2">
                                                <label class="th-opt <?= $thCfg['glow']==='1'?'sel':'' ?>"><input type="radio" name="theme_glow" value="1" <?= $thCfg['glow']==='1'?'checked':'' ?>> On</label>
                                                <label class="th-opt <?= $thCfg['glow']==='0'?'sel':'' ?>"><input type="radio" name="theme_glow" value="0" <?= $thCfg['glow']==='0'?'checked':'' ?>> Off</label>
                                            </div>
                                        </div>
                                    </div>
                                    </div>
                                    <div class="mt-3">
                                        <button type="submit" name="theme_save" value="1" class="btn btn-primary"><i class="ti ti-device-floppy"></i> Save Appearance</button>
                                    </div>
                                </div>
                            </div>
                            </form>
                            <script>
                            (function(){
                                try{
                                var form=document.getElementById('appearanceForm'); if(!form)return;
                                var PS={classic:{mode:'light',accent:'#6FD943',bg:'#f0f4f3',surface:'#fff',surface2:'#f7f9f8',surface3:'#eef2f0',border:'#e6ebe8',sidebar_bg:'#fff',sidebar_text:'#1f2328',header_bg:'transparent',shadow:'0 2px 6px rgba(16,32,28,.05)',grad:'linear-gradient(141.55deg, rgba(240,244,243,0) 3.46%, #f0f4f3 99.86%)',glass:false},
                                modern:{mode:'light',accent:'#4361ee',bg:'#f3f5fb',surface:'#fff',surface2:'#f7f8fc',surface3:'#edf0f8',border:'#e7eaf3',sidebar_bg:'#fff',sidebar_text:'#1a1f33',header_bg:'transparent',shadow:'0 1px 2px rgba(17,24,60,.05),0 10px 28px rgba(17,24,60,.07)',grad:'radial-gradient(1200px 500px at 100% -10%, rgba(67,97,238,.06), transparent), linear-gradient(160deg,#f5f6fc 0%, #eef1fa 100%)',glass:false},
                                sleek:{mode:'light',accent:'#2f7cf6',bg:'#f4f6fa',surface:'#fff',surface2:'#f8fafc',surface3:'#eef2f7',border:'#e3e8ef',sidebar_bg:'#fff',sidebar_text:'#27313f',header_bg:'transparent',shadow:'0 1px 2px rgba(15,32,60,.05),0 12px 30px rgba(15,32,60,.06)',grad:'linear-gradient(160deg,#f6f8fb 0%, #eef3f9 100%)',glass:false},
                                executive:{mode:'light',accent:'#B08D3E',bg:'#f1eee6',surface:'#fdfcf8',surface2:'#f7f4ec',surface3:'#efe9dc',border:'#e2dac6',sidebar_bg:'#14263B',sidebar_text:'#c9d4e2',header_bg:'transparent',shadow:'0 1px 2px rgba(70,55,20,.10),0 14px 34px rgba(70,55,20,.10)',grad:'linear-gradient(150deg, #f4f1e9 0%, #ece8dc 100%)',glass:false},
                                night:{mode:'dark',accent:'#D4A553',bg:'#14120e',surface:'#1b1812',surface2:'#211d15',surface3:'#292418',border:'#3a3224',sidebar_bg:'#181511',sidebar_text:'#d0c9b9',header_bg:'rgba(27,24,18,.92)',shadow:'0 1px 2px rgba(0,0,0,.35),0 16px 44px rgba(0,0,0,.45)',grad:'linear-gradient(160deg, #181510 0%, #121009 100%)',glass:false},
                                midnight:{mode:'dark',accent:'#38bdf8',bg:'#0a0e18',surface:'#111827',surface2:'#16202f',surface3:'#1c2938',border:'#273445',sidebar_bg:'#0d1322',sidebar_text:'#c7d3e6',header_bg:'rgba(17,24,39,.92)',shadow:'0 1px 2px rgba(0,0,0,.4),0 18px 48px rgba(0,0,0,.5)',grad:'radial-gradient(900px 420px at 15% -5%, rgba(56,189,248,.10), transparent), linear-gradient(165deg,#0b1020 0%, #080b14 100%)',glass:false},
                                aurora:{mode:'dark',accent:'#8B5CF6',bg:'#0a0e1a',surface:'rgba(20,26,48,.60)',surface2:'rgba(26,34,60,.55)',surface3:'rgba(32,42,72,.70)',border:'rgba(150,170,230,.18)',sidebar_bg:'rgba(10,14,26,.86)',sidebar_text:'#d9e0f2',header_bg:'rgba(13,18,32,.55)',shadow:'0 8px 32px rgba(0,0,0,.45)',grad:'radial-gradient(1100px 600px at 85% -10%, rgba(139,92,246,.16), transparent 60%), radial-gradient(900px 500px at -10% 110%, rgba(56,189,248,.12), transparent 60%), #0a0e1a',glass:true},
                                neon:{mode:'dark',accent:'#00E5FF',bg:'#08080f',surface:'#12121f',surface2:'#191929',surface3:'#202034',border:'#2c2c4a',sidebar_bg:'#0c0c18',sidebar_text:'#d6d6ef',header_bg:'rgba(18,18,31,.92)',shadow:'0 1px 2px rgba(0,0,0,.5),0 0 0 1px rgba(0,229,255,.06),0 20px 60px rgba(0,0,0,.55)',grad:'linear-gradient(165deg, #0b0b18 0%, #07070d 100%)',glass:false},
                                custom:{mode:'light',accent:'#6FD943',bg:'#f0f4f3',surface:'#fff',surface2:'#f7f9f8',surface3:'#eef2f0',border:'#e6ebe8',sidebar_bg:'#fff',sidebar_text:'#1f2328',header_bg:'transparent',shadow:'0 2px 6px rgba(16,32,28,.05)',grad:'linear-gradient(141.55deg, rgba(240,244,243,0) 3.46%, #f0f4f3 99.86%)',glass:false}
                                };
                                var N={1:'#0CAF60',2:'#584ED2',3:'#6FD943',4:'#145388',5:'#B9406B',6:'#008ECC',7:'#922C88',8:'#C0A145',9:'#48494B'};
                                function hx2rgb(h){h=h.replace('#','');if(h.length===3)h=h[0]+h[0]+h[1]+h[1]+h[2]+h[2];return{r:parseInt(h.substr(0,2),16),g:parseInt(h.substr(2,2),16),b:parseInt(h.substr(4,2),16)};}
                                function rgb(r,g,b){return r+','+g+','+b;}
                                function dk(h,a){var c=hx2rgb(h);return'rgb('+(c.r*(1-a)|0)+','+(c.g*(1-a)|0)+','+(c.b*(1-a)|0)+')';}
                                function sf(h,a){var c=hx2rgb(h);return'rgb('+(c.r+(255-c.r)*a|0)+','+(c.g+(255-c.g)*a|0)+','+(c.b+(255-c.b)*a|0)+')';}
                                function val(n){var f=form.querySelector('[name="'+n+'"]:checked');return f?f.value:'';}
                                function valText(n){var f=form.querySelector('[name="'+n+'"]');return f?f.value:'';}
                                function setV(o,k,v){o.style.setProperty('--'+k,v);}
                                function apply(){
                                    var b=document.body,c={}; c.preset=val('theme_preset')||'classic'; c.accent=val('theme_accent'); c.accent_custom=valText('theme_accent_custom'); c.bg=val('theme_bg')||'light'; c.radius=val('theme_radius')||'default'; c.compact=val('theme_compact')||'0'; c.font=val('theme_font')||'normal'; c.effects=val('theme_effects')||'1'; c.cursor=val('theme_cursor')||'ring'; c.motion=val('theme_motion')||'on'; c.sidebar=val('theme_sidebar')||'auto'; c.header=val('theme_header')||'auto'; c.glow=val('theme_glow')||'0'; c.heading_color=valText('theme_heading_color'); c.link_color=valText('theme_link_color'); c.link_hover=valText('theme_link_hover'); c.muted_color=valText('theme_muted_color'); c.code_color=valText('theme_code_color'); c.card_bg=valText('theme_card_bg'); c.sidebar_bg_c=valText('theme_sidebar_bg_c'); c.header_bg_c=valText('theme_header_bg_c'); c.font_family=valText('theme_font_family')||'system'; c.font_size=valText('theme_font_size')||'normal'; c.heading_weight=valText('theme_heading_weight')||'600'; c.line_height=val('theme_line_height')||'normal'; c.letter_spacing=val('theme_letter_spacing')||'normal';
                                    var P=PS[c.preset]||PS.classic; var acc=c.accent_custom&&/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(c.accent_custom)?c.accent_custom:(N[c.accent]||P.accent); acc=acc||P.accent; if(c.preset==='custom'){if(c.bg==='dark')P=PS.midnight;else P=PS.classic;P.accent=acc;}if(c.sidebar==='light'){P.sidebar_bg='#fff';P.sidebar_text='#1a1f33';}if(c.sidebar==='dark'){P.sidebar_bg='#0e1524';P.sidebar_text='#c7d3e6';}if(c.header==='light')P.header_bg='#fff';else if(c.header==='dark')P.header_bg=c.bg==='dark'?P.surface:'#111827';else if(c.header==='transparent')P.header_bg='transparent';
                                    var dark=P.mode==='dark'||c.bg==='dark';
                                    var r=document.documentElement.style; r.setProperty('--sps-accent',acc);var rc=hx2rgb(acc);var rgbS=rgb(rc.r,rc.g,rc.b);r.setProperty('--sps-accent-rgb',rgbS);r.setProperty('--sps-accent-hover',dk(acc,.10));r.setProperty('--sps-accent-border',dk(acc,.14));r.setProperty('--sps-accent-active',dk(acc,.18));r.setProperty('--sps-soft',sf(acc,.82));r.setProperty('--sps-soft2',sf(acc,.68));r.setProperty('--sps-alert-ink',dk(acc,.42));r.setProperty('--sps-bg',P.bg);r.setProperty('--sps-surface',P.surface);r.setProperty('--sps-surface2',P.surface2);r.setProperty('--sps-surface3',P.surface3);r.setProperty('--sps-border',P.border);r.setProperty('--sps-border2',P.border);r.setProperty('--sps-text',P.sidebar_text||'#212529');r.setProperty('--sps-muted','#6b7280');
                                        r.setProperty('--sps-heading',c.heading_color||P.sidebar_text||'#060606');r.setProperty('--sps-link',c.link_color||acc);r.setProperty('--sps-link-hover',c.link_hover||dk(c.link_color||acc,.12));r.setProperty('--sps-muted-text',c.muted_color||'#6b7280');r.setProperty('--sps-code',c.code_color||'#e83e8c');
                                        r.setProperty('--sps-sidebar-bg',c.sidebar_bg_c&&/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(c.sidebar_bg_c)?c.sidebar_bg_c:P.sidebar_bg);r.setProperty('--sps-sidebar-text',P.sidebar_text);r.setProperty('--sps-sidebar-muted','#6b7280');r.setProperty('--sps-header-bg',c.header_bg_c&&/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(c.header_bg_c)?c.header_bg_c:P.header_bg);r.setProperty('--sps-shadow',P.shadow);r.setProperty('--sps-card',c.card_bg&&/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(c.card_bg)?c.card_bg:P.surface);
                                        var ffMap={system:'system-ui,-apple-system,\'Segoe UI\',Roboto,sans-serif','open-sans':'\'Open Sans\',system-ui,sans-serif',inter:'\'Inter\',system-ui,sans-serif',poppins:'\'Poppins\',\'Segoe UI\',system-ui,sans-serif',roboto:'\'Roboto\',\'Segoe UI\',system-ui,sans-serif',nunito:'\'Nunito\',\'Segoe UI\',system-ui,sans-serif'};
                                        document.body.style.fontFamily=ffMap[c.font_family]||ffMap.system;
                                        var szMap={xs:'12px',sm:'13px',normal:'',lg:'15.5px',xl:'17px'}; if(szMap[c.font_size])document.body.style.fontSize=szMap[c.font_size];
                                        var hws=document.querySelectorAll('h1,h2,h3,h4,h5,h6'); for(var h=0;h<hws.length;h++)hws[h].style.fontWeight=c.heading_weight;
                                        var lhMap={tight:'1.35',normal:'',relaxed:'1.7'}; if(lhMap[c.line_height])document.body.style.lineHeight=lhMap[c.line_height];
                                        var lsMap={tight:'-0.02em',normal:'',wide:'0.045em'}; if(lsMap[c.letter_spacing])document.body.style.letterSpacing=lsMap[c.letter_spacing];
                                    for(var i=1;i<=10;i++)b.classList.remove('theme-'+i);b.className=b.className.replace(/sps-(p-\S+|bg-dark|glass|glow|radius-\S+|compact|font-lg|effects|motion|cursor-\S+|cursor-on)/g,'').trim();b.classList.add('sps-root','sps-p-'+c.preset);if(dark)b.classList.add('sps-bg-dark');if(c.preset==='aurora')b.classList.add('sps-glass');if(c.glow==='1'||c.preset==='neon'||c.preset==='midnight')b.classList.add('sps-glow');if(c.radius==='square')b.classList.add('sps-radius-square');if(c.radius==='rounded')b.classList.add('sps-radius-rounded');if(c.compact==='1')b.classList.add('sps-compact');if(c.font==='large')b.classList.add('sps-font-lg');
                                    Array.prototype.slice.call(form.querySelectorAll('.th-swat,.th-opt,.preset-card')).forEach(function(el){var ipt=el.querySelector('input');el.classList.toggle('sel',ipt&&ipt.checked);});
                                }
                                apply(); form.addEventListener('change',function(){apply();});
                                }catch(e){}
                            })();
                            </script>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include "includes/footer.php"; ?>
    <?php include "includes/scripts.php"; ?>

    <script>
    (function(){
        /* ── tab sync & deploy toggle ── */
        var syncTab = document.querySelector('a[href="#tab-sync"]');
        var deployCard = document.getElementById('tab-deploy');
        if(syncTab) syncTab.addEventListener('shown.bs.tab', function(){ deployCard.style.display='block'; deployCard.classList.add('show','active'); });
        var otherTabs = document.querySelectorAll('a[data-bs-toggle="pill"]:not([href="#tab-sync"])');
        otherTabs.forEach(function(t){ t.addEventListener('shown.bs.tab', function(){ deployCard.style.display='none'; deployCard.classList.remove('show','active'); }); });

        /* ── AJAX test buttons ── */
        function ajaxTest(btn, resultId, formData){
            btn.disabled = true;
            btn.innerHTML = '<i class="ti ti-loader"></i> Testing...';
            var res = document.getElementById(resultId);
            fetch(location.href.split('?')[0], {method:'POST', body: formData})
            .then(function(r){
                return r.text().then(function(t){
                    try { return JSON.parse(t); }
                    catch(e) { return {ok:false, msg:'Server returned non-JSON: ' + t.slice(0,200)}; }
                });
            })
            .then(function(d){
                res.className = 'test-result ' + (d.ok ? 'ok' : 'err');
                res.textContent = d.msg;
            })
            .catch(function(e){
                res.className = 'test-result err';
                res.textContent = 'Network error: ' + e.message;
            })
            .finally(function(){ btn.disabled = false; btn.innerHTML = '<i class="ti ti-flask"></i> ' + btn.dataset.label; });
        }

        /* sync test */
        var syncBtn = document.getElementById('syncTestBtn');
        if(syncBtn){ syncBtn.dataset.label = 'Test Connection';
            syncBtn.onclick = function(){
                var fd = new FormData(); fd.append('sync_test','1');
                var url = document.querySelector('[name=sync_url]');
                var tok = document.querySelector('[name=sync_token]');
                if(url) fd.append('sync_url', url.value);
                if(tok) fd.append('sync_token', tok.value);
                ajaxTest(this,'syncTestResult',fd);
            };
        }

        /* stripe test */
        var stripeBtn = document.getElementById('stripeTestBtn');
        if(stripeBtn){ stripeBtn.dataset.label = 'Test Stripe';
            stripeBtn.onclick = function(){
                var fd = new FormData(); fd.append('stripe_test','1');
                ajaxTest(this,'stripeTestResult',fd);
            };
        }

        /* smtp test */
        var smtpBtn = document.getElementById('smtpTestBtn');
        if(smtpBtn){ smtpBtn.dataset.label = 'Test';
            smtpBtn.onclick = function(){
                var form = document.getElementById('smtpForm');
                var fd = new FormData(form); fd.append('smtp_test','1');
                var to = form.querySelector('[name=smtp_test_to]').value;
                if(!to){ alert('Enter a test email address.'); return; }
                ajaxTest(this,'smtpTestResult',fd);
            };
        }

        /* twilio test */
        var twBtn = document.getElementById('twilioTestBtn');
        if(twBtn){ twBtn.dataset.label = 'Test Twilio';
            twBtn.onclick = function(){
                var form = document.getElementById('twilioForm');
                var fd = new FormData(form); fd.append('twilio_test','1');
                ajaxTest(this,'twilioTestResult',fd);
            };
        }

        /* ── payment methods add/remove ── */
        var payRows = document.getElementById('payRows');
        var addPay = document.getElementById('addPay');
        if(addPay) addPay.onclick = function(){
            var row = document.createElement('div');
            row.className = 'row mb-2 pay-row';
            row.innerHTML = '<input type="hidden" name="pay_id[]" value="0"><div class="col-md-10"><input type="text" class="form-control" name="pay_name[]" placeholder="New payment method"></div><div class="col-md-2"><button type="button" class="btn btn-outline-danger btn-sm remove-pay"><i class="ti ti-trash"></i></button></div>';
            payRows.appendChild(row);
        };
        if(payRows) payRows.addEventListener('click', function(e){
            if(e.target.closest('.remove-pay')) e.target.closest('.pay-row').remove();
        });
    })();
    </script>
</body>
</html>
