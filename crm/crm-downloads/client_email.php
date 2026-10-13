<?php
/* =====================================================================
 *  CLIENT EMAIL MODULE  —  everything in one file
 * =====================================================================
 *  Included by client_details.php  -> only the helper functions load.
 *  Requested directly              -> acts as the AJAX endpoint (POST
 *                                     em_action=...) and, on a plain GET,
 *                                     renders the manage page for
 *                                     templates + sending mailboxes.
 *
 *  One table, created on first load (same pattern as bulk_email.php):
 *      email_module   kind='template'  -> subject / body / visibility
 *                     kind='account'   -> SMTP mailbox in `config` JSON
 *
 *  Sends are recorded in the existing email_history table.
 * ===================================================================== */

/* When included from client_details.php the DB + session are already up;
 * when hit directly we have to boot them ourselves. */
if (!isset($con)) {
    include __DIR__ . '/includes/db.php';
    include __DIR__ . '/includes/auth.php';
}
require_once __DIR__ . '/includes/activity_log.php';

$em_direct = (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'client_email.php');

/* =====================================================================
 *  SCHEMA — created on first load, then seeded with the mailbox that is
 *  currently hardcoded across the CRM so sending works immediately.
 * ===================================================================== */
function em_ensure_schema($con)
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    @mysqli_query($con, "CREATE TABLE IF NOT EXISTS email_module (
        id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        kind        VARCHAR(8)      NOT NULL,
        title       VARCHAR(190)    NOT NULL,
        subject     VARCHAR(255)    NOT NULL DEFAULT '',
        body        MEDIUMTEXT          NULL,
        visibility  VARCHAR(7)      NOT NULL DEFAULT 'private',
        config      TEXT                NULL,
        is_default  TINYINT(1)      NOT NULL DEFAULT 0,
        is_active   TINYINT(1)      NOT NULL DEFAULT 1,
        created_by  INT(11)             NULL,
        created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at  TIMESTAMP           NULL DEFAULT NULL,
        PRIMARY KEY (id),
        CONSTRAINT ck_email_module_kind       CHECK (kind IN ('template','account')),
        CONSTRAINT ck_email_module_visibility CHECK (visibility IN ('private','public')),
        CONSTRAINT fk_email_module_created_by FOREIGN KEY (created_by)
            REFERENCES users (user_id) ON DELETE SET NULL ON UPDATE CASCADE,
        KEY idx_email_module_kind    (kind, deleted_at),
        KEY idx_email_module_owner   (kind, created_by, deleted_at),
        KEY idx_email_module_default (kind, is_default),
        KEY idx_email_module_title   (title)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $c = mysqli_fetch_assoc(mysqli_query(
        $con,
        "SELECT COUNT(*) c FROM email_module WHERE kind = 'account'"
    ));
    if ($c && (int) $c['c'] === 0) {
        $cfg = mysqli_real_escape_string($con, json_encode([
            'from_email' => 'invoice@syedpro.com',
            'from_name' => 'Syed Professional Services',
            'host' => 'smtp.office365.com',
            'port' => 587,
            'secure' => 'tls',
            'username' => 'invoice@syedpro.com',
            'password' => 'bgtqpxznmghqwkrc',
        ]));
        mysqli_query(
            $con,
            "INSERT INTO email_module (kind, title, config, is_default, is_active)
             VALUES ('account', 'Invoices (Syed Professional Services)', '$cfg', 1, 1)"
        );
    }
}
em_ensure_schema($con);

/* =====================================================================
 *  HELPERS  (also used by the compose box on client_details.php)
 * ===================================================================== */

/** user_type 1 is the admin role across this CRM (see includes/auth.php). */
function em_is_admin()
{
    return (string) ($_SESSION['user_type'] ?? '') === '1';
}

/** Owner or admin may edit; "public" only widens who may *use* a template. */
function em_can_edit(array $row)
{
    return em_is_admin() || (int) $row['created_by'] === (int) ($_SESSION['user_id'] ?? 0);
}

/** Sending mailboxes, default first. SMTP details come out of `config`. */
function em_accounts($con, $include_inactive = false)
{
    $where = "kind = 'account' AND deleted_at IS NULL" . ($include_inactive ? "" : " AND is_active = 1");
    $res = mysqli_query(
        $con,
        "SELECT id, title, config, is_default, is_active
           FROM email_module
          WHERE $where
          ORDER BY is_default DESC, title ASC"
    );
    $out = [];
    while ($res && $row = mysqli_fetch_assoc($res)) {
        $row['cfg'] = json_decode((string) $row['config'], true) ?: [];
        unset($row['config']);          // never ship SMTP passwords to the page
        unset($row['cfg']['password']);
        $out[] = $row;
    }
    return $out;
}

