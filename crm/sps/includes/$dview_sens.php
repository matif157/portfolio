<?php
/* ---------- 15-Minute Password Protection ---------- */
define('ACCESS_PASSWORD', 'skipper@');   // CHANGE THIS
define('SESSION_KEY', 'skipper_auth');
define('SESSION_LIFETIME', 15 * 60);       // 15 minutes in seconds

session_start();

/* ---- check existing session ---- */
$now = time();
if (
    isset($_SESSION[SESSION_KEY], $_SESSION['skipper_expires']) &&
    $_SESSION[SESSION_KEY] === true &&
    $_SESSION['skipper_expires'] >= $now
) {
    /* session still valid – bump the clock */
    $_SESSION['skipper_expires'] = $now + SESSION_LIFETIME;
} else {
    /* ---- expired or never logged – destroy old residue ---- */
    unset($_SESSION[SESSION_KEY], $_SESSION['skipper_expires']);

    /* ---- receiving login attempt ---- */
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        isset($_POST['access_password']) &&
        $_POST['access_password'] === ACCESS_PASSWORD
    ) {
        $_SESSION[SESSION_KEY] = true;
        $_SESSION['skipper_expires'] = $now + SESSION_LIFETIME;
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    /* ---- show login form and STOP ---- */
    showLoginForm();
    exit;
}

/* ---------- Logout (instant) ---------- */
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

