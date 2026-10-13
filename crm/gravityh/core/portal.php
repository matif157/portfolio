<?php
/**
 * GRAVITY CRM - Client Portal Core (SPS 2040 Phase 8)
 * Sec 26: separate auth scope over the same business data.
 * Staff session and portal session are fully distinct.
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

/** Current portal user (client), or null when logged out. */
function portal_user(): ?array {
    if (empty($_SESSION['portal_uid'])) {
        return null;
    }
    static $u = null;
    static $loaded = false;
    if (!$loaded) {
        $u = db_row(
            "SELECT pu.*, c.first_name, c.last_name, c.email AS client_email, c.id AS client_id
             FROM portal_users pu JOIN clients c ON c.id = pu.client_id
             WHERE pu.id = ? AND pu.status = 'active'",
            [$_SESSION['portal_uid']]
        );
        $loaded = true;
    }
    return $u;
}

function portal_require_login(): void {
    if (!portal_user()) {
        redirect('portal_login.php');
    }
}

/** Authenticate a portal account (password + lockout). */
function portal_authenticate(string $email, string $password): array {
    $pu = db_row('SELECT * FROM portal_users WHERE email = ?', [$email]);

    if ($pu && !empty($pu['locked_until']) && strtotime((string)$pu['locked_until']) > time()) {
        return ['ok' => false, 'message' => 'Account temporarily locked. Contact the office.'];
    }

    // Generic error on unknown or bad password (no enumeration).
    if (!$pu || !password_verify($password, (string)$pu['password_hash'])) {
        if ($pu) {
            db_execute('UPDATE portal_users SET failed_attempts = failed_attempts + 1 WHERE id = ?', [(int)$pu['id']]);
            if ((int)($pu['failed_attempts'] + 1) >= 5) {
                db_execute('UPDATE portal_users SET locked_until = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id = ?', [(int)$pu['id']]);
                log_security_event('portal_account_locked', 'Client portal account locked after 5 failed attempts.', (int)$pu['id']);
            }
        }
        return ['ok' => false, 'message' => 'Invalid email or password.'];
    }

    if ((string)$pu['status'] !== 'active') {
        return ['ok' => false, 'message' => 'This account is not active.'];
    }

    db_execute('UPDATE portal_users SET failed_attempts = 0, locked_until = NULL, last_login_at = NOW() WHERE id = ?', [(int)$pu['id']]);
    session_regenerate_id(true);
    $_SESSION['portal_uid'] = (int)$pu['id'];
    $_SESSION['portal_meta'] = [
        'ip'          => $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent'  => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        'logged_in_at'=> date('Y-m-d H:i:s'),
    ];
    log_security_event('portal_login', 'Client portal login: ' . $email, (int)$pu['id'], 'info');

    return ['ok' => true, 'user' => portal_user()];
}

/** Businesses the logged-in portal client can see. */
function portal_businesses(): array {
    $u = portal_user();
    if (!$u) { return []; }
    return db_query('SELECT * FROM businesses WHERE client_id = ? ORDER BY id ASC', [(int)$u['client_id']]);
}

/** Active service assignments for the client. */
function portal_services(): array {
    $u = portal_user();
    if (!$u) { return []; }
    return db_query(
        'SELECT cs.id AS assignment_id, s.name AS service, cs.status, cs.start_date, cs.current_step_order
         FROM client_services cs JOIN services s ON s.id = cs.service_id
         WHERE cs.client_id = ? AND cs.status = \'active\' ORDER BY cs.id DESC',
        [(int)$u['client_id']]
    );
}

/** Open requirements across the client's assignments. */
function portal_requirements(): array {
    $u = portal_user();
    if (!$u) { return []; }
    return db_query(
        "SELECT csr.id, csr.status, r.title, r.description, s.name AS service
         FROM client_service_requirements csr
         JOIN client_services cs ON cs.id = csr.client_services_id
         JOIN services s ON s.id = cs.service_id
         JOIN service_requirements r ON r.id = csr.requirement_id
         WHERE cs.client_id = ?
           AND csr.status IN ('not_started','requested','pending_client','under_review','received')
         ORDER BY csr.id DESC LIMIT 30",
        [(int)$u['client_id']]
    );
}

/** Invoices for the client with balance. */
function portal_invoices(): array {
    $u = portal_user();
    if (!$u) { return []; }
    return db_query(
        'SELECT id, invoice_number, invoice_date, due_date, total_amount,
                amount_paid AS paid_amount, amount_due AS balance_due, status
         FROM invoices WHERE client_id = ? ORDER BY invoice_date DESC LIMIT 40',
        [(int)$u['client_id']]
    );
}

/** Recent documents shared with the client. */
function portal_documents(): array {
    $u = portal_user();
    if (!$u) { return []; }
    return db_query(
        'SELECT id, title, file_name, file_type, file_path, file_size, uploaded_at AS created_at
         FROM documents WHERE client_id = ? ORDER BY id DESC LIMIT 30',
        [(int)$u['client_id']]
    );
}

/** Handle a portal document upload with server-side validation. */
function portal_handle_upload(): array {
    $u = portal_user();
    if (!$u || empty($_FILES['document'])) {
        return ['ok' => false, 'message' => 'Missing file or session.'];
    }

    $f = $_FILES['document'];
    $okExt = ['pdf','jpg','jpeg','png','doc','docx','xls','xlsx','csv','txt'];
    $ext = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $okExt, true)) {
        return ['ok' => false, 'message' => 'Unsupported file type.'];
    }
    if ((int)$f['size'] > (int)get_setting('upload_max_size', '5242880')) {
        return ['ok' => false, 'message' => 'File too large.'];
    }
    if ((int)$f['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => 'Upload failed (error ' . (int)$f['error'] . ').'];
    }

    $subdir = 'portal/' . (int)$u['client_id'];
    $dir = UPLOAD_PATH . '/' . $subdir;
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }

    $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', (string)$f['name']);
    $safe = uniqid('p', true) . '_' . $safe;
    $dest = $dir . '/' . $safe;

    if (!move_uploaded_file($f['tmp_name'], $dest)) {
        return ['ok' => false, 'message' => 'Could not store file.'];
    }

    db_execute(
        'INSERT INTO documents (client_id, title, file_path, file_name, file_size, file_type, is_shared, uploaded_by)
         VALUES (?, ?, ?, ?, ?, ?, 1, NULL)',
        [
            (int)$u['client_id'],
            preg_replace('/\.[^.]+$/', '', (string)$f['name']),
            $subdir . '/' . $safe,
            $safe,
            (int)$f['size'],
            $ext,
        ]
    );
    $doc_id = db_last_id();

    if (function_exists('bus_emit')) {
        bus_emit(EVT_DOCUMENT_UPLOADED, [], [
            'source' => 'document', 'source_id' => $doc_id, 'client_id' => (int)$u['client_id'],
        ]);
    }

    return ['ok' => true, 'message' => 'Document uploaded.', 'document_id' => $doc_id];
}

/** Record a client message from the portal onto the client timeline. */
function portal_message(string $body): bool {
    $u = portal_user();
    if (!$u) { return false; }
    db_execute(
        'INSERT INTO communications (client_id, user_id, channel, direction, subject, message_body, status)
         VALUES (?, NULL, \'portal\', \'inbound\', \'Client message via portal\', ?, \'received\')',
        [(int)$u['client_id'], mb_substr($body, 0, 4000)]
    );
    return true;
}