/** The mailbox to send through, falling back to the default one. */
function em_resolve_account($con, $id = 0)
{
    $id = (int) $id;
    $sql = $id > 0
        ? "SELECT * FROM email_module
            WHERE id = $id AND kind = 'account' AND is_active = 1 AND deleted_at IS NULL LIMIT 1"
        : "SELECT * FROM email_module
            WHERE kind = 'account' AND is_active = 1 AND deleted_at IS NULL
            ORDER BY is_default DESC, id ASC LIMIT 1";

    $res = mysqli_query($con, $sql);
    if ($res && $row = mysqli_fetch_assoc($res)) {
        $row['cfg'] = json_decode((string) $row['config'], true) ?: [];
        return $row;
    }
    // Asked for a specific mailbox that is gone/inactive — fall back.
    return $id > 0 ? em_resolve_account($con, 0) : null;
}

/** Templates this user may pick: their own, plus everyone else's public ones. */
function em_templates($con, $uid = null)
{
    $uid = (int) ($uid ?? ($_SESSION['user_id'] ?? 0));
    $res = mysqli_query(
        $con,
        "SELECT t.id, t.title, t.subject, t.body, t.visibility, t.created_by,
                u.user_name AS owner_name
           FROM email_module t
      LEFT JOIN users u ON u.user_id = t.created_by
          WHERE t.kind = 'template' AND t.deleted_at IS NULL
            AND (t.created_by = $uid OR t.visibility = 'public')
          ORDER BY t.title ASC"
    );
    $mine = [];
    $public = [];
    while ($res && $row = mysqli_fetch_assoc($res)) {
        $row['is_mine'] = ((int) $row['created_by'] === $uid);
        $row['can_edit'] = em_can_edit($row);
        if ($row['is_mine']) {
            $mine[] = $row;
        } else {
            $public[] = $row;
        }
    }
    return ['mine' => $mine, 'public' => $public];
}

/** Placeholders staff can drop into a subject or body. */
function em_merge_tags()
{
    return [
        '{client_name}' => 'Client full name',
        '{first_name}' => 'Client first name',
        '{business_name}' => 'Business name',
        '{client_email}' => 'Client primary email',
        '{client_id}' => 'Client ID',
        '{my_name}' => 'Your name (the sender)',
        '{today}' => "Today's date",
    ];
}

function em_apply_merge_tags($text, array $client = null)
{
    $full = trim(($client['name'] ?? '') . ' ' . ($client['mname'] ?? '') . ' ' . ($client['lname'] ?? ''));
    $map = [
        '{client_name}' => $full,
        '{first_name}' => (string) ($client['name'] ?? ''),
        '{business_name}' => (string) ($client['bus_name'] ?? ''),
        '{client_email}' => (string) ($client['prm_email'] ?? ''),
        '{client_id}' => (string) ($client['member_id'] ?? ''),
        '{my_name}' => (string) ($_SESSION['fullname'] ?? ''),
        '{today}' => date('m/d/Y'),
    ];
    return str_replace(array_keys($map), array_values($map), (string) $text);
}

/** Split "a@b.com, c@d.com" into [valid[], invalid[]] so typos get reported. */
function em_parse_addresses($raw)
{
    $valid = [];
    $invalid = [];
    foreach (preg_split('/[,;\r\n]+/', (string) $raw) as $piece) {
        $piece = trim($piece);
        if ($piece === '') {
            continue;
        }
        if (filter_var($piece, FILTER_VALIDATE_EMAIL)) {
            $valid[] = $piece;
        } else {
            $invalid[] = $piece;
        }
    }
    return [array_values(array_unique($valid)), $invalid];
}

/** PHPMailer (5.2.x, no namespace) wired up to a mailbox row. */
function em_mailer(array $account)
{
    require_once __DIR__ . '/PHPMailer/PHPMailerAutoload.php';
    $cfg = $account['cfg'];

    $mail = new PHPMailer;
    $mail->isSMTP();
    $mail->Host = $cfg['host'] ?? '';
    $mail->SMTPAuth = true;
    $mail->Username = $cfg['username'] ?? '';
    $mail->Password = $cfg['password'] ?? '';
    $mail->Port = (int) ($cfg['port'] ?? 587);
    $mail->CharSet = 'UTF-8';
    if (($cfg['secure'] ?? 'tls') !== 'none') {
        $mail->SMTPSecure = $cfg['secure'] ?? 'tls';
    }
    $mail->setFrom($cfg['from_email'] ?? '', $cfg['from_name'] ?? '');
    $mail->isHTML(true);
    return $mail;
}

/* =====================================================================
 *  AJAX ACTIONS  (only when this file is requested directly)
 * ===================================================================== */
