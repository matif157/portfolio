<?php
/*  AI ASSISTANT  ─  JSON endpoint
    -------------------------------------------------------------------------
    Auth-gated POST endpoint. Routes AI actions:
      chat            natural-language message; may run a secure read query
      ocr             extract fields from an uploaded image (Gemini vision)
      preview_client  build a "verify to insert" preview for a new client
      confirm_client  execute the confirmed new-client insert
      preview_note    build a preview for adding a note/comment
      confirm_note    execute the confirmed note insert
      export_csv      stream a query result as a downloadable .csv
      export_pdf      stream a query result as a downloadable .pdf (dompdf)
    Reads use a strict SELECT-only validator + START TRANSACTION READ ONLY.
    Writes always pass through preview -> confirm and are gated by ai.write.
    ------------------------------------------------------------------------- */
session_start();
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

include "includes/db.php";
include "includes/auth.php";
include "includes/roles.php";
include "includes/ai_helper.php";
include "includes/activity_log.php";

if (!ai_can_use()) {
    echo json_encode(array('ok' => false, 'error' => 'You do not have permission to use the AI Assistant.'));
    exit;
}
if (ai_get('ai_enabled', '1') !== '1') {
    // The launcher is hidden when disabled; if reached anyway, block everything.
    echo json_encode(array('ok' => false, 'error' => 'The AI Assistant is disabled in AI Settings.'));
    exit;
}
$action = $_POST['action'] ?? '';
$uid = (int) ($_SESSION['user_id'] ?? 0);

/* ---------------- Secure read query runner ------------------------------ */
function ai_run_select($raw)
{
    global $con;
    $sql = trim($raw);
    $sql = preg_replace('/;\s*$/', '', $sql);
    if ($sql === '') {
        return array('error' => 'Empty query.');
    }
    if (preg_match('/;/', $sql)) {
        return array('error' => 'Multiple statements are not allowed.');
    }
    if (!preg_match('/^\s*SELECT/i', $sql)) {
        return array('error' => 'Only SELECT queries are allowed (read-only).');
    }
    $bad = array('insert', 'update', 'delete', 'drop', 'alter', 'create', 'truncate',
        'grant', 'into', 'outfile', 'dumpfile', 'load_file', 'sleep',
        'benchmark', '@@', 'procedure', 'handler', 'replace ', 'rename', 'call ');
    foreach ($bad as $b) {
        if (stripos($sql, $b) !== false) {
            return array('error' => 'Query blocked (contains disallowed keyword: ' . $b . ').');
        }
    }
    $sql = preg_replace('/\s+LIMIT\s+\d+(?:\s*,\s*\d+)?\s*$/i', ' ', $sql);
    $sql .= ' LIMIT 200';
    $con->query('START TRANSACTION READ ONLY');
    $res = $con->query($sql);
    $con->query('COMMIT');
    if (!$res) {
        return array('error' => 'Query error: ' . $con->error);
    }
    $rows = array();
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    return array('rows' => $rows);
}

/* ---------------- Gemini text/tool helper --------------------------------- */
function ai_chat_prompt()
{
    $schema = ai_db_schema();
    $mods = ai_module_descriptions();
    $desc = '';
    foreach ($mods as $m => $txt) {
        $desc .= "- " . $m . ": " . $txt . "\n";
    }
    $rels = implode("\n", $schema['relationships']);
    return "You are the AI assistant for a client/accounting CRM (Syed Professional Services). "
        . "You help staff find and understand CRM data, generate files, and (with approval) add data.\n"
        . "MODULES:\n$desc\n"
        . "JOIN RELATIONSHIPS (use these to link data):\n$rels\n"
        . "RULES:\n"
        . "- To answer data questions, produce a valid MySQL SELECT. Put the SQL alone on its own line, and above it a short human-friendly explanation.\n"
        . "- Use ONLY tables listed above. Use the relationships to join. Filter by the user's question.\n"
        . "- To ADD a new client, output exactly a line starting with NEWCLIENT: followed by a single-line JSON object of fields e.g. {name, lname, bus_name, phone, cell_ph, prm_email, address, city, state, zip, type, ss}. Then a short confirmation sentence.\n"
        . "- To ADD a note to a client, output NOTE: <member_id> | <text>.\n"
        . "- Never claim to write data without the NEWCLIENT/NOTE markers; instead say what you would add and ask to confirm.\n"
        . "- If the user just chats, reply conversationally and helpfully.\n"
        . "- Keep answers concise.";
}