/* ---------- login page helper ---------- */
function showLoginForm()
{
    global $auth_error;
    ?><!doctype html>
    <html lang="en">

    <head>
        <meta charset="utf-8">
        <title>Skipper DB Explorer – Login</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            :root {
                --bg: #151822;
                --card: #222633;
                --text: #fff;
                --accent: #667eea;
            }

            body {
                background: var(--bg);
                color: var(--text);
                display: flex;
                align-items: center;
                justify-content: center;
                height: 100vh;
            }

            .login-box {
                background: var(--card);
                border: 1px solid #2a2f42;
                border-radius: 16px;
                padding: 40px;
                max-width: 380px;
                width: 100%;
            }

            .login-box h3 {
                background: linear-gradient(135deg, var(--accent), #764ba2);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
            }

            .form-control {
                background: rgba(255, 255, 255, .05);
                border: 1px solid #2a2f42;
                color: var(--text);
                border-radius: 10px;
            }

            .form-control:focus {
                background: rgba(255, 255, 255, .05);
                border-color: var(--accent);
                color: var(--text);
            }

            .btn-primary {
                background: linear-gradient(135deg, var(--accent), #764ba2);
                border: none;
                border-radius: 10px;
                font-weight: 600;
            }
        </style>
    </head>

    <body>
        <div class="login-box">
            <h3 class="mb-3 text-center"><i class="fas fa-lock me-2"></i>Password Required</h3>
            <?php if (!empty($auth_error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($auth_error) ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Enter password</label>
                    <input type="password" name="access_password" class="form-control" required autofocus>
                </div>
                <button class="btn btn-primary w-100">Access Dashboard</button>
            </form>
            <div class="text-center mt-3 small text-muted">Session lasts 15 minutes</div>
        </div>
    </body>

    </html>
    <?php
}
?>

<?php
include "db.php";

$tables = [];
$result = $con->query("SHOW TABLES");
while ($row = $result->fetch_array(MYSQLI_NUM)) {
    $tables[] = $row[0];
}

$tableData = null;
$limit = 20;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$isCustomQuery = false;
$executionTime = 0;

/* ---------- Custom Query Handling ---------- */
if (!empty($_POST['custom_query'])) {
    $query = trim($_POST['custom_query']);
    $startTime = microtime(true);
    $result = $con->query($query);
    $endTime = microtime(true);
    $executionTime = round(($endTime - $startTime) * 1000, 2); // in ms
    $isCustomQuery = true;

    if ($result === false) {
        echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($con->error) . "</div>";
    } else {
        if ($result instanceof mysqli_result) {
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            if (!empty($data)) {
                $columns = array_keys($data[0]);
                $tableData = [
                    'name' => 'Custom Query Results',
                    'columns' => $columns,
                    'rows' => $data,
                    'total' => count($data),
                    'pages' => 1,
                    'primaryKey' => null
                ];

                $tableData['column_types'] = analyzeColumnsForGraphs($data);
                $tableData['suggested_graphs'] = suggestGraphs($data, $columns);
                $tableData['summary_stats'] = calculateSummaryStats($data, $columns);
            } else {
                echo "<div class='alert alert-success'>Query executed successfully. No data returned.</div>";
            }
        } else {
            $affected = $con->affected_rows;
            echo "<div class='alert alert-success'>Query executed successfully. Affected rows: $affected.</div>";
        }
    }
}

/* ---------- Table Selection Handling ---------- */ elseif (!empty($_POST['table_name']) || !empty($_GET['table'])) {
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['table_name'] ?? $_GET['table']);

    $primaryKey = null;
    $result = $con->query("SHOW KEYS FROM `$table` WHERE Key_name = 'PRIMARY'");
    if ($result && $row = $result->fetch_assoc()) {
        $primaryKey = $row['Column_name'];
    }

    $totalRows = 0;
    $result = $con->query("SELECT COUNT(*) AS cnt FROM `$table`");
    if ($result && $row = $result->fetch_assoc()) {
        $totalRows = (int) $row['cnt'];
    }
    $totalPages = ceil($totalRows / $limit);

    $offset = ($page - 1) * $limit;
    $data = [];
    $columns = [];

    $query = "SELECT * FROM `$table` LIMIT $limit OFFSET $offset";
    $result = $con->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        if (!empty($data)) {
            $columns = array_keys($data[0]);
        }
    }

    $tableData = [
        'name' => $table,
        'columns' => $columns,
        'rows' => $data,
        'total' => $totalRows,
        'pages' => $totalPages,
        'primaryKey' => $primaryKey
    ];
}

/* ---------- Helper Functions ---------- */
function analyzeColumnsForGraphs($data)
{
    if (empty($data))
        return [];

    $columnTypes = [];
    $sampleSize = min(50, count($data));

    foreach ($data[0] as $column => $value) {
        $numericCount = 0;
        $dateCount = 0;
        $sampleValues = [];

        for ($i = 0; $i < $sampleSize; $i++) {
            $val = $data[$i][$column] ?? '';
            $sampleValues[] = $val;

            if (is_numeric($val) || preg_match('/^-?\d+\.?\d*$/', $val)) {
                $numericCount++;
            }
            if (
                preg_match('/^\d{4}-\d{2}-\d{2}/', $val) ||
                preg_match('/^\d{2}:\d{2}:\d{2}/', $val) ||
                strtotime($val) !== false
            ) {
                $dateCount++;
            }
        }

        if ($dateCount > $sampleSize * 0.7) {
            $type = 'datetime';
        } elseif ($numericCount > $sampleSize * 0.7) {
            $type = 'numeric';
        } else {
            $uniqueCount = count(array_unique($sampleValues));
            $type = ($uniqueCount <= 15 && $uniqueCount > 1) ? 'categorical' : 'text';
        }

        $columnTypes[$column] = $type;
    }

    return $columnTypes;
}

function suggestGraphs($data, $columns)
{
    if (empty($data) || empty($columns))
        return [];

    $suggestions = [];
    $columnTypes = analyzeColumnsForGraphs($data);

    $datetimeColumns = array_keys(array_filter($columnTypes, fn($t) => $t === 'datetime'));
    $numericColumns = array_keys(array_filter($columnTypes, fn($t) => $t === 'numeric'));
    $categoricalColumns = array_keys(array_filter($columnTypes, fn($t) => $t === 'categorical'));

    if (!empty($datetimeColumns) && !empty($numericColumns)) {
        $suggestions[] = [
            'type' => 'line',
            'icon' => '📈',
            'title' => "Trend Analysis",
            'desc' => "Track changes over time",
            'x_axis' => $datetimeColumns[0],
            'y_axis' => $numericColumns[0]
        ];
    }

    if (!empty($categoricalColumns) && !empty($numericColumns)) {
        $suggestions[] = [
            'type' => 'bar',
            'icon' => '📊',
            'title' => "Category Comparison",
            'desc' => "Compare values across categories",
            'x_axis' => $categoricalColumns[0],
            'y_axis' => $numericColumns[0]
        ];
    }

    if (!empty($categoricalColumns) && count(array_unique(array_column($data, $categoricalColumns[0]))) <= 10) {
        $suggestions[] = [
            'type' => 'pie',
            'icon' => '🥧',
            'title' => "Distribution",
            'desc' => "View percentage breakdown",
            'category' => $categoricalColumns[0]
        ];
    }

    if (count($numericColumns) >= 2) {
        $suggestions[] = [
            'type' => 'scatter',
            'icon' => '🔵',
            'title' => "Correlation",
            'desc' => "Find relationships between variables",
            'x_axis' => $numericColumns[0],
            'y_axis' => $numericColumns[1]
        ];
    }

    return $suggestions;
}

function calculateSummaryStats($data, $columns)
{
    $stats = [];
    $columnTypes = analyzeColumnsForGraphs($data);

    foreach ($columns as $col) {
        $type = $columnTypes[$col] ?? 'unknown';
        $values = array_column($data, $col);

        $stat = ['type' => $type];

        if ($type === 'numeric') {
            $numericValues = array_filter($values, 'is_numeric');
            if (!empty($numericValues)) {
                $stat['min'] = min($numericValues);
                $stat['max'] = max($numericValues);
                $stat['avg'] = array_sum($numericValues) / count($numericValues);
            }
        } elseif ($type === 'categorical') {
            $stat['unique'] = count(array_unique($values));
        }

        $stats[$col] = $stat;
    }

    return $stats;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Skipper DB Explorer - Database Visualization Tool</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/luxon@3.3.0"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-luxon@1.3.0"></script>
    <style>
        /* CSS Variables for Dark Theme */
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --warning-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --dark-bg: #151822;
            --darker-bg: #0f1118;
            --card-bg: #222633;
            --card-border: #2a2f42;
            --text-primary: #ffffff;
            --text-secondary: #a0aec0;
            --text-muted: #718096;
            --border-color: #2d3748;
            --hover-color: rgba(255, 255, 255, 0.05);
            --shadow-lg: 0 10px 30px rgba(0, 0, 0, 0.3);
            --shadow-md: 0 4px 20px rgba(0, 0, 0, 0.2);
            --accent-blue: #4299e1;
            --accent-green: #48bb78;
            --accent-purple: #9f7aea;
            --accent-pink: #ed64a6;
            --transition-speed: 0.3s;
        }

        /* CSS Variables for Light Theme */
        [data-theme="light"] {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --warning-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --dark-bg: #f8f9fa;
            --darker-bg: #ffffff;
            --card-bg: #ffffff;
            --card-border: #e2e8f0;
            --text-primary: #1a202c;
            --text-secondary: #4a5568;
            --text-muted: #718096;
            --border-color: #e2e8f0;
            --hover-color: rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 10px 30px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 20px rgba(0, 0, 0, 0.08);
            --accent-blue: #3182ce;
            --accent-green: #38a169;
            --accent-purple: #805ad5;
            --accent-pink: #d53f8c;
            --transition-speed: 0.3s;
        }

        /* Custom Theme Colors */
        [data-theme-color="blue"] {
            --primary-gradient: linear-gradient(135deg, #3182ce 0%, #2c5282 100%);
            --accent-blue: #3182ce;
        }

        [data-theme-color="green"] {
            --primary-gradient: linear-gradient(135deg, #38a169 0%, #2f855a 100%);
            --accent-green: #38a169;
        }

        [data-theme-color="purple"] {
            --primary-gradient: linear-gradient(135deg, #805ad5 0%, #6b46c1 100%);
            --accent-purple: #805ad5;
        }

        [data-theme-color="pink"] {
            --primary-gradient: linear-gradient(135deg, #d53f8c 0%, #b83280 100%);
            --accent-pink: #d53f8c;
        }

        [data-theme-color="teal"] {
            --primary-gradient: linear-gradient(135deg, #319795 0%, #2c7a7b 100%);
            --accent-teal: #319795;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--dark-bg);
            color: var(--text-primary);
            min-height: 100vh;
            overflow-x: hidden;
            transition: background-color var(--transition-speed), color var(--transition-speed);
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--darker-bg);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--card-border);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--accent-blue);
        }

        /* Layout */
        .app-container {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 20px;
            padding: 20px;
            max-width: 1600px;
            margin: 0 auto;
        }

        /* Sidebar */
        .sidebar {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 24px;
            border: 1px solid var(--card-border);
            box-shadow: var(--shadow-md);
            position: sticky;
            top: 20px;
            height: calc(100vh - 40px);
            overflow-y: auto;
            transition: background-color var(--transition-speed), border-color var(--transition-speed);
        }

        .sidebar-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .sidebar-header h3 {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .sidebar-section {
            margin-bottom: 30px;
        }

        .sidebar-title {
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Main Content */
        .main-content {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* Cards */
        .card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            box-shadow: var(--shadow-md);
            transition: transform var(--transition-speed), box-shadow var(--transition-speed), background-color var(--transition-speed);
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .card-header {
            background: transparent;
            border-bottom: 1px solid var(--card-border);
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: border-color var(--transition-speed);
        }

        .card-header h4 {
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-body {
            padding: 24px;
        }

        /* Buttons */
        .btn-primary {
            background: var(--primary-gradient);
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            transition: all var(--transition-speed);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
            color: white;
        }

        .btn-outline-light {
            border-color: var(--border-color);
            color: var(--text-secondary);
            padding: 10px 20px;
            border-radius: 10px;
            transition: all var(--transition-speed);
            background: transparent;
        }

        .btn-outline-light:hover {
            background: var(--hover-color);
            border-color: var(--accent-blue);
            color: var(--text-primary);
        }

        /* Form Controls */
        .form-select,
        .form-control {
            background: var(--darker-bg);
            border: 1px solid var(--card-border);
            color: var(--text-primary);
            border-radius: 10px;
            padding: 12px 16px;
            transition: all var(--transition-speed);
        }

        .form-select:focus,
        .form-control:focus {
            background: var(--darker-bg);
            border-color: var(--accent-blue);
            color: var(--text-primary);
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
        }

        textarea.form-control {
            min-height: 120px;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        /* Table */
        .data-table-container {
            background: var(--darker-bg);
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--card-border);
            transition: background-color var(--transition-speed), border-color var(--transition-speed);
        }

        .table {
            margin: 0;
            color: var(--text-primary);
            border-color: var(--card-border);
        }

        .table thead th {
            background: rgba(66, 153, 225, 0.1);
            border-bottom: 2px solid var(--accent-blue);
            padding: 16px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        .table tbody tr {
            transition: background-color 0.2s ease;
        }

        .table tbody tr:hover {
            background: rgba(66, 153, 225, 0.05);
        }

        .table td {
            padding: 14px 16px;
            border-color: var(--card-border);
            vertical-align: middle;
        }

        /* Graph Cards */
        .graph-card {
            height: 100%;
            min-height: 400px;
        }

        .chart-container {
            position: relative;
            height: 320px;
            width: 100%;
        }

        /* Graph Suggestions */
        .graph-suggestions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .graph-suggestion-card {
            background: rgba(66, 153, 225, 0.05);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 20px;
            cursor: pointer;
            transition: all var(--transition-speed);
            text-align: center;
        }

        .graph-suggestion-card:hover {
            background: rgba(66, 153, 225, 0.1);
            border-color: var(--accent-blue);
            transform: translateY(-3px);
        }

        .graph-suggestion-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }

        .graph-suggestion-title {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .graph-suggestion-desc {
            font-size: 0.9rem;
            color: var(--text-muted);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            border: 1px solid var(--card-border);
            transition: all var(--transition-speed);
        }

        [data-theme="light"] .stat-card {
            background: rgba(0, 0, 0, 0.02);
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Tabs */
        .nav-tabs {
            border-bottom: 1px solid var(--card-border);
            gap: 10px;
        }

        .nav-tabs .nav-link {
            background: transparent;
            border: none;
            color: var(--text-secondary);
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            transition: all var(--transition-speed);
        }

        .nav-tabs .nav-link:hover {
            color: var(--text-primary);
            background: var(--hover-color);
        }

        .nav-tabs .nav-link.active {
            background: var(--primary-gradient);
            color: white;
            border: none;
        }

        /* Loading Animation */
        .loader {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid var(--text-muted);
            border-radius: 50%;
            border-top-color: var(--accent-blue);
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Query History */
        .query-history-item {
            background: var(--darker-bg);
            border: 1px solid var(--card-border);
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all var(--transition-speed);
            font-family: monospace;
            font-size: 0.9rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .query-history-item:hover {
            border-color: var(--accent-blue);
            background: rgba(66, 153, 225, 0.05);
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }

        .quick-action-btn {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--card-border);
            border-radius: 8px;
            padding: 12px;
            text-align: center;
            cursor: pointer;
            transition: all var(--transition-speed);
            font-size: 0.9rem;
        }

        [data-theme="light"] .quick-action-btn {
            background: rgba(0, 0, 0, 0.02);
        }

        .quick-action-btn:hover {
            background: rgba(66, 153, 225, 0.1);
            border-color: var(--accent-blue);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .app-container {
                grid-template-columns: 1fr;
            }

            .sidebar {
                height: auto;
                position: static;
            }
        }

        @media (max-width: 768px) {
            .app-container {
                padding: 10px;
            }

            .card-body {
                padding: 16px;
            }

            .graph-suggestions {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* Custom Components */
        .badge-gradient {
            background: var(--primary-gradient);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .connection-status {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: rgba(72, 187, 120, 0.1);
            border-radius: 20px;
            font-size: 0.9rem;
        }

        [data-theme="light"] .connection-status {
            background: rgba(72, 187, 120, 0.1);
        }

        .status-dot {
            width: 8px;
            height: 8px;
            background: var(--accent-green);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }
        }

        .execution-time {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            background: rgba(159, 122, 234, 0.1);
            border-radius: 20px;
            font-size: 0.85rem;
        }

        /* Tooltip */
        .custom-tooltip {
            position: relative;
            display: inline-block;
        }

        .custom-tooltip .tooltip-text {
            visibility: hidden;
            background: var(--card-bg);
            color: var(--text-primary);
            text-align: center;
            padding: 8px 12px;
            border-radius: 6px;
            position: absolute;
            z-index: 1000;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            white-space: nowrap;
            font-size: 0.9rem;
            border: 1px solid var(--card-border);
            opacity: 0;
            transition: opacity 0.3s;
        }

        .custom-tooltip:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }

        /* Theme Switcher */
        .theme-switcher {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding: 12px;
            background: var(--darker-bg);
            border-radius: 10px;
            border: 1px solid var(--card-border);
        }

        .theme-switch-label {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-secondary);
        }

        .theme-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .theme-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: var(--card-border);
            transition: var(--transition-speed);
            border-radius: 24px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: var(--transition-speed);
            border-radius: 50%;
        }

        input:checked+.slider {
            background-color: var(--accent-blue);
        }

        input:checked+.slider:before {
            transform: translateX(26px);
        }

        /* Color Picker */
        .color-picker {
            display: flex;
            gap: 8px;
            margin-top: 10px;
        }

        .color-option {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            transition: transform var(--transition-speed);
            border: 2px solid transparent;
        }

        .color-option:hover {
            transform: scale(1.1);
        }

        .color-option.active {
            border-color: var(--text-primary);
        }

        .color-blue {
            background: linear-gradient(135deg, #3182ce 0%, #2c5282 100%);
        }

        .color-green {
            background: linear-gradient(135deg, #38a169 0%, #2f855a 100%);
        }

        .color-purple {
            background: linear-gradient(135deg, #805ad5 0%, #6b46c1 100%);
        }

        .color-pink {
            background: linear-gradient(135deg, #d53f8c 0%, #b83280 100%);
        }

        .color-teal {
            background: linear-gradient(135deg, #319795 0%, #2c7a7b 100%);
        }

        /* Floating Action Button */
        .fab {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: var(--primary-gradient);
            color: white;
            border: none;
            box-shadow: var(--shadow-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all var(--transition-speed);
            z-index: 1000;
        }

        .fab:hover {
            transform: scale(1.1);
        }

        /* Improved Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-out;
        }

        /* Enhanced Table Styling */
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(0, 0, 0, 0.02);
        }

        [data-theme="light"] .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(0, 0, 0, 0.02);
        }

        /* Enhanced Button Styles */
        .btn-gradient {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            transition: all var(--transition-speed);
        }

        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            color: white;
        }

        /* Enhanced Alert Styles */
        .alert {
            border-radius: 10px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 20px;
        }

        .alert-success {
            background-color: rgba(72, 187, 120, 0.1);
            color: var(--accent-green);
        }

        .alert-danger {
            background-color: rgba(245, 101, 101, 0.1);
            color: #f56565;
        }

        /* Enhanced Pagination */
        .pagination {
            margin-bottom: 0;
        }

        .page-link {
            background-color: var(--card-bg);
            border: 1px solid var(--card-border);
            color: var(--text-primary);
            padding: 8px 16px;
            margin: 0 2px;
            border-radius: 8px;
            transition: all var(--transition-speed);
        }

        .page-link:hover {
            background-color: var(--hover-color);
            border-color: var(--accent-blue);
            color: var(--text-primary);
        }

        .page-item.active .page-link {
            background-color: var(--accent-blue);
            border-color: var(--accent-blue);
        }
    </style>
</head>

<body>
    <!-- App Container -->
    <div class="app-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-database me-2"></i>Skipper DB Explorer</h3>
                <div class="connection-status mt-3">
                    <span class="status-dot"></span>
                    <span>Connected to Database</span>
                </div>
            </div>

            <!-- Theme Switcher -->
            <div class="sidebar-section">
                <div class="sidebar-title">
                    <i class="fas fa-palette"></i>
                    <span>Appearance</span>
                </div>
                <div class="theme-switcher">
                    <span class="theme-switch-label">Dark</span>
                    <label class="theme-switch">
                        <input type="checkbox" id="themeToggle">
                        <span class="slider"></span>
                    </label>
                    <span class="theme-switch-label">Light</span>
                </div>
                <div class="color-picker">
                    <div class="color-option color-blue active" data-color="blue"></div>
                    <div class="color-option color-green" data-color="green"></div>
                    <div class="color-option color-purple" data-color="purple"></div>
                    <div class="color-option color-pink" data-color="pink"></div>
                    <div class="color-option color-teal" data-color="teal"></div>
                </div>
            </div>

            <div class="sidebar-section">
                <div class="sidebar-title">
                    <i class="fas fa-table"></i>
                    <span>Database Tables</span>
                </div>
                <form method="post" action="">
                    <select name="table_name" class="form-select mb-3">
                        <option value="">Select a table...</option>
                        <?php foreach ($tables as $tbl): ?>
                            <option value="<?= htmlspecialchars($tbl ?? '') ?>" <?= (isset($tableData['name']) && $tableData['name'] === $tbl) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tbl ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-eye me-2"></i>Explore Table
                    </button>
                </form>

            </div>

            <div class="sidebar-section">
                <div class="sidebar-title">
                    <i class="fas fa-code"></i>
                    <span>SQL Query</span>
                </div>
                <form method="post" action="" id="queryForm">
                    <div class="mb-3">
                        <label class="form-label small text-muted">Write your SQL query:</label>
                        <textarea name="custom_query" class="form-control" rows="4"
                            placeholder="SELECT * FROM table WHERE condition..."><?= htmlspecialchars($_POST['custom_query'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 mb-3" id="runQueryBtn">
                        <i class="fas fa-play me-2"></i>Execute Query
                    </button>

                    <div class="quick-actions mb-3">
                        <div class="quick-action-btn" onclick="insertSampleQuery('SELECT')">
                            <i class="fas fa-filter me-2"></i>SELECT
                        </div>
                        <div class="quick-action-btn" onclick="insertSampleQuery('JOIN')">
                            <i class="fas fa-link me-2"></i>JOIN
                        </div>
                        <div class="quick-action-btn" onclick="insertSampleQuery('GROUP BY')">
                            <i class="fas fa-layer-group me-2"></i>GROUP BY
                        </div>
                        <div class="quick-action-btn" onclick="insertSampleQuery('ORDER BY')">
                            <i class="fas fa-sort-amount-down me-2"></i>ORDER BY
                        </div>
                    </div>
                </form>
            </div>

            <?php if ($tableData && !empty($tableData['suggested_graphs'])): ?>
                <div class="sidebar-section">
                    <div class="sidebar-title">
                        <i class="fas fa-lightbulb"></i>
                        <span>Quick Visualizations</span>
                    </div>
                    <div class="graph-suggestions">
                        <?php foreach ($tableData['suggested_graphs'] as $index => $graph): ?>
                            <div class="graph-suggestion-card" onclick="loadGraphSuggestion(<?= $index ?>)">
                                <div class="graph-suggestion-icon"><?= $graph['icon'] ?></div>
                                <div class="graph-suggestion-title"><?= $graph['title'] ?></div>
                                <div class="graph-suggestion-desc"><?= $graph['desc'] ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header Stats -->
            <?php if ($tableData): ?>
                <div class="stats-grid fade-in">
                    <div class="stat-card">
                        <div class="stat-value"><?= $tableData['total'] ?></div>
                        <div class="stat-label">Total Rows</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?= count($tableData['columns']) ?></div>
                        <div class="stat-label">Columns</div>
                    </div>
                    <?php if ($isCustomQuery && $executionTime > 0): ?>
                        <div class="stat-card">
                            <div class="stat-value"><?= $executionTime ?>ms</div>
                            <div class="stat-label">Query Time</div>
                        </div>
                    <?php endif; ?>
                    <?php if ($tableData['primaryKey']): ?>
                        <div class="stat-card">
                            <div class="stat-value custom-tooltip">
                                <i class="fas fa-key"></i>
                                <span class="tooltip-text">Primary Key</span>
                            </div>
                            <div class="stat-label"><?= $tableData['primaryKey'] ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Data Tabs -->
            <?php if ($tableData): ?>
                <div class="card fade-in">
                    <div class="card-header">
                        <h4><i class="fas fa-chart-bar me-2"></i>Data Analysis - <?= htmlspecialchars($tableData['name']) ?>
                        </h4>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-outline-light active" data-view="table">
                                <i class="fas fa-table me-2"></i>Table
                            </button>
                            <button type="button" class="btn btn-outline-light" data-view="graph">
                                <i class="fas fa-chart-line me-2"></i>Graph
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Table View -->
                        <div id="tableView" class="view-section">
                            <div class="data-table-container">
                                <table class="table table-hover table-striped">
                                    <thead>
                                        <tr>
                                            <?php foreach ($tableData['columns'] as $col): ?>
                                                <th class="<?= $tableData['primaryKey'] === $col ? 'primary-key' : '' ?>">
                                                    <?= htmlspecialchars($col) ?>
                                                    <?php if ($tableData['primaryKey'] === $col): ?>
                                                        <i class="fas fa-key ms-1 text-warning"></i>
                                                    <?php endif; ?>
                                                </th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tableData['rows'] as $row): ?>
                                            <tr>
                                                <?php foreach ($row as $key => $value): ?>
                                                    <td class="<?= $tableData['primaryKey'] === $key ? 'primary-key' : '' ?>">
                                                        <?php
                                                        $displayValue = htmlspecialchars(is_scalar($value) ? $value : '');

                                                        if (strlen($displayValue) > 100) {
                                                            echo substr($displayValue, 0, 100) . '...';
                                                        } else {
                                                            echo $displayValue;
                                                        }
                                                        ?>
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if ($tableData['pages'] > 1): ?>
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <div class="text-muted">
                                        Showing <?= min($limit, count($tableData['rows'])) ?> of <?= $tableData['total'] ?>
                                        records
                                    </div>
                                    <nav>
                                        <ul class="pagination mb-0">
                                            <?php for ($i = 1; $i <= min(5, $tableData['pages']); $i++): ?>
                                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                                    <a class="page-link" href="?table=<?= $tableData['name'] ?>&page=<?= $i ?>">
                                                        <?= $i ?>
                                                    </a>
                                                </li>
                                            <?php endfor; ?>
                                        </ul>
                                    </nav>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Graph View -->
                        <div id="graphView" class="view-section" style="display: none;">
                            <div class="row g-4">
                                <div class="col-md-8">
                                    <div class="card graph-card">
                                        <div class="card-header">
                                            <h5><i class="fas fa-chart-line me-2"></i>Data Visualization</h5>
                                            <div class="dropdown">
                                                <button class="btn btn-outline-light dropdown-toggle" type="button"
                                                    data-bs-toggle="dropdown">
                                                    <i class="fas fa-cog me-2"></i>Settings
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li><a class="dropdown-item" href="#"
                                                            onclick="changeGraphType('line')">Line Chart</a></li>
                                                    <li><a class="dropdown-item" href="#"
                                                            onclick="changeGraphType('bar')">Bar Chart</a></li>
                                                    <li><a class="dropdown-item" href="#"
                                                            onclick="changeGraphType('pie')">Pie Chart</a></li>
                                                    <li><a class="dropdown-item" href="#"
                                                            onclick="changeGraphType('scatter')">Scatter Plot</a></li>
                                                </ul>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div class="chart-container">
                                                <canvas id="dataChart"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5><i class="fas fa-sliders-h me-2"></i>Chart Settings</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label">X-Axis</label>
                                                <select id="xAxisSelect" class="form-select" onchange="updateChart()">
                                                    <?php foreach ($tableData['columns'] as $col): ?>
                                                        <?php $type = $tableData['column_types'][$col] ?? 'unknown'; ?>
                                                        <option value="<?= $col ?>" data-type="<?= $type ?>">
                                                            <?= htmlspecialchars($col) ?> (<?= $type ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Y-Axis</label>
                                                <select id="yAxisSelect" class="form-select" onchange="updateChart()">
                                                    <?php foreach ($tableData['columns'] as $col): ?>
                                                        <?php $type = $tableData['column_types'][$col] ?? 'unknown'; ?>
                                                        <option value="<?= $col ?>" data-type="<?= $type ?>">
                                                            <?= htmlspecialchars($col) ?> (<?= $type ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" id="showGrid" checked
                                                    onchange="updateChart()">
                                                <label class="form-check-label" for="showGrid">Show Grid</label>
                                            </div>
                                            <div class="form-check form-switch mb-3">
                                                <input class="form-check-input" type="checkbox" id="showLegend" checked
                                                    onchange="updateChart()">
                                                <label class="form-check-label" for="showLegend">Show Legend</label>
                                            </div>
                                            <button class="btn btn-outline-light w-100" onclick="updateChart()">
                                                <i class="fas fa-sync-alt me-2"></i>Refresh Chart
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php elseif (!$isCustomQuery): ?>
                <!-- Welcome Screen -->
                <div class="card fade-in">
                    <div class="card-body text-center py-5">
                        <div class="display-1 mb-4"
                            style="background: var(--primary-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        <h2 class="mb-3">Welcome to Skipper DB Explorer</h2>
                        <p class="text-muted mb-4">Run a SQL query or select a table to start visualizing your data</p>
                        <div class="row justify-content-center">
                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="mb-3">Try these sample queries:</h5>
                                        <div class="d-flex flex-wrap gap-2 justify-content-center">
                                            <button class="btn btn-outline-light"
                                                onclick="insertSampleQuery('SELECT * FROM ')">
                                                <i class="fas fa-star me-2"></i>Basic Select
                                            </button>
                                            <button class="btn btn-outline-light"
                                                onclick="insertSampleQuery('SELECT COUNT(*), ')">
                                                <i class="fas fa-calculator me-2"></i>Aggregation
                                            </button>
                                            <button class="btn btn-outline-light"
                                                onclick="insertSampleQuery('SELECT DATE(')">
                                                <i class="fas fa-calendar me-2"></i>Time Series
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Column Analysis -->
            <?php if ($tableData && !empty($tableData['column_types'])): ?>
                <div class="card fade-in">
                    <div class="card-header">
                        <h4><i class="fas fa-search me-2"></i>Column Analysis</h4>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <?php foreach ($tableData['column_types'] as $col => $type): ?>
                                <div class="col-md-3">
                                    <div class="card h-100" style="background: rgba(255,255,255,0.02);">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="mb-0"><?= htmlspecialchars($col) ?></h6>
                                                <span class="badge bg-<?=
                                                    $type === 'numeric' ? 'info' :
                                                    ($type === 'datetime' ? 'warning' :
                                                        ($type === 'categorical' ? 'success' : 'secondary'))
                                                    ?>">
                                                    <?= $type ?>
                                                </span>
                                            </div>
                                            <?php if (isset($tableData['summary_stats'][$col])): ?>
                                                <?php $stats = $tableData['summary_stats'][$col]; ?>
                                                <div class="small text-muted mt-2">
                                                    <?php if ($type === 'numeric'): ?>
                                                        <div>Min: <?= round($stats['min'] ?? 0, 2) ?></div>
                                                        <div>Max: <?= round($stats['max'] ?? 0, 2) ?></div>
                                                        <div>Avg: <?= round($stats['avg'] ?? 0, 2) ?></div>
                                                    <?php elseif ($type === 'categorical'): ?>
                                                        <div>Unique values: <?= $stats['unique'] ?? 0 ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Floating Action Button -->
    <button class="fab" id="fabButton">
        <i class="fas fa-question"></i>
    </button>

    <!-- Footer -->
    <!-- Footer -->
    <div class="text-center mt-4 mb-4 text-muted small">
        <div class="d-flex justify-content-center align-items-center gap-3">
            <span>Skipper DB Explorer v1.0</span>
            <span>•</span>
            <span><i class="fas fa-database me-1"></i> <?= count($tables) ?> tables</span>
            <span>•</span>
            <span><i class="fas fa-code me-1"></i> Powered by Chart.js & Bootstrap</span>
            <span>•</span>
            <a href="?logout=1" class="text-decoration-none text-muted">
                <i class="fas fa-sign-out-alt me-1"></i>Logout
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Store data for JavaScript
        <?php if ($tableData && $isCustomQuery): ?>
            const queryData = <?= json_encode($tableData['rows']) ?>;
            const columnTypes = <?= json_encode($tableData['column_types'] ?? []) ?>;
            const suggestedGraphs = <?= json_encode($tableData['suggested_graphs'] ?? []) ?>;
        <?php endif; ?>

        let currentChart = null;
        let currentGraphType = 'line';

        // Theme Management
        document.addEventListener('DOMContentLoaded', function () {
            // Check for saved theme preference or default to dark
            const savedTheme = localStorage.getItem('theme') || 'dark';
            const savedColor = localStorage.getItem('themeColor') || 'blue';

            // Apply saved theme
            if (savedTheme === 'light') {
                document.documentElement.setAttribute('data-theme', 'light');
                document.getElementById('themeToggle').checked = true;
            }

            // Apply saved color
            document.documentElement.setAttribute('data-theme-color', savedColor);
            document.querySelectorAll('.color-option').forEach(option => {
                option.classList.remove('active');
                if (option.getAttribute('data-color') === savedColor) {
                    option.classList.add('active');
                }
            });

            // Theme toggle event listener
            document.getElementById('themeToggle').addEventListener('change', function () {
                if (this.checked) {
                    document.documentElement.setAttribute('data-theme', 'light');
                    localStorage.setItem('theme', 'light');
                } else {
                    document.documentElement.removeAttribute('data-theme');
                    localStorage.setItem('theme', 'dark');
                }

                // Update chart colors if chart exists
                if (currentChart) {
                    updateChartColors();
                }
            });

            // Color picker event listeners
            document.querySelectorAll('.color-option').forEach(option => {
                option.addEventListener('click', function () {
                    const color = this.getAttribute('data-color');

                    // Update active state
                    document.querySelectorAll('.color-option').forEach(opt => {
                        opt.classList.remove('active');
                    });
                    this.classList.add('active');

                    // Apply color theme
                    document.documentElement.setAttribute('data-theme-color', color);
                    localStorage.setItem('themeColor', color);

                    // Update chart colors if chart exists
                    if (currentChart) {
                        updateChartColors();
                    }
                });
            });

            // FAB button click handler
            document.getElementById('fabButton').addEventListener('click', function () {
                alert('Skipper DB Explorer v1.0\n\nA powerful database visualization tool that allows you to:\n\n• Execute custom SQL queries\n• Visualize data with interactive charts\n• Analyze column types and statistics\n• Switch between dark and light themes\n• Customize accent colors\n\nFor more information, contact xkipper007@gmail.com');
            });
        });

        // Update chart colors based on theme
        function updateChartColors() {
            if (!currentChart) return;

            const isDark = document.documentElement.getAttribute('data-theme') !== 'light';
            const textColor = isDark ? '#a0aec0' : '#4a5568';
            const gridColor = isDark ? 'rgba(255, 255, 255, 0.05)' : 'rgba(0, 0, 0, 0.05)';

            // Update chart options
            if (currentChart.options.scales) {
                if (currentChart.options.scales.x) {
                    currentChart.options.scales.x.ticks.color = textColor;
                    currentChart.options.scales.x.grid.color = gridColor;
                    if (currentChart.options.scales.x.title) {
                        currentChart.options.scales.x.title.color = textColor;
                    }
                }

                if (currentChart.options.scales.y) {
                    currentChart.options.scales.y.ticks.color = textColor;
                    currentChart.options.scales.y.grid.color = gridColor;
                    if (currentChart.options.scales.y.title) {
                        currentChart.options.scales.y.title.color = textColor;
                    }
                }
            }

            if (currentChart.options.plugins.legend) {
                currentChart.options.plugins.legend.labels = {
                    color: textColor
                };
            }

            currentChart.update();
        }

        // View switching
        document.querySelectorAll('[data-view]').forEach(btn => {
            btn.addEventListener('click', function () {
                const view = this.dataset.view;

                // Update button states
                document.querySelectorAll('[data-view]').forEach(b => {
                    b.classList.remove('active');
                });
                this.classList.add('active');

                // Show/hide views
                document.getElementById('tableView').style.display = view === 'table' ? 'block' : 'none';
                document.getElementById('graphView').style.display = view === 'graph' ? 'block' : 'none';

                if (view === 'graph' && !currentChart) {
                    updateChart();
                }
            });
        });

        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Query form handling
        document.getElementById('queryForm')?.addEventListener('submit', function () {
            const btn = document.getElementById('runQueryBtn');
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<span class="loader me-2"></span> Executing...';
            btn.disabled = true;

            setTimeout(() => {
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            }, 2000);
        });

        const sampleQueries = {
            'SELECT': 'SELECT * FROM members LIMIT 10',
            'JOIN': 'SELECT m.name, (SELECT COUNT(*) FROM comments c WHERE c.member_id = m.member_id) as comment_count FROM members m LIMIT 10',
            'GROUP BY': 'SELECT status, COUNT(*) as total FROM members GROUP BY status LIMIT 10',
            'ORDER BY': 'SELECT name, client_since FROM members ORDER BY member_id DESC LIMIT 10',
            'SELECT * FROM ': 'SELECT invoice_id, total_amount, invoice_status FROM billing_invoice LIMIT 10',
            'SELECT COUNT(*), ': 'SELECT user_type, COUNT(*) as users FROM users GROUP BY user_type LIMIT 10',
            'SELECT DATE(': 'SELECT DATE(comment_time) as date, COUNT(*) as count FROM comments GROUP BY DATE(comment_time) LIMIT 10'
        };
        function insertSampleQuery(type) {
            const textarea = document.querySelector('textarea[name="custom_query"]');
            if (sampleQueries[type]) {
                textarea.value = sampleQueries[type];
                textarea.focus();
            }
        }

        function loadGraphSuggestion(index) {
            if (suggestedGraphs && suggestedGraphs[index]) {
                const graph = suggestedGraphs[index];
                changeGraphType(graph.type);

                if (graph.x_axis) {
                    document.getElementById('xAxisSelect').value = graph.x_axis;
                }
                if (graph.y_axis) {
                    document.getElementById('yAxisSelect').value = graph.y_axis;
                }

                // Switch to graph view
                document.querySelector('[data-view="graph"]').click();
                updateChart();
            }
        }

        function changeGraphType(type) {
            currentGraphType = type;
            updateChart();
        }

        function updateChart() {
            if (!queryData || queryData.length === 0) return;

            const xAxis = document.getElementById('xAxisSelect').value;
            const yAxis = document.getElementById('yAxisSelect').value;
            const showGrid = document.getElementById('showGrid').checked;
            const showLegend = document.getElementById('showLegend').checked;

            const ctx = document.getElementById('dataChart').getContext('2d');

            if (currentChart) {
                currentChart.destroy();
            }

            let chartData, options;
            const isDark = document.documentElement.getAttribute('data-theme') !== 'light';
            const textColor = isDark ? '#a0aec0' : '#4a5568';

            switch (currentGraphType) {
                case 'line':
                    ({ chartData, options } = prepareLineChart(xAxis, yAxis));
                    break;
                case 'bar':
                    ({ chartData, options } = prepareBarChart(xAxis, yAxis));
                    break;
                case 'pie':
                    ({ chartData, options } = preparePieChart(xAxis, yAxis));
                    break;
                case 'scatter':
                    ({ chartData, options } = prepareScatterChart(xAxis, yAxis));
                    break;
            }

            options.plugins.legend.display = showLegend;

            if (options.scales) {
                if (options.scales.x) {
                    options.scales.x.grid = { display: showGrid, color: isDark ? 'rgba(255, 255, 255, 0.05)' : 'rgba(0, 0, 0, 0.05)' };
                    options.scales.x.ticks.color = textColor;
                    if (options.scales.x.title) {
                        options.scales.x.title.color = textColor;
                    }
                }
                if (options.scales.y) {
                    options.scales.y.grid = { display: showGrid, color: isDark ? 'rgba(255, 255, 255, 0.05)' : 'rgba(0, 0, 0, 0.05)' };
                    options.scales.y.ticks.color = textColor;
                    if (options.scales.y.title) {
                        options.scales.y.title.color = textColor;
                    }
                }
            }

            if (options.plugins.legend && options.plugins.legend.labels) {
                options.plugins.legend.labels.color = textColor;
            }

            currentChart = new Chart(ctx, {
                type: currentGraphType === 'scatter' ? 'scatter' : currentGraphType,
                data: chartData,
                options: options
            });
        }

        function prepareLineChart(xAxis, yAxis) {
            const labels = queryData.map(row => row[xAxis] || '').slice(0, 50);
            const data = queryData.map(row => parseFloat(row[yAxis]) || 0).slice(0, 50);
            const isDark = document.documentElement.getAttribute('data-theme') !== 'light';
            const color = getComputedStyle(document.documentElement).getPropertyValue('--accent-blue').trim();

            return {
                chartData: {
                    labels: labels,
                    datasets: [{
                        label: yAxis,
                        data: data,
                        borderColor: color,
                        backgroundColor: isDark ? 'rgba(102, 126, 234, 0.1)' : 'rgba(49, 130, 206, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            backgroundColor: isDark ? 'rgba(34, 38, 51, 0.9)' : 'rgba(255, 255, 255, 0.9)',
                            titleColor: isDark ? '#ffffff' : '#1a202c',
                            bodyColor: isDark ? '#a0aec0' : '#4a5568',
                            borderColor: isDark ? '#2a2f42' : '#e2e8f0',
                            borderWidth: 1
                        }
                    },
                    scales: {
                        x: {
                            title: { display: true, text: xAxis },
                            ticks: { color: isDark ? '#718096' : '#4a5568' }
                        },
                        y: {
                            title: { display: true, text: yAxis },
                            ticks: { color: isDark ? '#718096' : '#4a5568' },
                            beginAtZero: true
                        }
                    }
                }
            };
        }

        function prepareBarChart(xAxis, yAxis) {
            const aggregated = {};
            queryData.forEach(row => {
                const key = row[xAxis] || 'Unknown';
                const value = parseFloat(row[yAxis]) || 0;
                aggregated[key] = (aggregated[key] || 0) + value;
            });

            const labels = Object.keys(aggregated).slice(0, 15);
            const data = Object.values(aggregated).slice(0, 15);
            const colors = generateColors(labels.length);
            const isDark = document.documentElement.getAttribute('data-theme') !== 'light';

            return {
                chartData: {
                    labels: labels,
                    datasets: [{
                        label: yAxis,
                        data: data,
                        backgroundColor: colors,
                        borderColor: colors.map(c => c.replace('0.7', '1')),
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: {
                            backgroundColor: isDark ? 'rgba(34, 38, 51, 0.9)' : 'rgba(255, 255, 255, 0.9)',
                            titleColor: isDark ? '#ffffff' : '#1a202c',
                            bodyColor: isDark ? '#a0aec0' : '#4a5568',
                            borderColor: isDark ? '#2a2f42' : '#e2e8f0',
                            borderWidth: 1
                        }
                    },
                    scales: {
                        x: {
                            title: { display: true, text: xAxis },
                            ticks: { color: isDark ? '#718096' : '#4a5568' }
                        },
                        y: {
                            title: { display: true, text: yAxis },
                            ticks: { color: isDark ? '#718096' : '#4a5568' },
                            beginAtZero: true
                        }
                    }
                }
            };
        }

        function preparePieChart(category) {
            const counts = {};
            queryData.forEach(row => {
                const key = row[category] || 'Unknown';
                counts[key] = (counts[key] || 0) + 1;
            });

            const labels = Object.keys(counts);
            const data = Object.values(counts);
            const colors = generateColors(labels.length);
            const isDark = document.documentElement.getAttribute('data-theme') !== 'light';

            return {
                chartData: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: colors,
                        borderColor: colors.map(c => c.replace('0.7', '1')),
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: { color: isDark ? '#a0aec0' : '#4a5568' }
                        },
                        tooltip: {
                            backgroundColor: isDark ? 'rgba(34, 38, 51, 0.9)' : 'rgba(255, 255, 255, 0.9)',
                            titleColor: isDark ? '#ffffff' : '#1a202c',
                            bodyColor: isDark ? '#a0aec0' : '#4a5568',
                            borderColor: isDark ? '#2a2f42' : '#e2e8f0',
                            borderWidth: 1
                        }
                    }
                }
            };
        }

        function prepareScatterChart(xAxis, yAxis) {
            const scatterData = queryData.slice(0, 100).map(row => ({
                x: parseFloat(row[xAxis]) || 0,
                y: parseFloat(row[yAxis]) || 0
            }));
            const isDark = document.documentElement.getAttribute('data-theme') !== 'light';
            const color = getComputedStyle(document.documentElement).getPropertyValue('--accent-blue').trim();

            return {
                chartData: {
                    datasets: [{
                        label: `${yAxis} vs ${xAxis}`,
                        data: scatterData,
                        backgroundColor: isDark ? 'rgba(102, 126, 234, 0.7)' : 'rgba(49, 130, 206, 0.7)',
                        borderColor: isDark ? 'rgba(102, 126, 234, 1)' : 'rgba(49, 130, 206, 1)',
                        pointRadius: 6,
                        pointHoverRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: {
                            backgroundColor: isDark ? 'rgba(34, 38, 51, 0.9)' : 'rgba(255, 255, 255, 0.9)',
                            titleColor: isDark ? '#ffffff' : '#1a202c',
                            bodyColor: isDark ? '#a0aec0' : '#4a5568',
                            borderColor: isDark ? '#2a2f42' : '#e2e8f0',
                            borderWidth: 1
                        }
                    },
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: xAxis
                            },
                            ticks: { color: isDark ? '#718096' : '#4a5568' }
                        },
                        y: {
                            title: {
                                display: true,
                                text: yAxis
                            },
                            ticks: { color: isDark ? '#718096' : '#4a5568' }
                        }
                    }
                }
            };
        }

        function generateColors(count) {
            const colors = [];
            const hueStep = 360 / Math.max(count, 1);

            for (let i = 0; i < count; i++) {
                const hue = (i * hueStep) % 360;
                colors.push(`hsla(${hue}, 70%, 60%, 0.7)`);
            }

            return colors;
        }

        // Auto-initialize if we have data
        document.addEventListener('DOMContentLoaded', function () {
            <?php if ($tableData && $isCustomQuery && !empty($tableData['rows'])): ?>
                if (suggestedGraphs && suggestedGraphs.length > 0) {
                    // Auto-load first suggestion
                    setTimeout(() => loadGraphSuggestion(0), 500);
                } else {
                    // Default to line chart
                    updateChart();
                }
            <?php endif; ?>
        });
    </script>
</body>

</html>