if ($em_direct && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['em_action'])) {

    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');

    $uid = (int) ($_SESSION['user_id'] ?? 0);
    $action = $_POST['em_action'];
    $esc = fn($v) => mysqli_real_escape_string($con, trim((string) $v));

    $respond = function ($ok, $payload = []) {
        if (is_string($payload)) {
            $payload = ['message' => $payload];
        }
        echo json_encode(array_merge(['success' => $ok], $payload));
        exit;
    };

    /* ---------- Templates ------------------------------------------ */
    if ($action === 'tpl_list') {
        $respond(true, em_templates($con, $uid));
    }

    if ($action === 'tpl_save') {
        $id = (int) ($_POST['id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $subject = trim((string) ($_POST['subject'] ?? ''));
        $body = trim((string) ($_POST['body'] ?? ''));
        $visibility = ($_POST['visibility'] ?? 'private') === 'public' ? 'public' : 'private';

        if ($title === '') {
            $respond(false, 'Template name is required.');
        }
        if ($subject === '') {
            $respond(false, 'Subject is required.');
        }
        if ($body === '' || trim(strip_tags($body)) === '') {
            $respond(false, 'Body is required.');
        }

        $t = $esc($title);
        $s = $esc($subject);
        $b = $esc($body);

        if ($id > 0) {
            $cur = mysqli_fetch_assoc(mysqli_query(
                $con,
                "SELECT * FROM email_module WHERE id = $id AND kind = 'template' AND deleted_at IS NULL LIMIT 1"
            ));
            if (!$cur) {
                $respond(false, 'Template not found.');
            }
            if (!em_can_edit($cur)) {
                $respond(false, 'This template belongs to another user, so you can use it but not edit it.');
            }
            $ok = mysqli_query(
                $con,
                "UPDATE email_module
                    SET title = '$t', subject = '$s', body = '$b', visibility = '$visibility'
                  WHERE id = $id"
            );
            $respond((bool) $ok, [
                'id' => $id,
                'message' => $ok ? 'Template updated.' : 'Could not update the template.',
            ]);
        }

        $ok = mysqli_query(
            $con,
            "INSERT INTO email_module (kind, title, subject, body, visibility, created_by)
             VALUES ('template', '$t', '$s', '$b', '$visibility', $uid)"
        );
        $respond((bool) $ok, [
            'id' => $ok ? mysqli_insert_id($con) : 0,
            'message' => $ok ? 'Template saved.' : 'Could not save the template.',
        ]);
    }

    if ($action === 'tpl_delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $cur = mysqli_fetch_assoc(mysqli_query(
            $con,
            "SELECT * FROM email_module WHERE id = $id AND kind = 'template' AND deleted_at IS NULL LIMIT 1"
        ));
        if (!$cur) {
            $respond(false, 'Template not found.');
        }
        if (!em_can_edit($cur)) {
            $respond(false, 'Only the staff member who created this template (or an admin) can delete it.');
        }
        $ok = mysqli_query($con, "UPDATE email_module SET deleted_at = NOW() WHERE id = $id");
        $respond((bool) $ok, $ok ? 'Template deleted.' : 'Could not delete the template.');
    }

    /* ---------- Mailboxes (admin only) ------------------------------ */
    if (in_array($action, ['acc_save', 'acc_delete', 'acc_default', 'acc_test'], true) && !em_is_admin()) {
        $respond(false, 'Only an admin can manage sending mailboxes.');
    }

    if ($action === 'acc_save') {
        $id = (int) ($_POST['id'] ?? 0);
        $label = trim((string) ($_POST['title'] ?? ''));
        $from_email = trim((string) ($_POST['from_email'] ?? ''));
        $host = trim((string) ($_POST['host'] ?? ''));
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($label === '' || $host === '' || $username === '') {
            $respond(false, 'Label, SMTP host and username are all required.');
        }
        if (!filter_var($from_email, FILTER_VALIDATE_EMAIL)) {
            $respond(false, 'The "From" address is not a valid email.');
        }

        // Editing with a blank password keeps the stored one.
        if ($id > 0 && $password === '') {
            $cur = mysqli_fetch_assoc(mysqli_query(
                $con,
                "SELECT config FROM email_module WHERE id = $id AND kind = 'account' LIMIT 1"
            ));
            $oldcfg = $cur ? (json_decode((string) $cur['config'], true) ?: []) : [];
            $password = (string) ($oldcfg['password'] ?? '');
        }
        if ($password === '') {
            $respond(false, 'A password is required for a new mailbox.');
        }

        $secure = in_array($_POST['secure'] ?? 'tls', ['tls', 'ssl', 'none'], true) ? $_POST['secure'] : 'tls';
        $cfg = $esc(json_encode([
            'from_email' => $from_email,
            'from_name' => trim((string) ($_POST['from_name'] ?? '')),
            'host' => $host,
            'port' => (int) ($_POST['port'] ?? 587),
            'secure' => $secure,
            'username' => $username,
            'password' => $password,
        ]));
        $t = $esc($label);
        $active = !empty($_POST['is_active']) ? 1 : 0;
        $make_default = !empty($_POST['is_default']) ? 1 : 0;

        if ($id > 0) {
            $ok = mysqli_query(
                $con,
                "UPDATE email_module SET title = '$t', config = '$cfg', is_active = $active
                  WHERE id = $id AND kind = 'account'"
            );
        } else {
            $ok = mysqli_query(
                $con,
                "INSERT INTO email_module (kind, title, config, is_active, created_by)
                 VALUES ('account', '$t', '$cfg', $active, $uid)"
            );
            $id = $ok ? mysqli_insert_id($con) : 0;
        }
        if ($ok && $make_default && $id > 0) {
            mysqli_query($con, "UPDATE email_module SET is_default = 0 WHERE kind = 'account'");
            mysqli_query($con, "UPDATE email_module SET is_default = 1 WHERE id = $id");
        }
        $respond((bool) $ok, $ok ? 'Mailbox saved.' : 'Could not save the mailbox.');
    }

    if ($action === 'acc_default') {
        $id = (int) ($_POST['id'] ?? 0);
        mysqli_query($con, "UPDATE email_module SET is_default = 0 WHERE kind = 'account'");
        $ok = mysqli_query($con, "UPDATE email_module SET is_default = 1 WHERE id = $id AND kind = 'account'");
        $respond((bool) $ok, $ok ? 'Default mailbox updated.' : 'Could not update the default.');
    }

    if ($action === 'acc_delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $left = (int) mysqli_fetch_assoc(mysqli_query(
            $con,
            "SELECT COUNT(*) c FROM email_module
              WHERE kind = 'account' AND deleted_at IS NULL AND id <> $id"
        ))['c'];
        if ($left === 0) {
            $respond(false, 'This is the only mailbox — the CRM would have no way to send email. Add another one first.');
        }
        $row = mysqli_fetch_assoc(mysqli_query(
            $con,
            "SELECT is_default FROM email_module WHERE id = $id AND kind = 'account' LIMIT 1"
        ));
        if (!$row) {
            $respond(false, 'Mailbox not found.');
        }
        mysqli_query(
            $con,
            "UPDATE email_module SET deleted_at = NOW(), is_active = 0, is_default = 0
              WHERE id = $id AND kind = 'account'"
        );
        if ((int) $row['is_default'] === 1) {
            // Promote another mailbox so sending never silently breaks.
            $next = mysqli_fetch_assoc(mysqli_query(
                $con,
                "SELECT id FROM email_module WHERE kind = 'account' AND deleted_at IS NULL ORDER BY id ASC LIMIT 1"
            ));
            if ($next) {
                mysqli_query($con, "UPDATE email_module SET is_default = 1 WHERE id = " . (int) $next['id']);
            }
        }
        $respond(true, 'Mailbox removed.');
    }

    if ($action === 'acc_test') {
        $acc = em_resolve_account($con, (int) ($_POST['id'] ?? 0));
        $target = trim((string) ($_POST['test_to'] ?? ''));
        if (!$acc) {
            $respond(false, 'Mailbox not found or inactive.');
        }
        if (!filter_var($target, FILTER_VALIDATE_EMAIL)) {
            $respond(false, 'Enter a valid test address.');
        }
        try {
            $from = (string) ($acc['cfg']['from_email'] ?? '');
            $mail = em_mailer($acc);
            $mail->addAddress($target);
            $mail->Subject = 'CRM test email from ' . $from;
            $mail->Body = '<p>Test email sent from the CRM using <b>' . htmlspecialchars($from) . '</b>.</p>';
            $mail->AltBody = 'Test email from ' . $from;
            $ok = $mail->send();
            $respond($ok, $ok ? 'Test email sent to ' . $target . '.' : 'SMTP error: ' . $mail->ErrorInfo);
        } catch (Throwable $e) {
            $respond(false, 'SMTP error: ' . $e->getMessage());
        }
    }

    /* ---------- Send to a client ------------------------------------ */
    if ($action === 'send') {
        $client_id = (int) ($_POST['client_id'] ?? 0);
        $subject = trim((string) ($_POST['subject'] ?? ''));
        $body = trim((string) ($_POST['body'] ?? ''));

        if ($client_id <= 0) {
            $respond(false, 'Missing client.');
        }
        if ($subject === '') {
            $respond(false, 'Subject is required.');
        }
        // &nbsp;-only paragraphs survive strip_tags, so decode entities and
        // trim non-breaking spaces too before calling the body empty.
        $body_text = html_entity_decode(strip_tags($body), ENT_QUOTES, 'UTF-8');
        if ($body === '' || trim($body_text, " \t\n\r\0\x0B\xC2\xA0") === '') {
            $respond(false, 'Message body is required.');
        }

        $client = mysqli_fetch_assoc(mysqli_query(
            $con,
            "SELECT member_id, name, mname, lname, bus_name, prm_email, sec_email
               FROM members WHERE member_id = $client_id LIMIT 1"
        ));
        if (!$client) {
            $respond(false, 'Client not found.');
        }

        $to_raw = trim((string) ($_POST['to'] ?? ''));
        if ($to_raw === '') {
            $to_raw = (string) ($client['prm_email'] ?: $client['sec_email']);
        }
        list($to, $bad_to) = em_parse_addresses($to_raw);
        list($cc, $bad_cc) = em_parse_addresses($_POST['cc'] ?? '');
        list($bcc, $bad_bcc) = em_parse_addresses($_POST['bcc'] ?? '');

        // Report typos before "nothing to send to", otherwise a mistyped
        // address is blamed on the client having no email on file.
        $bad = array_merge($bad_to, $bad_cc, $bad_bcc);
        if ($bad) {
            $respond(false, 'These addresses are not valid: ' . implode(', ', $bad));
        }
        if (!$to) {
            $respond(false, 'No "To" address. This client has no email on file — type one in.');
        }

        $acc = em_resolve_account($con, (int) ($_POST['sender_account_id'] ?? 0));
        if (!$acc) {
            $respond(false, 'No sending mailbox is configured. Add one on the Email Templates page first.');
        }

        $subject = em_apply_merge_tags($subject, $client);
        $body = em_apply_merge_tags($body, $client);

        $sent = false;
        $error = '';        // full technical detail — log only
        $mail_reason = '';  // what the mail server itself said — safe to show
        try {
            $mail = em_mailer($acc);
            foreach ($to as $a) {
                $mail->addAddress($a);
            }
            foreach ($cc as $a) {
                $mail->addCC($a);
            }
            foreach ($bcc as $a) {
                $mail->addBCC($a);
            }
            // Replies should reach the staff member who wrote the email.
            $urow = mysqli_fetch_assoc(mysqli_query(
                $con,
                "SELECT user_email FROM users WHERE user_id = $uid LIMIT 1"
            ));
            $reply = trim((string) ($urow['user_email'] ?? ''));
            if ($reply !== '' && filter_var($reply, FILTER_VALIDATE_EMAIL)) {
                $mail->addReplyTo($reply, (string) ($_SESSION['fullname'] ?? ''));
            }

            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = trim(html_entity_decode(strip_tags(
                preg_replace('/<br\s*\/?>|<\/(p|div|tr|h[1-6])>/i', "\n", $body)
            ), ENT_QUOTES, 'UTF-8'));

            $sent = $mail->send();
            if (!$sent) {
                $error = $mail->ErrorInfo;
                $mail_reason = $mail->ErrorInfo;   // mail-server wording only
            }
        } catch (Throwable $e) {
            // A bug on our side (bad query, missing class…). Log it, but the
            // message must never reach the user — it carries paths and lines.
            $error = $e->getMessage();
        }

        if (!$sent) {
            error_log('Client email SMTP error: ' . $error);
            $reason = trim(preg_replace(
                ['~https?://\S+~', '/\s+/'],
                ['', ' '],
                (string) $mail_reason
            ), " \t.-");
            $respond(false, $reason !== ''
                ? 'The email could not be sent: ' . $reason . '.'
                : 'The email could not be sent. Please try again — if it keeps happening, contact support.');
        }

        // Record it in the existing history table.
        $from_email = (string) ($acc['cfg']['from_email'] ?? '');
        mysqli_query(
            $con,
            "INSERT INTO email_history (client_id, subject, body, sent_at, sent_by, To_mail, From_mail, type)
             VALUES ($client_id, '" . $esc($subject) . "', '" . $esc($body) . "', NOW(), $uid,
                     '" . $esc(implode(', ', $to)) . "', '" . $esc($from_email) . "', 'general')"
        );

        logActivity(
            $con,
            $uid,
            'EMAIL',
            'members',
            $client_id,
            'Sent email "' . $subject . '" to ' . implode(', ', $to) . ' via ' . $from_email
        );

        $respond(true, 'Email sent to ' . implode(', ', $to) . '.');
    }

    $respond(false, 'Unknown action.');
}

