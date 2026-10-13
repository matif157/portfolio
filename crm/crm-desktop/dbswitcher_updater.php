<?php
session_start();

const DB_INSPECTOR_PASSWORD = 'skipper@';

const DB_INSPECTOR_TIMEOUT = 900; // 15 minutes

if (isset($_POST['_logout'])) {
    session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

if (!empty($_POST['_password'])) {
    if ($_POST['_password'] === DB_INSPECTOR_PASSWORD) {
        $_SESSION['db_inspector_auth'] = true;
        $_SESSION['db_inspector_last_active'] = time();
    } else {
        $loginError = 'Incorrect password.';
    }
}

// Auto-logout on inactivity
if (!empty($_SESSION['db_inspector_auth'])) {
    if (time() - ($_SESSION['db_inspector_last_active'] ?? 0) > DB_INSPECTOR_TIMEOUT) {
        session_destroy();
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?expired=1');
        exit;
    }
    $_SESSION['db_inspector_last_active'] = time();
}

$sessionExpired = isset($_GET['expired']);

if (empty($_SESSION['db_inspector_auth'])) {
    $loginError = $loginError ?? null;
    ?><!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DB Inspector — Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&family=Ubuntu+Mono:wght@400;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Ubuntu', sans-serif;
            background: #1A1A1A;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: #252525;
            border: 1px solid #3A3A3A;
            border-radius: 12px;
            padding: 40px 36px 32px;
            width: 100%;
            max-width: 360px;
            box-shadow: 0 8px 32px rgba(0,0,0,.5);
        }
        .login-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 28px;
        }
        .login-logo .logo-icon {
            width: 42px; height: 42px;
            background: #E95420;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; color: #fff;
            box-shadow: 0 2px 12px rgba(233,84,32,.45);
        }
        .login-logo .logo-text strong { display: block; color: #E5E5E5; font-size: 1rem; }
        .login-logo .logo-text span   { font-size: .7rem; color: #666; font-family: 'Ubuntu Mono', monospace; }
        label { display: block; font-size: .78rem; color: #8E8E8E; margin-bottom: 6px; letter-spacing: .3px; }
        .input-wrap { position: relative; margin-bottom: 18px; }
        .input-wrap input {
            width: 100%;
            background: #1A1A1A;
            border: 1px solid #3A3A3A;
            border-radius: 7px;
            padding: 10px 40px 10px 14px;
            font-size: .88rem;
            color: #E5E5E5;
            font-family: 'Ubuntu Mono', monospace;
            outline: none;
            transition: border .18s;
        }
        .input-wrap input:focus { border-color: #E95420; }
        .input-wrap .eye-btn {
            position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
            background: none; border: none; color: #555; cursor: pointer; font-size: .9rem;
            padding: 0;
        }
        .input-wrap .eye-btn:hover { color: #E95420; }
        .btn-login {
            width: 100%;
            background: #E95420;
            color: #fff;
            border: none;
            border-radius: 7px;
            padding: 10px 0;
            font-size: .88rem;
            font-family: 'Ubuntu', sans-serif;
            font-weight: 500;
            cursor: pointer;
            transition: background .15s;
        }
        .btn-login:hover { background: #C7461A; }
        .error-msg {
            background: rgba(220,53,69,.12);
            border-left: 3px solid #dc3545;
            color: #dc3545;
            border-radius: 5px;
            padding: 9px 13px;
            font-size: .78rem;
            margin-bottom: 16px;
        }
        .lock-icon { text-align: center; font-size: 2rem; margin-bottom: 6px; opacity: .25; }
    </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <div class="login-card">
        <div class="login-logo">
            <div class="logo-icon"><i class="bi bi-database-fill-lock"></i></div>
            <div class="logo-text">
                <strong>DB Inspector</strong>
                <span>authentication required</span>
            </div>
        </div>
        <?php if ($sessionExpired): ?>
            <div class="error-msg" style="border-color:#E95420;color:#E95420;background:rgba(233,84,32,.1)">
                <i class="bi bi-clock-history"></i> Session expired due to inactivity. Please log in again.
            </div>
        <?php elseif ($loginError): ?>
            <div class="error-msg"><i class="bi bi-exclamation-octagon-fill"></i> <?= htmlspecialchars($loginError) ?></div>
        <?php endif; ?>
        <form method="post">
            <label for="pwd">Password</label>
            <div class="input-wrap">
                <input type="password" id="pwd" name="_password" autofocus autocomplete="current-password" placeholder="Enter password">
                <button type="button" class="eye-btn" onclick="togglePwd()"><i class="bi bi-eye" id="eyeIcon"></i></button>
            </div>
            <button type="submit" class="btn-login"><i class="bi bi-unlock-fill"></i> &nbsp;Unlock</button>
        </form>
    </div>
    <script>
    function togglePwd() {
        const i = document.getElementById('pwd');
        const e = document.getElementById('eyeIcon');
        i.type = i.type === 'password' ? 'text' : 'password';
        e.className = i.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
    }
    </script>
</body>
</html><?php
    exit;
}

// Read credentials from livemachine/config/database.php via reflection so this
// file stays portable — change creds there, switcher follows automatically.
require_once __DIR__ . '/livemachine/config/database.php';
$lmConsts = (new ReflectionClass('Database'))->getConstants();

$databases = [
    'hrm' => [
        'host' => $lmConsts['DB_HOST'],
        'user' => $lmConsts['DB_USER'],
        'pass' => $lmConsts['DB_PASS'],
        'name' => $lmConsts['DB_NAME'],
        'port' => $lmConsts['DB_PORT'],
    ],
    'crm' => __DIR__ . '/includes/db.php',
];

$db = $_GET['db'] ?? 'hrm';
if (!isset($databases[$db])) die('Invalid database');

if (is_array($databases[$db])) {
    $cfg = $databases[$db];
    $con = @mysqli_connect($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['name'], $cfg['port']);
} else {
    include $databases[$db];
}

if (!isset($con) || $con->connect_error) {
    die('<pre style="color:#E95420;padding:2rem;font-family:Ubuntu Mono,monospace">Connection error: '
        . htmlspecialchars($con->connect_error ?? 'unknown') . '</pre>');
}

$activeDb = $db;

$tables = [];
$result = $con->query("SHOW TABLES");
while ($row = $result->fetch_array(MYSQLI_NUM)) {
    $tables[] = $row[0];
}

$tableData    = null;
$limit        = 20;
$page         = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$queryError   = null;
$querySuccess = null;

/* ---------- Custom Query ---------- */
if (!empty($_POST['custom_query'])) {
    $query = trim($_POST['custom_query']);
    $queryType = strtoupper(strtok($query, " \t\n\r"));

    if ($queryType === 'SELECT' || $queryType === 'SHOW' || $queryType === 'DESCRIBE' || $queryType === 'EXPLAIN') {
        $result = $con->query($query);
        if ($result && $result->num_rows > 0) {
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $tableData = [
                'name'       => 'Custom Query',
                'columns'    => array_keys($data[0]),
                'rows'       => $data,
                'total'      => count($data),
                'pages'      => 1,
                'primaryKey' => null,
            ];
            $querySuccess = count($data) . ' row(s) returned.';
        } elseif ($result) {
            $querySuccess = 'Query executed — no rows returned.';
        } else {
            $queryError = $con->error;
        }
    } else {
        if ($con->query($query) !== false) {
            $affected = $con->affected_rows;
            $msg = strtoupper($queryType) . ' executed successfully.';
            if ($affected >= 0) $msg .= " $affected row(s) affected.";
            $querySuccess = $msg;
        } else {
            $queryError = $con->error;
        }
    }

/* ---------- Table Selection ---------- */
} elseif (!empty($_POST['table_name']) || !empty($_GET['table'])) {
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['table_name'] ?? $_GET['table']);

    $primaryKey = null;
    $result = $con->query("SHOW KEYS FROM `$table` WHERE Key_name = 'PRIMARY'");
    if ($result && $row = $result->fetch_assoc()) {
        $primaryKey = $row['Column_name'];
    }

    $totalRows = 0;
    $result    = $con->query("SELECT COUNT(*) AS cnt FROM `$table`");
    if ($result && $row = $result->fetch_assoc()) {
        $totalRows = (int)$row['cnt'];
    }

    $totalPages = max(1, (int)ceil($totalRows / $limit));
    $offset     = ($page - 1) * $limit;
    $data       = [];
    $columns    = [];

    $result = $con->query("SELECT * FROM `$table` LIMIT $limit OFFSET $offset");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        if (!empty($data)) {
            $columns = array_keys($data[0]);
        }
    }

    $tableData = [
        'name'       => $table,
        'columns'    => $columns,
        'rows'       => $data,
        'total'      => $totalRows,
        'pages'      => $totalPages,
        'primaryKey' => $primaryKey,
    ];
}

function cell(mixed $value): string {
    if ($value === null) {
        return '<span class="null-val">NULL</span>';
    }
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DB Inspector — <?= strtoupper($activeDb) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&family=Ubuntu+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        /* ── Ubuntu palette ─────────────────────────────────────── */
        :root {
            --ub-orange:      #E95420;
            --ub-orange-dk:   #C7461A;
            --ub-orange-lt:   #F4836B;
            --ub-aubergine:   #2C001E;
            --ub-plum:        #5E2750;
            --ub-violet:      #77216F;
            --ub-warm-grey:   #AEA79F;

            /* light theme */
            --bg:             #F0EFED;
            --surface:        #FFFFFF;
            --surface-raised: #FAFAFA;
            --text:           #111111;
            --text-muted:     #6B6B6B;
            --border:         #D9D9D9;
            --hover-row:      rgba(233,84,32,.055);
            --shadow-sm:      0 1px 3px rgba(0,0,0,.10);
            --shadow-md:      0 2px 8px rgba(0,0,0,.10), 0 1px 3px rgba(0,0,0,.08);
            --shadow-lg:      0 8px 24px rgba(0,0,0,.12), 0 2px 6px rgba(0,0,0,.08);
            --mono:           'Ubuntu Mono', 'Fira Code', 'Cascadia Code', monospace;

            --sidebar-w:      252px;
            --topbar-h:       52px;
        }

        [data-theme="dark"] {
            --bg:             #1A1A1A;
            --surface:        #252525;
            --surface-raised: #2E2E2E;
            --text:           #E5E5E5;
            --text-muted:     #8E8E8E;
            --border:         #3A3A3A;
            --hover-row:      rgba(233,84,32,.08);
            --shadow-sm:      0 1px 3px rgba(0,0,0,.4);
            --shadow-md:      0 2px 8px rgba(0,0,0,.35), 0 1px 3px rgba(0,0,0,.3);
            --shadow-lg:      0 8px 24px rgba(0,0,0,.5), 0 2px 6px rgba(0,0,0,.4);
        }

        /* ── Reset & base ───────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html, body { height: 100%; }

        body {
            font-family: 'Ubuntu', 'Segoe UI', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            display: flex;
            font-size: 14px;
            line-height: 1.5;
            transition: background .25s, color .25s;
        }

        /* ── Sidebar ────────────────────────────────────────────── */
        .sidebar {
            width: var(--sidebar-w);
            min-width: var(--sidebar-w);
            background: var(--ub-aubergine);
            display: flex;
            flex-direction: column;
            height: 100vh;
            position: sticky;
            top: 0;
            overflow: hidden;
            z-index: 100;
            flex-shrink: 0;
        }

        .sidebar-logo {
            padding: 18px 18px 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid rgba(255,255,255,.08);
        }

        .sidebar-logo .logo-icon {
            width: 34px;
            height: 34px;
            background: var(--ub-orange);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: #fff;
            flex-shrink: 0;
            box-shadow: 0 2px 8px rgba(233,84,32,.4);
        }

        .sidebar-logo .logo-text {
            line-height: 1.15;
        }

        .sidebar-logo .logo-text strong {
            display: block;
            color: #fff;
            font-size: .95rem;
            font-weight: 500;
            letter-spacing: .2px;
        }

        .sidebar-logo .logo-text span {
            font-size: .68rem;
            color: rgba(255,255,255,.45);
            font-family: var(--mono);
            letter-spacing: .5px;
        }

        /* DB switcher */
        .db-switcher {
            display: flex;
            gap: 6px;
            padding: 12px 14px;
            border-bottom: 1px solid rgba(255,255,255,.08);
        }

        .db-pill {
            flex: 1;
            text-align: center;
            padding: 5px 0;
            border-radius: 6px;
            font-size: .72rem;
            font-weight: 500;
            letter-spacing: .6px;
            text-transform: uppercase;
            text-decoration: none;
            color: rgba(255,255,255,.55);
            border: 1px solid rgba(255,255,255,.12);
            transition: all .18s;
        }

        .db-pill:hover {
            color: rgba(255,255,255,.85);
            border-color: rgba(255,255,255,.25);
            background: rgba(255,255,255,.07);
        }

        .db-pill.active {
            background: var(--ub-orange);
            border-color: var(--ub-orange);
            color: #fff;
            box-shadow: 0 2px 8px rgba(233,84,32,.45);
        }

        /* Sidebar search */
        .sidebar-search {
            padding: 10px 12px;
            border-bottom: 1px solid rgba(255,255,255,.06);
        }

        .sidebar-search-wrap {
            position: relative;
        }

        .sidebar-search input {
            width: 100%;
            background: rgba(255,255,255,.07);
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 6px;
            padding: 6px 10px 6px 30px;
            font-size: .78rem;
            color: rgba(255,255,255,.8);
            font-family: 'Ubuntu', sans-serif;
            outline: none;
            transition: border .18s, background .18s;
        }

        .sidebar-search input::placeholder { color: rgba(255,255,255,.32); }

        .sidebar-search input:focus {
            background: rgba(255,255,255,.11);
            border-color: rgba(233,84,32,.6);
        }

        .sidebar-search .si {
            position: absolute;
            left: 9px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255,255,255,.35);
            font-size: .8rem;
            pointer-events: none;
        }

        /* Table section header */
        .sidebar-section-label {
            padding: 10px 14px 4px;
            font-size: .65rem;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: rgba(255,255,255,.3);
        }

        /* Table nav list */
        .table-nav {
            flex: 1;
            overflow-y: auto;
            padding: 4px 8px 12px;
        }

        .table-nav::-webkit-scrollbar { width: 4px; }
        .table-nav::-webkit-scrollbar-track { background: transparent; }
        .table-nav::-webkit-scrollbar-thumb { background: rgba(255,255,255,.15); border-radius: 4px; }

        .table-nav-btn {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 7px 10px;
            border-radius: 6px;
            border: none;
            background: transparent;
            color: rgba(255,255,255,.6);
            font-size: .8rem;
            font-family: var(--mono);
            text-align: left;
            cursor: pointer;
            transition: background .15s, color .15s;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .table-nav-btn i { font-size: .75rem; flex-shrink: 0; opacity: .7; }

        .table-nav-btn:hover {
            background: rgba(255,255,255,.08);
            color: rgba(255,255,255,.9);
        }

        .table-nav-btn.active {
            background: rgba(233,84,32,.22);
            color: var(--ub-orange-lt);
            font-weight: 700;
        }

        .table-nav-btn.active i { opacity: 1; color: var(--ub-orange); }

        /* Sidebar footer */
        .sidebar-footer {
            padding: 10px 14px;
            border-top: 1px solid rgba(255,255,255,.07);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .sidebar-footer span {
            font-size: .68rem;
            color: rgba(255,255,255,.28);
            font-family: var(--mono);
        }

        /* ── Main area ──────────────────────────────────────────── */
        .main-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            height: 100vh;
            overflow-y: auto;
        }

        /* Topbar */
        .topbar {
            height: var(--topbar-h);
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 22px;
            position: sticky;
            top: 0;
            z-index: 50;
            box-shadow: var(--shadow-sm);
            flex-shrink: 0;
        }

        .breadcrumb-trail {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: .82rem;
        }

        .breadcrumb-trail .seg-db {
            color: var(--ub-orange);
            font-weight: 500;
            font-family: var(--mono);
            font-size: .78rem;
            background: rgba(233,84,32,.1);
            padding: 2px 8px;
            border-radius: 4px;
        }

        .breadcrumb-trail .bi-chevron-right { color: var(--border); font-size: .7rem; }

        .breadcrumb-trail .seg-table {
            color: var(--text);
            font-weight: 500;
            font-family: var(--mono);
            font-size: .8rem;
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-icon {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--surface-raised);
            color: var(--text-muted);
            cursor: pointer;
            font-size: .9rem;
            transition: all .15s;
        }

        .btn-icon:hover {
            background: var(--ub-orange);
            border-color: var(--ub-orange);
            color: #fff;
        }

        /* ── Content ────────────────────────────────────────────── */
        .content { padding: 20px 22px 36px; }

        /* ── Alerts ─────────────────────────────────────────────── */
        .ub-alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 11px 16px;
            border-radius: 6px;
            font-size: .82rem;
            margin-bottom: 18px;
            border-left: 3px solid;
        }

        .ub-alert.error {
            background: rgba(220,53,69,.1);
            border-color: #dc3545;
            color: #dc3545;
        }

        .ub-alert.success {
            background: rgba(233,84,32,.08);
            border-color: var(--ub-orange);
            color: var(--ub-orange);
        }

        [data-theme="dark"] .ub-alert.error   { background: rgba(220,53,69,.15); }
        [data-theme="dark"] .ub-alert.success  { background: rgba(233,84,32,.12); }

        /* ── Stats ──────────────────────────────────────────────── */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 20px;
        }

        .stat-tile {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 14px 16px;
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
        }

        .stat-tile::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--ub-orange), var(--ub-orange-lt));
        }

        .stat-tile .s-label {
            font-size: .65rem;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 6px;
        }

        .stat-tile .s-value {
            font-family: var(--mono);
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text);
            line-height: 1;
        }

        .stat-tile .s-icon {
            position: absolute;
            right: 14px;
            bottom: 10px;
            font-size: 1.6rem;
            color: var(--ub-orange);
            opacity: .12;
        }

        /* ── Panel cards ─────────────────────────────────────────── */
        .panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            box-shadow: var(--shadow-md);
            margin-bottom: 20px;
        }

        .panel-header {
            padding: 11px 16px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .panel-title {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: .82rem;
            font-weight: 500;
            color: var(--text);
        }

        .panel-title i { color: var(--ub-orange); font-size: .88rem; }

        .panel-body { padding: 16px; }

        /* ── Top panels row ──────────────────────────────────────── */
        .panels-row {
            display: grid;
            grid-template-columns: 220px 1fr;
            gap: 16px;
            margin-bottom: 20px;
        }

        /* ── SQL terminal ────────────────────────────────────────── */
        .sql-terminal {
            background: #1A1A1A;
            border-radius: 0 0 8px 8px;
            padding: 14px 16px 12px;
        }

        .terminal-bar {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 10px;
        }

        .terminal-dot {
            width: 10px; height: 10px;
            border-radius: 50%;
        }

        .terminal-dot.red    { background: #FF5F57; }
        .terminal-dot.yellow { background: #FFBD2E; }
        .terminal-dot.green  { background: #28CA41; }

        .terminal-label {
            font-family: var(--mono);
            font-size: .68rem;
            color: rgba(255,255,255,.25);
            margin-left: 4px;
            letter-spacing: .5px;
        }

        .sql-editor {
            width: 100%;
            background: transparent;
            border: none;
            outline: none;
            color: #E6DB74;
            font-family: var(--mono);
            font-size: .82rem;
            line-height: 1.65;
            resize: vertical;
            min-height: 76px;
            caret-color: var(--ub-orange);
        }

        .sql-editor::placeholder { color: rgba(255,255,255,.2); }

        .terminal-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid rgba(255,255,255,.07);
        }

        .terminal-hint {
            font-family: var(--mono);
            font-size: .68rem;
            color: rgba(255,255,255,.28);
        }

        .terminal-hint kbd {
            background: rgba(255,255,255,.12);
            border-radius: 3px;
            padding: 1px 5px;
            font-size: .65rem;
        }

        /* ── SQL Autocomplete ── */
        .sql-autocomplete {
            position: absolute;
            background: #1E1E1E;
            border: 1px solid rgba(233,84,32,.5);
            border-radius: 6px;
            box-shadow: 0 8px 24px rgba(0,0,0,.6);
            font-family: 'Ubuntu Mono', monospace;
            font-size: .78rem;
            z-index: 9999;
            max-height: 180px;
            overflow-y: auto;
            min-width: 160px;
            display: none;
        }
        .sql-autocomplete::-webkit-scrollbar { width: 4px; }
        .sql-autocomplete::-webkit-scrollbar-thumb { background: rgba(233,84,32,.4); border-radius: 4px; }
        .ac-item {
            padding: 5px 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #E6DB74;
            border-bottom: 1px solid rgba(255,255,255,.04);
        }
        .ac-item:last-child { border-bottom: none; }
        .ac-item:hover, .ac-item.ac-active {
            background: rgba(233,84,32,.2);
            color: #fff;
        }
        .ac-item .ac-badge {
            font-size: .6rem;
            padding: 1px 5px;
            border-radius: 3px;
            font-weight: 700;
            letter-spacing: .4px;
            flex-shrink: 0;
        }
        .ac-badge.kw  { background: rgba(102,217,239,.15); color: #66D9EF; }
        .ac-badge.tbl { background: rgba(233,84,32,.2);    color: #E95420; }
        .ac-badge.col { background: rgba(166,226,46,.15);  color: #A6E22E; }
        .sql-editor-wrap { position: relative; }

        .btn-run {
            background: var(--ub-orange);
            color: #fff;
            border: none;
            border-radius: 5px;
            padding: 5px 14px;
            font-size: .78rem;
            font-family: 'Ubuntu', sans-serif;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: background .15s;
        }

        .btn-run:hover { background: var(--ub-orange-dk); }

        .panel-header-sql {
            background: #111111;
            border-radius: 8px 8px 0 0;
            border-bottom: 1px solid rgba(255,255,255,.08);
            padding: 10px 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .panel-header-sql .panel-title {
            color: rgba(255,255,255,.7);
            font-size: .78rem;
        }

        .panel-header-sql .panel-title i { color: var(--ub-orange); }

        /* Select panel adjustments */
        .select-panel .panel-body { padding: 14px; }

        .ub-select {
            width: 100%;
            background: var(--surface-raised);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 7px 10px;
            font-size: .8rem;
            color: var(--text);
            font-family: 'Ubuntu', sans-serif;
            outline: none;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 16 16'%3E%3Cpath fill='%23888' d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            cursor: pointer;
            transition: border .15s;
            margin-bottom: 10px;
        }

        .ub-select:focus { border-color: var(--ub-orange); }

        .btn-inspect {
            width: 100%;
            background: var(--ub-orange);
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 8px 0;
            font-size: .8rem;
            font-family: 'Ubuntu', sans-serif;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: background .15s;
        }

        .btn-inspect:hover { background: var(--ub-orange-dk); }

        /* ── Data table ──────────────────────────────────────────── */
        .data-panel .panel-header {
            background: var(--surface-raised);
            border-radius: 8px 8px 0 0;
        }

        .table-meta {
            font-size: .72rem;
            color: var(--text-muted);
            font-family: var(--mono);
        }

        .table-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .search-field {
            position: relative;
        }

        .search-field input {
            background: var(--surface-raised);
            border: 1px solid var(--border);
            border-radius: 5px;
            padding: 5px 10px 5px 28px;
            font-size: .76rem;
            color: var(--text);
            font-family: 'Ubuntu', sans-serif;
            outline: none;
            width: 180px;
            transition: border .15s, width .2s;
        }

        .search-field input:focus {
            border-color: var(--ub-orange);
            width: 220px;
        }

        .search-field i {
            position: absolute;
            left: 9px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: .75rem;
            pointer-events: none;
        }

        .btn-sm-icon {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 5px 11px;
            border-radius: 5px;
            border: 1px solid var(--border);
            background: var(--surface-raised);
            color: var(--text-muted);
            font-size: .75rem;
            cursor: pointer;
            transition: all .15s;
            font-family: 'Ubuntu', sans-serif;
        }

        .btn-sm-icon:hover {
            border-color: var(--ub-orange);
            color: var(--ub-orange);
            background: rgba(233,84,32,.06);
        }

        /* Table scroll wrapper */
        .table-scroll { overflow-x: auto; }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .78rem;
        }

        .data-table thead th {
            background: var(--ub-aubergine);
            color: rgba(255,255,255,.85);
            padding: 9px 13px;
            font-family: 'Ubuntu', sans-serif;
            font-size: .7rem;
            font-weight: 500;
            letter-spacing: .5px;
            text-transform: uppercase;
            white-space: nowrap;
            border: none;
            position: sticky;
            top: 0;
            z-index: 5;
        }

        .data-table thead th.pk-th {
            background: var(--ub-plum);
            color: #fff;
        }

        .data-table thead th .pk-badge {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            font-size: .62rem;
            background: rgba(233,84,32,.7);
            color: #fff;
            border-radius: 3px;
            padding: 0 4px;
            margin-left: 5px;
            vertical-align: middle;
            font-weight: 700;
        }

        .data-table tbody tr { border-bottom: 1px solid var(--border); transition: background .1s; }
        .data-table tbody tr:last-child { border-bottom: none; }
        .data-table tbody tr:hover { background: var(--hover-row); }

        .data-table td {
            padding: 8px 13px;
            font-family: var(--mono);
            font-size: .78rem;
            color: var(--text);
            white-space: nowrap;
            max-width: 280px;
            overflow: hidden;
            text-overflow: ellipsis;
            border-right: 1px solid var(--border);
        }

        .data-table td:last-child { border-right: none; }

        .data-table td.pk-td {
            background: rgba(94,39,80,.06);
            font-weight: 700;
            color: var(--ub-plum);
        }

        [data-theme="dark"] .data-table td.pk-td {
            background: rgba(119,33,111,.12);
            color: #C084B4;
        }

        .null-val {
            color: #9B59B6;
            font-style: italic;
            font-size: .72rem;
            opacity: .8;
        }

        [data-theme="dark"] .null-val { color: #BA8DE8; }

        /* ── Pagination ──────────────────────────────────────────── */
        .pagination-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            padding: 14px 16px 6px;
        }

        .pg-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 30px;
            height: 30px;
            padding: 0 8px;
            border-radius: 5px;
            border: 1px solid var(--border);
            background: var(--surface-raised);
            color: var(--text-muted);
            font-size: .76rem;
            font-family: 'Ubuntu', sans-serif;
            text-decoration: none;
            transition: all .15s;
        }

        .pg-btn:hover {
            border-color: var(--ub-orange);
            color: var(--ub-orange);
            background: rgba(233,84,32,.06);
        }

        .pg-btn.active {
            background: var(--ub-orange);
            border-color: var(--ub-orange);
            color: #fff;
            font-weight: 600;
        }

        .pg-btn.disabled { opacity: .4; pointer-events: none; }

        /* ── Empty state ─────────────────────────────────────────── */
        .empty-state {
            text-align: center;
            padding: 56px 20px;
            color: var(--text-muted);
        }

        .empty-state .empty-icon {
            font-size: 2.8rem;
            color: var(--ub-orange);
            opacity: .3;
            margin-bottom: 14px;
        }

        .empty-state h3 {
            font-size: 1rem;
            font-weight: 500;
            margin-bottom: 6px;
            color: var(--text);
        }

        .empty-state p { font-size: .8rem; }

        /* ── Responsive ──────────────────────────────────────────── */
        @media (max-width: 860px) {
            .sidebar { display: none; }
            .stats-row { grid-template-columns: repeat(2, 1fr); }
            .panels-row { grid-template-columns: 1fr; }
            .search-field input { width: 140px; }
            .search-field input:focus { width: 160px; }
        }
    </style>
</head>
<body>

    <!-- ── Sidebar ──────────────────────────────────── -->
    <aside class="sidebar">
        <div class="sidebar-logo">
            <div class="logo-icon"><i class="bi bi-database-fill-gear"></i></div>
            <div class="logo-text">
                <strong>DB Inspector</strong>
                <span>v<?= strtoupper($activeDb) ?> · <?= count($tables) ?> tables</span>
            </div>
        </div>

        <div class="db-switcher">
            <a href="?db=hrm" class="db-pill <?= $activeDb === 'hrm' ? 'active' : '' ?>">
                <i class="bi bi-hdd-fill"></i> HRM
            </a>
            <a href="?db=crm" class="db-pill <?= $activeDb === 'crm' ? 'active' : '' ?>">
                <i class="bi bi-hdd-fill"></i> CRM
            </a>
        </div>

        <div class="sidebar-search">
            <div class="sidebar-search-wrap">
                <i class="bi bi-search si"></i>
                <input type="text" id="sidebarSearch" placeholder="Filter tables…" autocomplete="off">
            </div>
        </div>

        <div class="sidebar-section-label">Tables (<?= count($tables) ?>)</div>

        <nav class="table-nav" id="tableNav">
            <?php foreach ($tables as $tbl): ?>
                <button
                    class="table-nav-btn <?= (isset($tableData['name']) && $tableData['name'] === $tbl) ? 'active' : '' ?>"
                    onclick="pickTable('<?= htmlspecialchars($tbl, ENT_QUOTES) ?>')"
                    data-tbl="<?= htmlspecialchars(strtolower($tbl)) ?>">
                    <i class="bi bi-table"></i><?= htmlspecialchars($tbl) ?>
                </button>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-footer">
            <span><?= strtoupper($activeDb) ?> · <?= date('H:i') ?></span>
            <span>ubuntu style</span>
        </div>
    </aside>

    <!-- Hidden form for sidebar table selection -->
    <form id="pickForm" method="post" style="display:none">
        <input type="hidden" name="db" value="<?= htmlspecialchars($activeDb) ?>">
        <input type="hidden" name="table_name" id="pickName">
    </form>

    <!-- ── Main area ─────────────────────────────────── -->
    <div class="main-area">

        <!-- Topbar -->
        <header class="topbar">
            <div class="breadcrumb-trail">
                <span class="seg-db"><i class="bi bi-hdd-fill" style="margin-right:4px;opacity:.7;"></i><?= strtoupper($activeDb) ?></span>
                <?php if ($tableData): ?>
                    <i class="bi bi-chevron-right"></i>
                    <span class="seg-table"><?= htmlspecialchars($tableData['name']) ?></span>
                <?php endif; ?>
            </div>
            <div class="topbar-actions">
                <?php if ($tableData): ?>
                    <button class="btn-icon" onclick="exportCSV()" title="Export CSV">
                        <i class="bi bi-download"></i>
                    </button>
                    <button class="btn-icon" onclick="window.print()" title="Print">
                        <i class="bi bi-printer"></i>
                    </button>
                <?php endif; ?>
                <button class="btn-icon" id="themeToggle" title="Toggle theme">
                    <i class="bi bi-sun-fill" id="themeIcon"></i>
                </button>
                <form method="post" style="margin:0">
                    <button type="submit" name="_logout" value="1" class="btn-icon" title="Logout" style="color:#dc3545;border-color:#dc3545;">
                        <i class="bi bi-box-arrow-right"></i>
                    </button>
                </form>
            </div>
        </header>

        <div class="content">

            <!-- Alerts -->
            <?php if ($queryError): ?>
                <div class="ub-alert error">
                    <i class="bi bi-exclamation-octagon-fill"></i>
                    <div><strong>Query Error:</strong> <?= htmlspecialchars($queryError) ?></div>
                </div>
            <?php elseif ($querySuccess): ?>
                <div class="ub-alert success">
                    <i class="bi bi-check-circle-fill"></i>
                    <div><?= htmlspecialchars($querySuccess) ?></div>
                </div>
            <?php endif; ?>

            <!-- Stats -->
            <?php if ($tableData): ?>
                <div class="stats-row">
                    <div class="stat-tile">
                        <div class="s-label">Table</div>
                        <div class="s-value" style="font-size:1rem;word-break:break-all;"><?= htmlspecialchars($tableData['name']) ?></div>
                        <i class="bi bi-table s-icon"></i>
                    </div>
                    <div class="stat-tile">
                        <div class="s-label">Total Rows</div>
                        <div class="s-value"><?= number_format($tableData['total']) ?></div>
                        <i class="bi bi-list-ol s-icon"></i>
                    </div>
                    <div class="stat-tile">
                        <div class="s-label">Columns</div>
                        <div class="s-value"><?= count($tableData['columns']) ?></div>
                        <i class="bi bi-layout-three-columns s-icon"></i>
                    </div>
                    <div class="stat-tile">
                        <div class="s-label">Primary Key</div>
                        <div class="s-value" style="font-size:1rem;"><?= $tableData['primaryKey'] ? htmlspecialchars($tableData['primaryKey']) : '—' ?></div>
                        <i class="bi bi-key-fill s-icon"></i>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Controls row -->
            <div class="panels-row">

                <!-- Table selector -->
                <div class="panel select-panel">
                    <div class="panel-header">
                        <div class="panel-title"><i class="bi bi-collection"></i> Select Table</div>
                    </div>
                    <div class="panel-body">
                        <form method="post">
                            <input type="hidden" name="db" value="<?= htmlspecialchars($activeDb) ?>">
                            <select name="table_name" class="ub-select">
                                <option value="">— choose a table —</option>
                                <?php foreach ($tables as $tbl): ?>
                                    <option value="<?= htmlspecialchars($tbl) ?>"
                                        <?= (isset($tableData['name']) && $tableData['name'] === $tbl) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($tbl) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn-inspect">
                                <i class="bi bi-eye"></i> Inspect Table
                            </button>
                        </form>
                    </div>
                </div>

                <!-- SQL terminal -->
                <div class="panel" style="overflow:hidden;">
                    <div class="panel-header-sql">
                        <div class="panel-title"><i class="bi bi-terminal-fill"></i> SQL Console</div>
                    </div>
                    <div class="sql-terminal">
                        <div class="terminal-bar">
                            <span class="terminal-dot red"></span>
                            <span class="terminal-dot yellow"></span>
                            <span class="terminal-dot green"></span>
                            <span class="terminal-label">mysql · <?= strtoupper($activeDb) ?></span>
                        </div>
                        <form method="post" id="sqlForm">
                            <input type="hidden" name="db" value="<?= htmlspecialchars($activeDb) ?>">
                            <div class="sql-editor-wrap">
                            <textarea
                                name="custom_query"
                                id="sqlEditor"
                                class="sql-editor"
                                rows="3"
                                autocomplete="off"
                                autocorrect="off"
                                spellcheck="false"
                                placeholder="SELECT * FROM users LIMIT 20&#10;-- UPDATE users SET active=0 WHERE id=5&#10;-- DELETE FROM logs WHERE created_at < '2024-01-01'"
                            ><?= htmlspecialchars($_POST['custom_query'] ?? '') ?></textarea>
                            <div class="sql-autocomplete" id="sqlAC"></div>
                            </div>
                            <div class="terminal-footer">
                                <span class="terminal-hint">
                                    <kbd>Ctrl</kbd>+<kbd>Enter</kbd> to execute &nbsp;·&nbsp; SELECT · UPDATE · DELETE · INSERT
                                </span>
                                <button type="submit" class="btn-run">
                                    <i class="bi bi-play-fill"></i> Execute
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>

            <!-- Results table -->
            <?php if ($tableData && !empty($tableData['columns'])): ?>
                <?php
                    $rowFrom = ($page - 1) * $limit + 1;
                    $rowTo   = min($page * $limit, $tableData['total']);
                ?>
                <div class="panel data-panel">
                    <div class="panel-header">
                        <div class="panel-title">
                            <i class="bi bi-grid-3x3-gap-fill"></i>
                            <?= htmlspecialchars($tableData['name']) ?>
                            <?php if ($tableData['primaryKey']): ?>
                                <span style="font-size:.68rem;background:rgba(94,39,80,.12);color:var(--ub-plum);border-radius:4px;padding:1px 7px;font-weight:600;font-family:var(--mono);">
                                    PK: <?= htmlspecialchars($tableData['primaryKey']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="table-actions">
                            <span class="table-meta">
                                rows <?= number_format($rowFrom) ?>–<?= number_format($rowTo) ?> / <?= number_format($tableData['total']) ?>
                            </span>
                            <div class="search-field">
                                <i class="bi bi-search"></i>
                                <input type="text" id="rowSearch" placeholder="Filter rows…" autocomplete="off">
                            </div>
                        </div>
                    </div>

                    <div class="table-scroll">
                        <table class="data-table" id="dataTable">
                            <thead>
                                <tr>
                                    <?php foreach ($tableData['columns'] as $col): ?>
                                        <th class="<?= $tableData['primaryKey'] === $col ? 'pk-th' : '' ?>">
                                            <?= htmlspecialchars($col) ?>
                                            <?php if ($tableData['primaryKey'] === $col): ?>
                                                <span class="pk-badge"><i class="bi bi-key-fill"></i> PK</span>
                                            <?php endif; ?>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tableData['rows'] as $row): ?>
                                    <tr>
                                        <?php foreach ($row as $key => $value): ?>
                                            <td class="<?= $tableData['primaryKey'] === $key ? 'pk-td' : '' ?>"
                                                title="<?= htmlspecialchars((string)($value ?? ''), ENT_QUOTES) ?>">
                                                <?= cell($value) ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($tableData['pages'] > 1): ?>
                        <div class="pagination-wrap">
                            <?php if ($page > 1): ?>
                                <a class="pg-btn" href="?table=<?= urlencode($tableData['name']) ?>&page=<?= $page - 1 ?>&db=<?= urlencode($activeDb) ?>">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            <?php endif; ?>

                            <?php
                            $s = max(1, $page - 2);
                            $e = min($tableData['pages'], $page + 2);

                            if ($s > 1) {
                                echo '<a class="pg-btn" href="?table=' . urlencode($tableData['name']) . '&page=1&db=' . urlencode($activeDb) . '">1</a>';
                                if ($s > 2) echo '<span class="pg-btn disabled">&hellip;</span>';
                            }

                            for ($i = $s; $i <= $e; $i++): ?>
                                <a class="pg-btn <?= $i === $page ? 'active' : '' ?>"
                                   href="?table=<?= urlencode($tableData['name']) ?>&page=<?= $i ?>&db=<?= urlencode($activeDb) ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>

                            <?php
                            if ($e < $tableData['pages']) {
                                if ($e < $tableData['pages'] - 1) echo '<span class="pg-btn disabled">&hellip;</span>';
                                echo '<a class="pg-btn" href="?table=' . urlencode($tableData['name']) . '&page=' . $tableData['pages'] . '&db=' . urlencode($activeDb) . '">' . $tableData['pages'] . '</a>';
                            }
                            ?>

                            <?php if ($page < $tableData['pages']): ?>
                                <a class="pg-btn" href="?table=<?= urlencode($tableData['name']) ?>&page=<?= $page + 1 ?>&db=<?= urlencode($activeDb) ?>">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif (!$tableData): ?>
                <div class="panel">
                    <div class="empty-state">
                        <div class="empty-icon"><i class="bi bi-database"></i></div>
                        <h3>Select a table to begin</h3>
                        <p>Choose a table from the sidebar or dropdown, or run a custom SELECT query above.</p>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <script>
    (() => {
        /* ── Theme ── */
        const html  = document.documentElement;
        const icon  = document.getElementById('themeIcon');
        const saved = localStorage.getItem('ub-theme') || 'light';
        html.setAttribute('data-theme', saved);
        if (saved === 'dark') icon.className = 'bi bi-moon-fill';

        document.getElementById('themeToggle').addEventListener('click', () => {
            const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            localStorage.setItem('ub-theme', next);
            icon.className = next === 'dark' ? 'bi bi-moon-fill' : 'bi bi-sun-fill';
        });

        /* ── Sidebar table filter ── */
        document.getElementById('sidebarSearch').addEventListener('input', function () {
            const q = this.value.toLowerCase();
            document.querySelectorAll('#tableNav .table-nav-btn').forEach(btn => {
                btn.style.display = btn.dataset.tbl.includes(q) ? '' : 'none';
            });
        });

        /* ── Ctrl+Enter to submit SQL ── */
        document.getElementById('sqlEditor')?.addEventListener('keydown', e => {
            if (e.ctrlKey && e.key === 'Enter') document.getElementById('sqlForm').submit();
        });

        /* ── Row filter ── */
        document.getElementById('rowSearch')?.addEventListener('input', function () {
            const q = this.value.toLowerCase();
            document.querySelectorAll('#dataTable tbody tr').forEach(tr => {
                tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    })();

    /* ── Sidebar table pick ── */
    function pickTable(name) {
        document.getElementById('pickName').value = name;
        document.getElementById('pickForm').submit();
    }

    /* ── CSV export ── */
    function exportCSV() {
        const table = document.getElementById('dataTable');
        if (!table) return;
        const rows = [...table.querySelectorAll('tr')].map(r =>
            [...r.querySelectorAll('th,td')].map(c => {
                const v = c.textContent.trim();
                return (v.includes(',') || v.includes('"') || v.includes('\n'))
                    ? `"${v.replace(/"/g, '""')}"` : v;
            }).join(',')
        );
        const a = Object.assign(document.createElement('a'), {
            href:     URL.createObjectURL(new Blob([rows.join('\n')], { type: 'text/csv' })),
            download: `<?= isset($tableData['name']) ? addslashes(htmlspecialchars($tableData['name'])) : 'export' ?>_${new Date().toISOString().slice(0,10)}.csv`,
        });
        document.body.appendChild(a); a.click(); a.remove();
    }

    /* ── SQL Autocomplete ── */
    (() => {
        const SQL_KEYWORDS = [
            'SELECT','FROM','WHERE','INSERT','INTO','VALUES','UPDATE','SET','DELETE',
            'CREATE','DROP','ALTER','TABLE','DATABASE','INDEX','JOIN','INNER','LEFT',
            'RIGHT','OUTER','ON','AS','AND','OR','NOT','NULL','IS','IN','LIKE','BETWEEN',
            'ORDER','BY','GROUP','HAVING','LIMIT','OFFSET','DISTINCT','COUNT','SUM','AVG',
            'MIN','MAX','UNION','ALL','EXISTS','CASE','WHEN','THEN','ELSE','END',
            'PRIMARY','KEY','FOREIGN','REFERENCES','DEFAULT','AUTO_INCREMENT','UNIQUE',
            'CONSTRAINT','SHOW','TABLES','DESCRIBE','EXPLAIN','TRUNCATE','REPLACE',
        ];

        const TABLE_NAMES = <?= json_encode($tables) ?>;

        const COLUMN_NAMES = <?= json_encode(
            isset($tableData['columns']) ? $tableData['columns'] : []
        ) ?>;

        const editor = document.getElementById('sqlEditor');
        const ac     = document.getElementById('sqlAC');
        if (!editor || !ac) return;

        let activeIdx = -1;
        let items     = [];

        function getWordBefore(ta) {
            const val   = ta.value;
            const pos   = ta.selectionStart;
            const chunk = val.slice(0, pos);
            const match = chunk.match(/[\w]+$/);
            return match ? match[0] : '';
        }

        function replaceWord(ta, word, replacement) {
            const val   = ta.value;
            const pos   = ta.selectionStart;
            const start = pos - word.length;
            ta.value    = val.slice(0, start) + replacement + val.slice(pos);
            const newPos = start + replacement.length;
            ta.selectionStart = ta.selectionEnd = newPos;
        }

        function buildList(word) {
            const wl = word.toLowerCase();
            const kw  = SQL_KEYWORDS.filter(k => k.toLowerCase().startsWith(wl) && k.toLowerCase() !== wl);
            const tbl = TABLE_NAMES.filter(t => t.toLowerCase().startsWith(wl) && t.toLowerCase() !== wl);
            const col = COLUMN_NAMES.filter(c => c.toLowerCase().startsWith(wl) && c.toLowerCase() !== wl);
            return [
                ...kw.map(k  => ({ label: k,  type: 'kw'  })),
                ...tbl.map(t => ({ label: t,  type: 'tbl' })),
                ...col.map(c => ({ label: c,  type: 'col' })),
            ].slice(0, 12);
        }

        function renderAC(word) {
            items = buildList(word);
            activeIdx = -1;

            if (!items.length || !word) { ac.style.display = 'none'; return; }

            ac.innerHTML = items.map((it, i) =>
                `<div class="ac-item" data-i="${i}">
                    <span class="ac-badge ${it.type}">${it.type === 'kw' ? 'SQL' : it.type === 'tbl' ? 'TBL' : 'COL'}</span>
                    ${it.label}
                </div>`
            ).join('');

            ac.style.display = 'block';
        }

        function applyItem(idx) {
            const word = getWordBefore(editor);
            replaceWord(editor, word, items[idx].label + ' ');
            ac.style.display = 'none';
            editor.focus();
        }

        editor.addEventListener('input', () => {
            renderAC(getWordBefore(editor));
        });

        editor.addEventListener('keydown', e => {
            if (ac.style.display === 'none') return;
            const els = ac.querySelectorAll('.ac-item');

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                els[activeIdx]?.classList.remove('ac-active');
                activeIdx = (activeIdx + 1) % items.length;
                els[activeIdx]?.classList.add('ac-active');
                els[activeIdx]?.scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                els[activeIdx]?.classList.remove('ac-active');
                activeIdx = (activeIdx - 1 + items.length) % items.length;
                els[activeIdx]?.classList.add('ac-active');
                els[activeIdx]?.scrollIntoView({ block: 'nearest' });
            } else if ((e.key === 'Tab' || e.key === 'Enter') && activeIdx >= 0) {
                e.preventDefault();
                applyItem(activeIdx);
            } else if (e.key === 'Escape') {
                ac.style.display = 'none';
            }
        });

        ac.addEventListener('mousedown', e => {
            const item = e.target.closest('.ac-item');
            if (item) { e.preventDefault(); applyItem(+item.dataset.i); }
        });

        document.addEventListener('click', e => {
            if (!editor.contains(e.target) && !ac.contains(e.target)) {
                ac.style.display = 'none';
            }
        });
    })();

    /* ── Inactivity auto-logout (15 min) ── */
    (() => {
        const TIMEOUT = 15 * 60 * 1000;
        const WARN_BEFORE = 60 * 1000;
        let warnTimer, logoutTimer;

        const toast = document.createElement('div');
        toast.style.cssText = 'display:none;position:fixed;bottom:24px;right:24px;background:#E95420;color:#fff;padding:12px 18px;border-radius:8px;font-size:.82rem;font-family:Ubuntu,sans-serif;box-shadow:0 4px 16px rgba(0,0,0,.35);z-index:9999;gap:10px;align-items:center;';
        toast.innerHTML = '<i class="bi bi-clock-history"></i> <span>Session expiring in <b id="cdSec">60</b>s — move mouse to stay.</span>';
        document.body.appendChild(toast);

        let cdInterval;
        function showWarn() {
            let secs = 60;
            toast.style.display = 'flex';
            document.getElementById('cdSec').textContent = secs;
            cdInterval = setInterval(() => {
                secs--;
                const el = document.getElementById('cdSec');
                if (el) el.textContent = secs;
                if (secs <= 0) clearInterval(cdInterval);
            }, 1000);
        }

        function reset() {
            clearTimeout(warnTimer);
            clearTimeout(logoutTimer);
            clearInterval(cdInterval);
            toast.style.display = 'none';
            warnTimer  = setTimeout(showWarn, TIMEOUT - WARN_BEFORE);
            logoutTimer = setTimeout(() => {
                document.querySelector('form [name="_logout"]')?.closest('form').submit();
            }, TIMEOUT);
        }

        ['mousemove','keydown','click','scroll','touchstart'].forEach(e =>
            document.addEventListener(e, reset, { passive: true })
        );

        reset();
    })();
    </script>
</body>
</html>