/* ---------------- Handlers ------------------------------------------------ */
switch ($action) {

    case 'chat': {
        $message = trim($_POST['message'] ?? '');
        if ($message === '') { echo json_encode(array('ok' => false, 'error' => 'Empty message.')); break; }
        $r = gemini_call(array(array('text' => $message)), ai_chat_prompt());
        if (isset($r['error'])) {
            /* Offline / no-internet fallback: reply locally for basic queries. */
            $local = ai_local_answer($message);
            if (isset($local['text'])) {
                echo json_encode(array('ok' => true, 'type' => 'text', 'text' => $local['text']));
            } else {
                echo json_encode(array('ok' => false, 'error' => $r['error']));
            }
            break;
        }
        $text = trim($r['text']);

        // NEWCLIENT intent
        if (preg_match('/NEWCLIENT:\s*(\{.*\})/is', $text, $m)) {
            $fields = json_decode($m[1], true);
            if (!is_array($fields)) { echo json_encode(array('ok' => true, 'type' => 'text', 'text' => $text)); break; }
            echo json_encode(array('ok' => true, 'type' => 'newclient', 'fields' => $fields, 'text' => 'New client detected. Review the preview below.'));
            break;
        }
        // NOTE intent
        if (preg_match('/NOTE:\s*(\d+)\s*\|\s*(.+)/is', $text, $m)) {
            echo json_encode(array('ok' => true, 'type' => 'note',
                'member_id' => (int) $m[1], 'text_note' => trim($m[2]),
                'text' => 'Add a note to client #' . (int) $m[1] . '? Preview and confirm below.'));
            break;
        }
        // SELECT intent -> run secure read
        if (preg_match('/SELECT\b.*/is', $text, $m)) {
            $sql = trim($m[0]);
            $sql = preg_replace("/[\r\n]+/", ' ', $sql);
            if (ai_get('ai_allow_read', '1') !== '1') {
                echo json_encode(array('ok' => false, 'error' => 'Reading data is disabled in AI settings.')); break;
            }
            $result = ai_run_select($sql);
            if (isset($result['error'])) {
                echo json_encode(array('ok' => true, 'type' => 'text', 'text' => $text . "\n\n[Query error: " . $result['error'] . ']'));
                break;
            }
            echo json_encode(array('ok' => true, 'type' => 'table', 'sql' => $sql, 'rows' => $result['rows'], 'text' => $text));
            break;
        }
        echo json_encode(array('ok' => true, 'type' => 'text', 'text' => $text));
        break;
    }

    case 'ocr': {
        if (ai_get('ai_allow_ocr', '1') !== '1') { echo json_encode(array('ok' => false, 'error' => 'Image OCR is disabled.')); break; }
        $b64 = $_POST['image'] ?? '';
        $mime = $_POST['mime'] ?? 'image/png';
        if ($b64 === '') { echo json_encode(array('ok' => false, 'error' => 'No image received.')); break; }
        $prompt = "Extract the following fields from this document/image and return them as a single-line JSON object with keys: name, lname, bus_name, phone, cell_ph, prm_email, address, city, state, zip, ss, ein, dob. Use empty strings for missing. Then on the next line give a one-line summary of what the document is.";
        $r = gemini_call(array(array('text' => $prompt, 'image_b64' => $b64, 'mime' => $mime)));
        if (isset($r['error'])) { echo json_encode(array('ok' => false, 'error' => $r['error'])); break; }
        $text = trim($r['text']);
        $fields = array();
        if (preg_match('/\{.*\}/s', $text, $m)) {
            $fields = json_decode($m[0], true);
            if (!is_array($fields)) $fields = array();
        }
        echo json_encode(array('ok' => true, 'type' => 'ocr', 'fields' => $fields, 'summary' => $text));
        break;
    }

    case 'preview_client': {
        if (!ai_can_write()) { echo json_encode(array('ok' => false, 'error' => 'You need the AI write permission.')); break; }
        if (ai_get('ai_allow_write', '1') !== '1') { echo json_encode(array('ok' => false, 'error' => 'Inserting data is disabled in AI settings.')); break; }
        $f = $_POST['fields'] ?? array();
        $preview = array();
        $preview['name'] = trim($f['name'] ?? '');
        $preview['lname'] = trim($f['lname'] ?? '');
        $preview['bus_name'] = trim($f['bus_name'] ?? '');
        $preview['phone'] = trim($f['phone'] ?? '');
        $preview['cell_ph'] = trim($f['cell_ph'] ?? '');
        $preview['prm_email'] = trim($f['prm_email'] ?? '');
        $preview['address'] = trim($f['address'] ?? '');
        $preview['city'] = trim($f['city'] ?? '');
        $preview['state'] = trim($f['state'] ?? '');
        $preview['zip'] = trim($f['zip'] ?? '');
        $preview['ss'] = trim($f['ss'] ?? '');
        $preview['ein'] = trim($f['ein'] ?? '');
        $preview['type'] = !empty($f['type']) ? (int) $f['type'] : 1;
        if ($preview['name'] === '') { echo json_encode(array('ok' => false, 'error' => 'A first name is required to add a client.')); break; }
        echo json_encode(array('ok' => true, 'preview' => $preview));
        break;
    }

    case 'confirm_client': {
        if (!ai_can_write()) { echo json_encode(array('ok' => false, 'error' => 'You need the AI write permission.')); break; }
        if (ai_get('ai_allow_write', '1') !== '1') { echo json_encode(array('ok' => false, 'error' => 'Inserting data is disabled.')); break; }
        if (ai_get('ai_require_confirm', '1') === '1' && (($_POST['confirmed'] ?? '') !== '1')) {
            echo json_encode(array('ok' => false, 'error' => 'Confirmation required.')); break;
        }
        $f = $_POST['fields'] ?? array();
        $name = mysqli_real_escape_string($con, trim($f['name'] ?? ''));
        $lname = mysqli_real_escape_string($con, trim($f['lname'] ?? ''));
        $bus_name = mysqli_real_escape_string($con, trim($f['bus_name'] ?? ''));
        $phone = mysqli_real_escape_string($con, trim($f['phone'] ?? ''));
        $cell = mysqli_real_escape_string($con, trim($f['cell_ph'] ?? ''));
        $email = mysqli_real_escape_string($con, trim($f['prm_email'] ?? ''));
        $address = mysqli_real_escape_string($con, trim($f['address'] ?? ''));
        $city = mysqli_real_escape_string($con, trim($f['city'] ?? ''));
        $state = mysqli_real_escape_string($con, trim($f['state'] ?? ''));
        $zip = mysqli_real_escape_string($con, trim($f['zip'] ?? ''));
        $ss = mysqli_real_escape_string($con, trim($f['ss'] ?? ''));
        $ein = mysqli_real_escape_string($con, trim($f['ein'] ?? ''));
        $type = !empty($f['type']) ? (int) $f['type'] : 1;
        if ($name === '') { echo json_encode(array('ok' => false, 'error' => 'A first name is required.')); break; }
        $today = date('Y-m-d');
        $dob = trim($f['dob'] ?? '');
        if ($dob === '') { $dob = $today; }
        $sql = "INSERT INTO members (
            file, name, dba, ein, ss, tf, dob, phone, cell_ph, other_ph, overseas_ph,
            prm_email, sec_email, fax, mem_pic, type, added_by, mname, lname, bus_name, member_pos,
            hzip, hstate, hcity, hstreet, city, state, address, zip,
            occupation, idcard, pmb, hapartment, datelast, dateupdated,
            duedate, closingm, incorp_date, incorpstate, user_id, business_address, client_since, specialClient, bus_start_date,
            hcounty, bcounty, category, ach_payer, payer_frequency, status
        ) VALUES (
            '', '$name', '', '$ein', '$ss', '', '$dob', '$phone', '$cell', '', '',
            '$email', '', '', '', '$type', '$uid', '', '$lname', '$bus_name', '',
            '', '', '', '', '$city', '$state', '$address', '$zip',
            '', '', '', '', '$today', '$today',
            '', '', '$today', '', '$uid', '', '$today', '0', '$today',
            '', '', '', '0', 'None', '1'
        )";
        if (!mysqli_query($con, $sql)) { echo json_encode(array('ok' => false, 'error' => 'Insert failed: ' . $con->error)); break; }
        $newId = mysqli_insert_id($con);
        if (function_exists('logActivity')) {
            @logActivity($con, $uid, 'CREATE', 'member', $newId, 'AI added client ' . $name);
        }
        echo json_encode(array('ok' => true, 'member_id' => $newId, 'message' => "Client #$newId ($name) added."));
        break;
    }

    case 'preview_note': {
        if (!ai_can_write()) { echo json_encode(array('ok' => false, 'error' => 'You need the AI write permission.')); break; }
        if (ai_get('ai_allow_write', '1') !== '1') { echo json_encode(array('ok' => false, 'error' => 'Inserting data is disabled.')); break; }
        $mid = (int) ($_POST['member_id'] ?? 0);
        $note = trim($_POST['text_note'] ?? '');
        if ($mid <= 0 || $note === '') { echo json_encode(array('ok' => false, 'error' => 'A client id and note text are required.')); break; }
        echo json_encode(array('ok' => true, 'preview' => array('member_id' => $mid, 'note' => $note)));
        break;
    }

    case 'confirm_note': {
        if (!ai_can_write()) { echo json_encode(array('ok' => false, 'error' => 'You need the AI write permission.')); break; }
        if (ai_get('ai_allow_write', '1') !== '1') { echo json_encode(array('ok' => false, 'error' => 'Inserting data is disabled.')); break; }
        if (ai_get('ai_require_confirm', '1') === '1' && (($_POST['confirmed'] ?? '') !== '1')) {
            echo json_encode(array('ok' => false, 'error' => 'Confirmation required.')); break;
        }
        $mid = (int) ($_POST['member_id'] ?? 0);
        $note = mysqli_real_escape_string($con, trim($_POST['text_note'] ?? ''));
        if ($mid <= 0 || $note === '') { echo json_encode(array('ok' => false, 'error' => 'Invalid note.')); break; }
        if (!mysqli_query($con, "INSERT INTO notestab (member_id, notes_detail, user_id) VALUES ('$mid','$note','$uid')")) {
            echo json_encode(array('ok' => false, 'error' => 'Insert failed: ' . $con->error)); break;
        }
        $logNote = mysqli_insert_id($con);
        if (function_exists('logActivity')) {
            @logActivity($con, $uid, 'CREATE', 'note', $mid, 'AI added note to client #' . $mid);
        }
        echo json_encode(array('ok' => true, 'message' => "Note added to client #$mid."));
        break;
    }

    case 'export_csv': {
        if (ai_get('ai_allow_download', '1') !== '1') { echo json_encode(array('ok' => false, 'error' => 'Downloads are disabled.')); break; }
        $sql = $_POST['sql'] ?? '';
        $result = ai_run_select($sql);
        if (isset($result['error'])) { echo json_encode(array('ok' => false, 'error' => $result['error'])); break; }
        $rows = $result['rows'];
        if (count($rows) === 0) { echo json_encode(array('ok' => false, 'error' => 'No rows to export.')); break; }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="ai_export_' . date('Ymd_His') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, array_keys($rows[0]));
        foreach ($rows as $row) { fputcsv($out, $row); }
        fclose($out);
        exit;
    }

    case 'export_pdf': {
        if (ai_get('ai_allow_download', '1') !== '1') { echo json_encode(array('ok' => false, 'error' => 'Downloads are disabled.')); break; }
        $title = htmlspecialchars($_POST['title'] ?? 'AI Export');
        $html = '<h2>' . $title . '</h2>';
        $sql = $_POST['sql'] ?? '';
        $result = ai_run_select($sql);
        if (isset($result['error'])) { echo json_encode(array('ok' => false, 'error' => $result['error'])); break; }
        $rows = $result['rows'];
        if (count($rows) === 0) { echo json_encode(array('ok' => false, 'error' => 'No rows to export.')); break; }
        $html .= '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;font-family:sans-serif;font-size:10px;width:100%"><thead><tr>';
        foreach (array_keys($rows[0]) as $h) { $html .= '<th style="background:#eee">' . htmlspecialchars($h) . '</th>'; }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) { $html .= '<tr>'; foreach ($row as $v) { $html .= '<td>' . htmlspecialchars((string) $v) . '</td>'; } $html .= '</tr>'; }
        $html .= '</tbody></table>';
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="ai_export_' . date('Ymd_His') . '.pdf"');
        include_once 'dompdf/autoload.inc.php';
        $dompdf = new Dompdf\Dompdf(array('isRemoteEnabled' => true));
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        echo $dompdf->output();
        exit;
    }

    default:
        echo json_encode(array('ok' => false, 'error' => 'Unknown action.'));
        break;
}