/* When included by client_details.php we are done — helpers only. */
if (!$em_direct) {
    return;
}

/* =====================================================================
 *  MANAGE PAGE  (plain GET on client_email.php)
 * ===================================================================== */
$uid = (int) $_SESSION['user_id'];
$templates = em_templates($con, $uid);
$accounts = em_accounts($con, true);
$merge_tags = em_merge_tags();
?>
<!doctype html>
<html>

<head>
    <title>Email Templates</title>
    <?php include "includes/links.php"; ?>
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.ckeditor.com/ckeditor5/41.0.0/classic/ckeditor.js"></script>
    <style>
        .em-note {
            font-size: .85rem;
            color: #6c757d;
        }

        .em-note-public {
            color: #157347;
            font-weight: 600;
        }

        .em-sub {
            color: #6c757d;
            font-size: .88rem;
        }

        .em-badge-public {
            background: #198754;
            color: #fff;
        }

        .em-badge-private {
            background: #6c757d;
            color: #fff;
        }

        .ck-editor__editable_inline {
            min-height: 210px;
        }

        .em-tag {
            cursor: pointer;
            font-family: monospace;
            font-size: .78rem;
            background: #eef1f7;
            border: 1px solid #dfe3ec;
            border-radius: 4px;
            padding: 2px 6px;
            margin: 0 4px 4px 0;
            display: inline-block;
        }

        .em-tag:hover {
            background: #dfe6f5;
        }
    </style>
