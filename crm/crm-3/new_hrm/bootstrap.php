<?php
require_once dirname(__DIR__) . '/includes/hrm_helpers.php';

// ---- USA-only access gate (admins always allowed) ----
if (!isset($_SESSION['user_country']) && isset($_SESSION['user_id']) && isset($con)) {
    $__nh_uid = (int) $_SESSION['user_id'];
    $__nh_res = mysqli_query($con, "SELECT user_country FROM users WHERE user_id='$__nh_uid' LIMIT 1");
    $__nh_row = $__nh_res ? mysqli_fetch_assoc($__nh_res) : null;
    $_SESSION['user_country'] = $__nh_row ? strtoupper(trim((string) $__nh_row['user_country'])) : '';
}
$__nh_country  = isset($_SESSION['user_country']) ? strtoupper(trim((string) $_SESSION['user_country'])) : '';
$__nh_is_admin = isset($_SESSION['user_type']) && (string) $_SESSION['user_type'] === '1';
if (!$__nh_is_admin && $__nh_country !== 'US') {
    http_response_code(403);
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Access denied</title>'
       . '<style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#f4f7fb;color:#152334;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}'
       . '.box{background:#fff;border:1px solid #d9e2ec;border-radius:14px;box-shadow:0 14px 32px rgba(15,23,42,.06);padding:32px 36px;max-width:460px;text-align:center}'
       . '.box h1{margin:0 0 10px;font-size:22px;color:#b42318}.box p{margin:0 0 18px;color:#475569;font-size:14px}'
       . '.box a{display:inline-block;padding:10px 18px;border-radius:8px;background:#1456b8;color:#fff;text-decoration:none;font-weight:600}</style></head>'
       . '<body><div class="box"><h1>Access denied</h1>'
       . '<p>The New HRM module is available to USA users only.</p>'
       . '<a href="' . htmlspecialchars(hrm_root_path() === '' ? '/' : '../index.php', ENT_QUOTES, 'UTF-8') . '">Back to dashboard</a>'
       . '</div></body></html>';
    exit;
}
// ---- /USA-only access gate ----

function new_hrm_sidebar_access_context()
{
    $context = [
        'acc_add_client' => 'No',
        'acc_view_client' => 'No',
        'acc_view_products' => 'No',
        'acc_view_staff' => 'No',
        'acc_view_entity' => 'No',
        'acc_view_product' => 'No',
        'acc_invoice_and_billing' => 'No',
        'acc_estimates_and_qutations' => 'No',
        'usertype' => isset($_SESSION['user_type']) ? (string) $_SESSION['user_type'] : '',
    ];

    if ($context['usertype'] === '1') {
        foreach ($context as $key => $value) {
            if ($key !== 'usertype') {
                $context[$key] = 'Yes';
            }
        }

        return $context;
    }

    $sessionFallbacks = [
        'acc_add_client' => 'acc_add_new_client',
        'acc_view_client' => 'acc_view_clients_list',
        'acc_view_products' => 'acc_view_products',
        'acc_view_staff' => 'acc_view_staff',
        'acc_view_entity' => 'acc_view_entity_form',
        'acc_view_product' => 'acc_view_product_cycle',
        'acc_invoice_and_billing' => 'acc_invoice_and_billing',
        'acc_estimates_and_qutations' => 'acc_estimates_and_qutations',
    ];

    foreach ($sessionFallbacks as $contextKey => $sessionKey) {
        if (isset($GLOBALS[$contextKey])) {
            $context[$contextKey] = (string) $GLOBALS[$contextKey];
        } elseif (isset($_SESSION[$sessionKey])) {
            $context[$contextKey] = (string) $_SESSION[$sessionKey];
        }
    }

    if (isset($GLOBALS['usertype'])) {
        $context['usertype'] = (string) $GLOBALS['usertype'];
    }

    return $context;
}