</head>

<body class="theme-3">
    <?php include "includes/header.php"; ?>
    <?php include "includes/sidebar.php"; ?>

    <div class="dash-container">
        <div class="dash-content">
            <div class="page-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Email Templates</h4>
                <button class="btn btn-primary btn-sm" id="emNewBtn">
                    <i class="fas fa-plus"></i> Add New Template
                </button>
            </div>

            <!-- TEMPLATE ADD / EDIT -->
            <div class="card mb-4 shadow-sm border-light d-none" id="emFormCard">
                <div class="card-body">
                    <h6 class="mb-3" id="emFormTitle">Add New Template</h6>
                    <input type="hidden" id="emId" value="">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label">Template Name</label>
                            <input type="text" id="emTitle" class="form-control" maxlength="150"
                                placeholder="e.g. Documents Request">
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">Subject</label>
                            <input type="text" id="emSubject" class="form-control" maxlength="255"
                                placeholder="Email subject line">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Body</label>
                        <textarea id="emBody"></textarea>
                    </div>
                    <div class="mt-3">
                        <label class="form-label d-block mb-1">Merge tags</label>
                        <?php foreach ($merge_tags as $tag => $desc): ?>
                            <span class="em-tag" title="<?= htmlspecialchars($desc) ?>"><?= htmlspecialchars($tag) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-3">
                        <label class="form-label d-block mb-1">Mark as</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="emVis" id="emVisPrivate" checked>
                            <label class="form-check-label" for="emVisPrivate">Private</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="emVis" id="emVisPublic">
                            <label class="form-check-label" for="emVisPublic">Public</label>
                        </div>
                        <div class="em-note" id="emVisNote"></div>
                    </div>
                    <div class="mt-4">
                        <button class="btn btn-primary btn-sm" id="emSaveBtn">Save Template</button>
                        <button class="btn btn-light btn-sm" id="emCancelBtn">Cancel</button>
                    </div>
                </div>
            </div>

            <!-- MY TEMPLATES -->
            <div class="card shadow-sm border-light mb-4">
                <div class="card-body">
                    <h6 class="mb-3">My Templates (<?= count($templates['mine']) ?>)</h6>
                    <?php if (!$templates['mine']): ?>
                        <p class="text-muted mb-0">You haven't created any templates yet.</p>
                    <?php else: ?>
                        <table class="table table-bordered table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:30%">Name</th>
                                    <th style="width:40%">Subject</th>
                                    <th style="width:14%">Visibility</th>
                                    <th style="width:16%">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($templates['mine'] as $t): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($t['title']) ?></td>
                                        <td class="em-sub"><?= htmlspecialchars($t['subject']) ?></td>
                                        <td>
                                            <span
                                                class="badge <?= $t['visibility'] === 'public' ? 'em-badge-public' : 'em-badge-private' ?>">
                                                <?= ucfirst($t['visibility']) ?>
                                            </span>
                                        </td>
                                        <td class="text-nowrap">
                                            <button class="btn btn-sm btn-warning me-1 emEdit"
                                                data-t='<?= htmlspecialchars(json_encode($t), ENT_QUOTES) ?>'>Edit</button>
                                            <button class="btn btn-sm btn-danger emDel"
                                                data-id="<?= (int) $t['id'] ?>">Del</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- PUBLIC TEMPLATES -->
            <div class="card shadow-sm border-light mb-4">
                <div class="card-body">
                    <h6 class="mb-3">Public Templates (<?= count($templates['public']) ?>)</h6>
                    <?php if (!$templates['public']): ?>
                        <p class="text-muted mb-0">No public templates yet.</p>
                    <?php else: ?>
                        <table class="table table-bordered table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:28%">Name</th>
                                    <th style="width:38%">Subject</th>
                                    <th style="width:18%">Created By</th>
                                    <th style="width:16%">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($templates['public'] as $t): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($t['title']) ?></td>
                                        <td class="em-sub"><?= htmlspecialchars($t['subject']) ?></td>
                                        <td class="em-note"><?= htmlspecialchars($t['owner_name'] ?? '—') ?></td>
                                        <td class="text-nowrap">
                                            <?php if ($t['can_edit']): ?>
                                                <button class="btn btn-sm btn-warning me-1 emEdit"
                                                    data-t='<?= htmlspecialchars(json_encode($t), ENT_QUOTES) ?>'>Edit</button>
                                                <button class="btn btn-sm btn-danger emDel"
                                                    data-id="<?= (int) $t['id'] ?>">Del</button>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (em_is_admin()): ?>
                <!-- SENDING MAILBOXES -->
                <div class="card shadow-sm border-light">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <h6 class="mb-0">Sending Mailboxes</h6>
                            <button class="btn btn-primary btn-sm" id="emAccNewBtn">
                                <i class="fas fa-plus"></i> Add Mailbox
                            </button>
                        </div>
                        <div class="border rounded p-3 mb-3 mt-3 d-none" id="emAccForm">
                            <input type="hidden" id="emAccId" value="">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Label</label>
                                    <input type="text" id="emAccTitle" class="form-control" placeholder="e.g. Invoices">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">From Address</label>
                                    <input type="email" id="emAccFromEmail" class="form-control"
                                        placeholder="invoice@syedpro.com">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">From Name</label>
                                    <input type="text" id="emAccFromName" class="form-control"
                                        placeholder="Syed Professional Services">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">SMTP Host</label>
                                    <input type="text" id="emAccHost" class="form-control"
                                        placeholder="smtp.office365.com">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Port</label>
                                    <input type="number" id="emAccPort" class="form-control" value="587">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Encryption</label>
                                    <select id="emAccSecure" class="form-control">
                                        <option value="tls">TLS</option>
                                        <option value="ssl">SSL</option>
                                        <option value="none">None</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">SMTP Username</label>
                                    <input type="text" id="emAccUsername" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">SMTP Password</label>
                                    <input type="password" id="emAccPassword" class="form-control"
                                        autocomplete="new-password">
                                </div>
                                <div class="col-md-8 d-flex align-items-end">
                                    <div class="form-check me-4">
                                        <input class="form-check-input" type="checkbox" id="emAccActive" checked>
                                        <label class="form-check-label" for="emAccActive">Active</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="emAccDefault">
                                        <label class="form-check-label" for="emAccDefault">Make default</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-primary btn-sm" id="emAccSaveBtn">Save Mailbox</button>
                                    <button class="btn btn-light btn-sm" id="emAccCancelBtn">Cancel</button>
                                </div>
                            </div>
                        </div>

                        <table class="table table-bordered table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Label</th>
                                    <th>From</th>
                                    <th>SMTP</th>
                                    <th>Status</th>
                                    <th style="width:24%">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($accounts as $a): $c = $a['cfg']; ?>
                                    <tr>
                                        <td>
                                            <?= htmlspecialchars($a['title']) ?>
                                            <?php if ((int) $a['is_default'] === 1): ?>
                                                <span class="badge badge-success ms-1">Default</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="em-sub">
                                            <?= htmlspecialchars($c['from_name'] ?? '') ?><br>
                                            <?= htmlspecialchars($c['from_email'] ?? '') ?>
                                        </td>
                                        <td class="em-sub">
                                            <?= htmlspecialchars($c['host'] ?? '') ?>:<?= (int) ($c['port'] ?? 0) ?>
                                            (<?= htmlspecialchars(strtoupper($c['secure'] ?? '')) ?>)<br>
                                            <?= htmlspecialchars($c['username'] ?? '') ?>
                                        </td>
                                        <td>
                                            <span
                                                class="badge <?= (int) $a['is_active'] === 1 ? 'badge-success' : 'badge-danger' ?>">
                                                <?= (int) $a['is_active'] === 1 ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </td>
                                        <td class="text-nowrap">
                                            <button class="btn btn-sm btn-warning me-1 emAccEdit"
                                                data-a='<?= htmlspecialchars(json_encode($a), ENT_QUOTES) ?>'>Edit</button>
                                            <button class="btn btn-sm btn-secondary me-1 emAccTest"
                                                data-id="<?= (int) $a['id'] ?>">Test</button>
                                            <?php if ((int) $a['is_default'] !== 1): ?>
                                                <button class="btn btn-sm btn-info me-1 emAccDefault"
                                                    data-id="<?= (int) $a['id'] ?>">Default</button>
                                            <?php endif; ?>
                                            <button class="btn btn-sm btn-danger emAccDel"
                                                data-id="<?= (int) $a['id'] ?>">Del</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <?php include "includes/footer.php"; ?>

    <script>
        let emEditor = null;
        ClassicEditor.create(document.querySelector('#emBody')).then(e => emEditor = e).catch(() => { });

        function emPost(data) {
            const fd = new FormData();
            Object.keys(data).forEach(k => fd.append(k, data[k]));
            return fetch('client_email.php', { method: 'POST', body: fd }).then(r => r.json());
        }

        function emDone(d) {
            if (!d.success) return Swal.fire('Error', d.message || 'Request failed.', 'error');
            Swal.fire({ icon: 'success', title: d.message, timer: 1300, showConfirmButton: false })
                .then(() => location.reload());
        }

        function emVisNote() {
            const pub = document.getElementById('emVisPublic').checked;
            document.getElementById('emVisNote').innerHTML = pub
                ? '<span class="em-note-public">Public — if you select public it will show to everyone in the CRM.</span>'
                : '';
        }
        document.getElementById('emVisPublic').addEventListener('change', emVisNote);
        document.getElementById('emVisPrivate').addEventListener('change', emVisNote);

        document.getElementById('emNewBtn').addEventListener('click', () => {
            document.getElementById('emId').value = '';
            document.getElementById('emTitle').value = '';
            document.getElementById('emSubject').value = '';
            document.getElementById('emVisPrivate').checked = true;
            emVisNote();
            if (emEditor) emEditor.setData('');
            document.getElementById('emFormTitle').textContent = 'Add New Template';
            document.getElementById('emFormCard').classList.remove('d-none');
        });

        document.getElementById('emCancelBtn').addEventListener('click', () =>
            document.getElementById('emFormCard').classList.add('d-none'));

        document.querySelectorAll('.em-tag').forEach(el => el.addEventListener('click', () => {
            navigator.clipboard?.writeText(el.textContent.trim());
            el.style.background = '#cfe9d4';
            setTimeout(() => el.style.background = '', 500);
        }));

        document.querySelectorAll('.emEdit').forEach(b => b.addEventListener('click', () => {
            const t = JSON.parse(b.dataset.t);
            document.getElementById('emId').value = t.id;
            document.getElementById('emTitle').value = t.title;
            document.getElementById('emSubject').value = t.subject;
            document.getElementById('emVisPublic').checked = (t.visibility === 'public');
            document.getElementById('emVisPrivate').checked = (t.visibility !== 'public');
            emVisNote();
            if (emEditor) emEditor.setData(t.body || '');
            document.getElementById('emFormTitle').textContent = 'Edit Template';
            document.getElementById('emFormCard').classList.remove('d-none');
            document.getElementById('emFormCard').scrollIntoView({ behavior: 'smooth', block: 'center' });
        }));

        document.getElementById('emSaveBtn').addEventListener('click', () => {
            emPost({
                em_action: 'tpl_save',
                id: document.getElementById('emId').value,
                title: document.getElementById('emTitle').value.trim(),
                subject: document.getElementById('emSubject').value.trim(),
                body: emEditor ? emEditor.getData() : '',
                visibility: document.getElementById('emVisPublic').checked ? 'public' : 'private'
            }).then(emDone);
        });

        document.querySelectorAll('.emDel').forEach(b => b.addEventListener('click', () => {
            Swal.fire({
                title: 'Delete this template?', icon: 'warning', showCancelButton: true,
                confirmButtonColor: '#d33', confirmButtonText: 'Yes, delete it!'
            }).then(r => {
                if (r.isConfirmed) emPost({ em_action: 'tpl_delete', id: b.dataset.id }).then(emDone);
            });
        }));

        /* ── Mailboxes (admin) ────────────────────────────────── */
        const emAccForm = document.getElementById('emAccForm');
        if (emAccForm) {
            document.getElementById('emAccNewBtn').addEventListener('click', () => {
                ['emAccId', 'emAccTitle', 'emAccFromEmail', 'emAccFromName', 'emAccHost', 'emAccUsername', 'emAccPassword']
                    .forEach(id => document.getElementById(id).value = '');
                document.getElementById('emAccPort').value = 587;
                document.getElementById('emAccSecure').value = 'tls';
                document.getElementById('emAccActive').checked = true;
                document.getElementById('emAccDefault').checked = false;
                emAccForm.classList.remove('d-none');
            });

            document.getElementById('emAccCancelBtn').addEventListener('click', () =>
                emAccForm.classList.add('d-none'));

            document.querySelectorAll('.emAccEdit').forEach(b => b.addEventListener('click', () => {
                const a = JSON.parse(b.dataset.a), c = a.cfg || {};
                document.getElementById('emAccId').value = a.id;
                document.getElementById('emAccTitle').value = a.title;
                document.getElementById('emAccFromEmail').value = c.from_email || '';
                document.getElementById('emAccFromName').value = c.from_name || '';
                document.getElementById('emAccHost').value = c.host || '';
                document.getElementById('emAccPort').value = c.port || 587;
                document.getElementById('emAccSecure').value = c.secure || 'tls';
                document.getElementById('emAccUsername').value = c.username || '';
                document.getElementById('emAccPassword').value = '';
                document.getElementById('emAccActive').checked = (a.is_active == 1);
                document.getElementById('emAccDefault').checked = (a.is_default == 1);
                emAccForm.classList.remove('d-none');
                emAccForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }));

            document.getElementById('emAccSaveBtn').addEventListener('click', () => {
                emPost({
                    em_action: 'acc_save',
                    id: document.getElementById('emAccId').value,
                    title: document.getElementById('emAccTitle').value.trim(),
                    from_email: document.getElementById('emAccFromEmail').value.trim(),
                    from_name: document.getElementById('emAccFromName').value.trim(),
                    host: document.getElementById('emAccHost').value.trim(),
                    port: document.getElementById('emAccPort').value,
                    secure: document.getElementById('emAccSecure').value,
                    username: document.getElementById('emAccUsername').value.trim(),
                    password: document.getElementById('emAccPassword').value,
                    is_active: document.getElementById('emAccActive').checked ? 1 : '',
                    is_default: document.getElementById('emAccDefault').checked ? 1 : ''
                }).then(emDone);
            });

            document.querySelectorAll('.emAccDefault').forEach(b => b.addEventListener('click', () =>
                emPost({ em_action: 'acc_default', id: b.dataset.id }).then(emDone)));

            document.querySelectorAll('.emAccDel').forEach(b => b.addEventListener('click', () => {
                Swal.fire({
                    title: 'Remove this mailbox?', icon: 'warning', showCancelButton: true,
                    confirmButtonColor: '#d33', confirmButtonText: 'Yes, remove it'
                }).then(r => {
                    if (r.isConfirmed) emPost({ em_action: 'acc_delete', id: b.dataset.id }).then(emDone);
                });
            }));

            document.querySelectorAll('.emAccTest').forEach(b => b.addEventListener('click', () => {
                Swal.fire({
                    title: 'Send a test email', input: 'email', inputLabel: 'Send the test to',
                    showCancelButton: true, confirmButtonText: 'Send test', showLoaderOnConfirm: true,
                    preConfirm: addr => emPost({ em_action: 'acc_test', id: b.dataset.id, test_to: addr || '' })
                        .then(d => { if (!d.success) { Swal.showValidationMessage(d.message); return false; } return d; })
                        .catch(() => { Swal.showValidationMessage('Request failed.'); return false; })
                }).then(r => { if (r.isConfirmed && r.value) Swal.fire('Sent!', r.value.message, 'success'); });
            }));
        }
    </script>
</body>

</html>