function new_hrm_render_page_start($title, $heading, $description = '')
{
    $root = hrm_root_path();
    $sidebarContext = new_hrm_sidebar_access_context();

    $acc_add_client = $sidebarContext['acc_add_client'];
    $acc_view_client = $sidebarContext['acc_view_client'];
    $acc_view_products = $sidebarContext['acc_view_products'];
    $acc_view_staff = $sidebarContext['acc_view_staff'];
    $acc_view_entity = $sidebarContext['acc_view_entity'];
    $acc_view_product = $sidebarContext['acc_view_product'];
    $acc_invoice_and_billing = $sidebarContext['acc_invoice_and_billing'];
    $acc_estimates_and_qutations = $sidebarContext['acc_estimates_and_qutations'];
    $usertype = $sidebarContext['usertype'];

    chdir($root);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo hrm_e($title); ?></title>
    <base href="../">
    <?php include $root . '/includes/links.php'; ?>
    <style>
        :root {
            --hrm-bg: #f4f7fb;
            --hrm-surface: #ffffff;
            --hrm-surface-soft: #f8fafc;
            --hrm-border: #d9e2ec;
            --hrm-border-strong: #c5d1de;
            --hrm-text: #152334;
            --hrm-muted: #64748b;
            --hrm-primary: #1456b8;
            --hrm-primary-soft: #e8f1ff;
            --hrm-success: #15803d;
            --hrm-success-soft: #e8f7ee;
            --hrm-warning: #b45309;
            --hrm-warning-soft: #fff4df;
            --hrm-danger: #b42318;
            --hrm-danger-soft: #feeceb;
            --hrm-accent: #6d28d9;
            --hrm-accent-soft: #f1ebff;
            --hrm-shadow: 0 14px 32px rgba(15, 23, 42, 0.06);
        }
        body.theme-3 {
            background: linear-gradient(180deg, #f9fbfd 0%, var(--hrm-bg) 100%);
        }
        .dash-content {
            padding-bottom: 24px;
        }
        .hrm-page-hero {
            border: 1px solid var(--hrm-border);
            border-radius: 14px;
            background: linear-gradient(135deg, #ffffff 0%, #f7faff 55%, #eef4ff 100%);
            box-shadow: var(--hrm-shadow);
            overflow: hidden;
            position: relative;
        }
        .hrm-page-hero::after {
            content: "";
            position: absolute;
            inset: 0 0 auto auto;
            width: 280px;
            height: 280px;
            background: radial-gradient(circle, rgba(20, 86, 184, 0.16) 0%, rgba(20, 86, 184, 0) 72%);
            pointer-events: none;
        }
        .hrm-page-hero .card-body {
            padding: 24px 28px;
            position: relative;
            z-index: 1;
        }
        .hrm-page-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border-radius: 999px;
            background: var(--hrm-primary-soft);
            color: var(--hrm-primary);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
        }
        .hrm-page-summary {
            max-width: 760px;
            color: var(--hrm-muted);
            font-size: 14px;
            margin: 10px 0 0;
        }
        .hrm-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: flex-end;
        }
        .hrm-metric-card {
            border: 1px solid var(--hrm-border);
            border-radius: 12px;
            background: linear-gradient(180deg, var(--hrm-surface) 0%, var(--hrm-surface-soft) 100%);
            padding: 18px 18px 16px;
            height: 100%;
            box-shadow: var(--hrm-shadow);
        }
        .hrm-metric-label {
            color: var(--hrm-muted);
            font-size: 12px;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: .06em;
            font-weight: 700;
        }
        .hrm-metric-value {
            font-size: 30px;
            font-weight: 700;
            color: var(--hrm-text);
        }
        .hrm-section-card {
            border: 1px solid var(--hrm-border);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--hrm-shadow);
            background: var(--hrm-surface);
        }
        .hrm-section-card .card-header {
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            border-bottom: 1px solid var(--hrm-border);
            padding: 16px 20px;
        }
        .hrm-section-card .card-body {
            padding: 18px 20px;
        }
        .hrm-avatar {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: var(--hrm-primary-soft);
            color: var(--hrm-primary);
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .hrm-mini-label {
            color: var(--hrm-muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .04em;
            font-weight: 700;
        }
        .hrm-table td,
        .hrm-table th {
            vertical-align: middle;
        }
        .hrm-table thead th {
            border-bottom: 1px solid var(--hrm-border-strong);
            background: #f8fafc;
            color: var(--hrm-muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .06em;
            font-weight: 700;
        }
        .hrm-table tbody tr:hover {
            background: #f8fbff;
        }
        .hrm-filter-bar {
            border: 1px solid var(--hrm-border);
            border-radius: 12px;
            padding: 16px 18px 4px;
            background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
            box-shadow: var(--hrm-shadow);
        }
        .hrm-stack {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .hrm-kv {
            display: grid;
            gap: 14px;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        }
        .hrm-kv-item {
            border: 1px solid var(--hrm-border);
            border-radius: 10px;
            padding: 14px 16px;
            background: var(--hrm-surface-soft);
        }
        .hrm-inline-stat {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #edf2f7;
        }
        .hrm-inline-stat:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }
        .hrm-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            line-height: 1;
        }
        .hrm-badge.is-success { background: var(--hrm-success-soft); color: var(--hrm-success); }
        .hrm-badge.is-warning { background: var(--hrm-warning-soft); color: var(--hrm-warning); }
        .hrm-badge.is-danger { background: var(--hrm-danger-soft); color: var(--hrm-danger); }
        .hrm-badge.is-accent { background: var(--hrm-accent-soft); color: var(--hrm-accent); }
        .hrm-badge.is-neutral { background: #eef2f6; color: #475569; }
        .hrm-table-note {
            color: var(--hrm-muted);
            font-size: 12px;
        }
        .hrm-toolbar-title {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .hrm-toolbar-title h5 {
            margin: 0;
            color: var(--hrm-text);
        }
        .hrm-toolbar-title p {
            margin: 0;
            color: var(--hrm-muted);
            font-size: 13px;
        }
        .hrm-list-card {
            border: 1px solid var(--hrm-border);
            border-radius: 10px;
            padding: 14px 16px;
            background: #fff;
        }
        .hrm-list-card + .hrm-list-card {
            margin-top: 12px;
        }
        .hrm-grid-2 {
            display: grid;
            grid-template-columns: 1.2fr .8fr;
            gap: 18px;
        }
        .hrm-grid-3 {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }
        .hrm-form-section {
            border-top: 1px solid var(--hrm-border);
            margin-top: 18px;
            padding-top: 18px;
        }
        .hrm-empty {
            padding: 24px;
            text-align: center;
            border: 1px dashed var(--hrm-border-strong);
            border-radius: 12px;
            background: #fbfcfe;
            color: var(--hrm-muted);
        }
        .hrm-subtle {
            color: var(--hrm-muted);
        }
        @media (max-width: 991px) {
            .hrm-grid-2,
            .hrm-grid-3 {
                grid-template-columns: 1fr;
            }
            .hrm-actions {
                justify-content: flex-start;
                margin-top: 16px;
            }
        }
    </style>
</head>
<body class="theme-3">
    <div class="loader-bg">
        <div class="loader-track">
            <div class="loader-fill"></div>
        </div>
    </div>

    <?php include $root . '/includes/sidebar.php'; ?>
    <?php include $root . '/includes/header.php'; ?>

    <div class="dash-container">
        <div class="dash-content">
            <div class="card hrm-page-hero">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-lg-8">
                            <span class="hrm-page-eyebrow">Workforce Operations</span>
                            <div class="page-header-title mt-3">
                                <h4 class="m-b-10"><?php echo hrm_e($heading); ?></h4>
                            </div>
                            <?php if ($description !== ''): ?>
                                <p class="hrm-page-summary"><?php echo hrm_e($description); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="col-lg-4">
                            <div class="hrm-actions">
                                <a href="new_hrm/index.php" class="btn btn-light">Dashboard</a>
                                <a href="new_hrm/employees.php" class="btn btn-light">People</a>
                                <a href="new_hrm/attendance_logs.php" class="btn btn-light">Attendance</a>
                                <a href="new_hrm/leaves.php" class="btn btn-light"><?php echo hrm_is_admin() ? 'Leaves' : 'My Leaves'; ?></a>
                                <a href="new_hrm/breaks.php" class="btn btn-light"><?php echo hrm_is_admin() ? 'Breaks' : 'My Breaks'; ?></a>
                                <?php if (hrm_is_admin()): ?>
                                    <a href="new_hrm/payroll.php" class="btn btn-primary">Payroll</a>
                                <?php else: ?>
                                    <a href="new_hrm/salary.php" class="btn btn-primary">My Salary</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
            $flash = hrm_consume_flash();
            if ($flash):
                $alertClass = $flash['type'] === 'success' ? 'alert-success' : 'alert-danger';
                ?>
                <div class="alert <?php echo $alertClass; ?>"><?php echo hrm_e($flash['message']); ?></div>
            <?php endif; ?>
    <?php
}

function new_hrm_render_page_end()
{
    $root = hrm_root_path();
    ?>
        </div>
    </div>
    <?php include $root . '/includes/footer.php'; ?>
    <?php include $root . '/includes/scripts.php'; ?>
</body>
</html>
    <?php
}
