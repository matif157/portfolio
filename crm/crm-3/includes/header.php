<?php
include 'includes/db.php';
include __DIR__ . '/theme.php';
sps_theme_inject($con);

/* ---------- 1.  user picture (unchanged) ---------- */
$uuid = $_SESSION['user_id'] ?? 0;
$uuquery = mysqli_query($con, "SELECT user_pic FROM users WHERE user_id='$uuid' LIMIT 1");
$user_pic = 'no-available-image.png';
if ($row = mysqli_fetch_assoc($uuquery))
    $user_pic = $row['user_pic'] ?: $user_pic;
if (!file_exists(__DIR__ . '/../uploads/staff_images/' . $user_pic))
    $user_pic = 'no-available-image.png';

/* ---------- 2.  Get orders that need to be renewed ---------- */
$renewals = [];
$today = date('Y-m-d');
$next30 = date('Y-m-d', strtotime('+30 days'));

// Check if current user is admin
$is_admin = false;
$admin_check = mysqli_query($con, "SELECT user_type FROM users WHERE user_id='$uuid' LIMIT 1");
if ($admin_row = mysqli_fetch_assoc($admin_check)) {
    $is_admin = ($admin_row['user_type'] === 'admin' || $admin_row['user_type'] === '1');
}

// Build query based on user role
$renewal_query = mysqli_query(
    $con,
    "
  SELECT o.order_id, o.mem_id, o.bil_cyc_months, o.start_date, o.exp_date,
         m.name AS client_name, m.bus_name, mt.type_name, ms.membership_name,
         u.user_name AS assigned_to_name, u.user_email AS assigned_to_email
  FROM orders_auto_renewed o
  JOIN members m ON o.mem_id=m.member_id
  JOIN member_type mt ON m.type=mt.type_id
  JOIN memberships ms ON o.prod_id=ms.membership_id
  LEFT JOIN users u ON o.assign_to = u.user_id
  WHERE o.order_status='1'
  AND o.exp_date BETWEEN '$today' AND '$next30'"
    . ($is_admin ? "" : " AND (o.assign_to = '$uuid' OR o.assign_to IS NULL)")
    . " ORDER BY o.exp_date ASC"
);

// Debug: Check if query returns results
if (!$renewal_query) {
    die("Error in renewal query: " . mysqli_error($con));
}

while ($r = mysqli_fetch_assoc($renewal_query)) {
    $days = (new DateTime())->diff(new DateTime($r['exp_date']))->days;
    $r['days_left'] = $days;
    if ($days <= 7) {
        $r['status'] = 'Urgent';
        $r['status_class'] = 'badge-urgent';
    } elseif ($days <= 30) {
        $r['status'] = 'Upcoming';
        $r['status_class'] = 'badge-pending';
    } else {
        $r['status'] = 'Scheduled';
        $r['status_class'] = 'badge-renewed';
    }
    $renewals[] = $r;
}

/* ---------- 3.  Get regular activities ---------- */
$logs = [];
$res = mysqli_query($con, "
  SELECT  l.*,
          u.user_name  AS actor_name
  FROM    activity_logs l
  LEFT JOIN users u ON u.user_id=l.user_id
  ORDER BY l.created_at DESC
  LIMIT 15
");

while ($r = mysqli_fetch_assoc($res))
    $logs[] = $r;

/* ---------- 3b.  Flag activities whose client is inactive ----------
   Resolve the member each activity refers to — directly for member activities,
   otherwise parsed from the description text (e.g. "Member ID: 12", "client ID:
   12", "member #12", "for client 12") — then look their status up in a single
   query. Activities on a member whose status = 0 (inactive) get a red marker in
   the activity log so the user can tell the client is no longer active. */
$activity_member_ids = [];               // log index => member_id|null
foreach ($logs as $i => $l) {
    $mid = null;
    $otype = strtolower($l['object_type'] ?? '');
    if ($otype === 'member' || $otype === 'members') {
        $mid = (int) $l['object_id'];
    } elseif (preg_match('/(?:member|client)\s*(?:id)?\s*[:#]?\s*(\d+)/i', $l['description'] ?? '', $m)) {
        $mid = (int) $m[1];
    }
    $activity_member_ids[$i] = $mid ?: null;
}

$inactive_members = [];                   // member_id => true (status = 0)
$ids_to_check = array_values(array_unique(array_filter($activity_member_ids)));
if (!empty($ids_to_check)) {
    $in = implode(',', array_map('intval', $ids_to_check));
    $statusRes = mysqli_query($con, "SELECT member_id, status FROM members WHERE member_id IN ($in)");
    if ($statusRes) {
        while ($sr = mysqli_fetch_assoc($statusRes)) {
            if ((string) $sr['status'] === '0')
                $inactive_members[(int) $sr['member_id']] = true;
        }
    }
}

/* ---------- 4.  normalise activities ---------- */
$recent_activities = [];
foreach ($logs as $i => $l) {
    $mid = $activity_member_ids[$i];
    $recent_activities[] = [
        'activity_type' => strtolower($l['object_type']) . '_' . strtolower($l['action']),
        'description' => $l['description'] ?: ucfirst(strtolower($l['action'])) . ' ' . $l['object_type'] . ' #' . $l['object_id'],
        'timestamp' => $l['created_at'],
        'added_by' => $l['actor_name'],
        'client_id' => $mid,
        'client_inactive' => ($mid && isset($inactive_members[$mid])),
    ];
}

// Calculate counts
$renewal_count = count($renewals);
$activity_count = count($recent_activities);
$unread_count = $activity_count; // Regular notifications count

/* ---------- 5.  Quick Action permission gates ----------
   Reuses the same $acc_* role/permission flags that includes/auth.php
   already sets for every page that includes this header (add_client.php,
   users.php, quotations.php, memberships.php, etc.), so a Quick Action
   shortcut is only offered when the user could actually use the page it
   leads to. Items with no dedicated permission column in dashboard_access
   (Add Project, Payment Plan, Instant Invoice, Client Type, Reminder, Bulk
   Invoice, Bulk Email, SMS, Stripe Link) are left open to any logged-in
   user, matching how those pages behave today. */
$qa_perm_add_client  = (($acc_add_client ?? 'No') === 'Yes');
$qa_perm_billing     = (($acc_billing_client ?? 'No') === 'Yes');
$qa_perm_quotation   = (($acc_estimates_and_qutations ?? 'No') === 'Yes');
$qa_perm_add_user    = (($acc_add_edit_staff ?? 'No') === 'Yes');
$qa_perm_add_service = (($acc_add_edit_products ?? 'No') === 'Yes');
?>
<!-- [ Header ] start -->
<header class="dash-header transprent-bg">
    <div class="header-wrapper">

        <!-- ═══ Zone A: hamburger + brand ═══ -->
        <ul class="list-unstyled header-zone header-left">
            <li class="dash-h-item mob-hamburger">
                <a href="#!" class="dash-head-link" id="mobile-collapse">
                    <div class="hamburger hamburger--arrowturn">
                        <div class="hamburger-box">
                            <div class="hamburger-inner"></div>
                        </div>
                    </div>
                </a>
            </li>
            <li class="dash-h-item header-brand">
                <a href="index.php" class="dash-head-link">
                    <span class="brand-chip">S</span>
                        <span class="brand-text">SPS CRM</span>
                </a>
            </li>
        </ul>

        <!-- ═══ Zone B: global search ═══ -->
        <div class="header-zone header-search">
            <form id="searchForm" action="view_clients.php" method="GET" class="search-form">
                <div class="search-wrapper">
                    <!-- Search-by dropdown -->
                    <div class="search-by-filter">
                        <div class="search-by-dropdown">
                            <div class="search-by-selected" id="searchBySelected">
                                <i class="ti ti-search search-icon"></i>
                                <span id="searchByLabel">Search By: All</span>
                                <i class="ti ti-chevron-down dropdown-arrow"></i>
                            </div>
                            <div class="search-by-options" id="searchByOptions">
                                <div class="search-option" data-value="all">
                                    <i class="ti ti-apps option-icon"></i><span>All Fields</span>
                                </div>
                                <div class="search-option" data-value="member_id">
                                    <i class="ti ti-id option-icon"></i><span>Member ID</span>
                                </div>
                                <div class="search-option" data-value="name">
                                    <i class="ti ti-user option-icon"></i><span>Name</span>
                                </div>
                                <div class="search-option" data-value="phone">
                                    <i class="ti ti-phone option-icon"></i><span>Phone</span>
                                </div>
                                <div class="search-option" data-value="email">
                                    <i class="ti ti-mail option-icon"></i><span>Email</span>
                                </div>
                                <div class="search-option" data-value="business">
                                    <i class="ti ti-building option-icon"></i><span>Business</span>
                                </div>
                                <div class="search-option" data-value="ein">
                                    <i class="ti ti-file-invoice option-icon"></i><span>EIN</span>
                                </div>
                                <div class="search-option" data-value="ssn">
                                    <i class="ti ti-id-badge option-icon"></i><span>SSN</span>
                                </div>
                                <div class="search-option" data-value="pmb">
                                    <i class="ti ti-mailbox option-icon"></i><span>PMB</span>
                                </div>
                                <div class="search-option" data-value="invoice">
                                    <i class="ti ti-file-invoice option-icon"></i><span>Invoice</span>
                                </div>
                                <div class="search-option" data-value="instant_invoice">
                                    <i class="ti ti-receipt option-icon"></i><span>Instant Invoice</span>
                                </div>
                            </div>
                            <input type="hidden" id="searchBy" name="search_by" value="all">
                        </div>
                    </div>

                    <!-- Status filter toggle -->
                    <div class="status-filter">
                        <div class="status-option">
                            <input type="radio" id="active" name="profile_status" value="1" checked>
                            <label for="active">Active</label>
                        </div>
                        <div class="status-option">
                            <input type="radio" id="inactive" name="profile_status" value="0">
                            <label for="inactive">Inactive</label>
                        </div>
                    </div>

                    <!-- Search input + submit -->
                    <div class="search-input-group">
                        <input type="text" class="form-control search-input"
                            placeholder="Search Clients / Instant Invoices..."
                            name="search" id="searchInput" data-toggle="tooltip" data-placement="top">
                        <button class="search-button" type="submit">
                            <i class="fa fa-search"></i>
                        </button>
                    </div>
                </div>
            </form>
            <div id="searchResults" class="search-results-dropdown"></div>
        </div>

        <!-- ═══ Zone C: contextual icons + profile ═══ -->
        <ul class="list-unstyled header-zone header-right">
            <!-- Renewals pill -->
            <li>
                <div class="renewal-container" id="renewalIcon" title="Product Renewals">
                    <span class="pill-label">Renewals</span>
                    <span class="pill"><i class="ti ti-calendar-time"></i></span>
                    <?php if ($renewal_count > 0): ?>
                        <span class="notification-badge"><?php echo $renewal_count > 9 ? '9+' : $renewal_count; ?></span>
                    <?php endif; ?>
                </div>
            </li>
            <!-- Notifications pill -->
            <li>
                <div class="bell-container" id="bellIcon" title="System Activity">
                    <span class="pill-label">Notifications</span>
                    <span class="pill"><i class="ti ti-bell"></i></span>
                    <?php if ($unread_count > 0): ?>
                        <span class="notification-badge"><?php echo $unread_count > 9 ? '9+' : $unread_count; ?></span>
                    <?php endif; ?>
                </div>
            </li>
            <!-- Fullscreen -->
            <li>
                <div class="fullscreen-btn" id="fullscreenToggle" title="Toggle Fullscreen (F11)">
                    <i class="ti ti-maximize" id="fullscreenIcon"></i>
                </div>
            </li>
            <!-- Quick action -->
            <li>
                <div class="quick-action-btn" id="quickActionBtn" title="Quick Action">
                    <i class="ti ti-plus"></i>
                </div>
            </li>
            <!-- Profile dropdown -->
            <li class="dropdown dash-h-item drp-company">
                <a class="dash-head-link dropdown-toggle arrow-none me-0" data-bs-toggle="dropdown" href="#"
                    role="button" aria-haspopup="false" aria-expanded="false">
                    <span class="theme-avtar">
                        <img src="uploads/staff_images/<?php echo htmlspecialchars($user_pic); ?>"
                            class="img-fluid rounded-circle">
                    </span>
                    <span class="hide-mob ms-2">Hi,
                        <?php echo htmlspecialchars($_SESSION['fullname'] ?? ''); ?></span>
                    <i class="ti ti-chevron-down drp-arrow nocolor hide-mob"></i>
                </a>
                <div class="dropdown-menu dash-h-dropdown">
                    <a href="profile.php" class="dropdown-item">
                        <i class="ti ti-user text-dark"></i><span>Profile</span>
                    </a>
                    <a href="staff_details.php" class="dropdown-item">
                        <i class="ti ti-id-badge"></i><span>Staff Details</span>
                    </a>
                    <a href="user_profile.php" class="dropdown-item">
                        <i class="ti ti-activity"></i><span>Workflow</span>
                    </a>
                    <a href="quickphrases.php" class="dropdown-item">
                        <i class="ti ti-message"></i><span>Quick Phrases</span>
                    </a>
                    <a href="logout.php"
                        onclick="event.preventDefault(); document.getElementById('frm-logout').submit();"
                        class="dropdown-item">
                        <i class="ti ti-power"></i><span>Log out</span>
                    </a>
                    <form id="frm-logout" action="logout.php" method="POST" class="d-none"></form>
                </div>
            </li>
        </ul>

        <!-- ═══ Dropdown containers (positioned over the header) ═══ -->
        <div id="renewalsDropdown" class="renewals-dropdown">
            <div class="renewals-header">
                <h6>Product Renewals (<?php echo $renewal_count; ?>)</h6>
                <a href="ToBeRenewed.php" class="view-all-link">View All</a>
            </div>
            <div class="renewals-body">
                <?php if (!empty($renewals)): ?>
                    <?php foreach ($renewals as $renewal): ?>
                        <div class="renewal-item">
                            <div class="renewal-icon">
                                <?php
                                $icon = 'ti-calendar-time';
                                $color = '#5c6bc0';
                                switch ($renewal['status_class']) {
                                    case 'badge-urgent':   $color = '#d32f2f'; break;
                                    case 'badge-pending':  $color = '#ff8f00'; break;
                                    case 'badge-renewed':  $color = '#388e3c'; break;
                                }
                                ?>
                                <i class="ti <?php echo $icon; ?>" style="color: <?php echo $color; ?>;"></i>
                            </div>
                            <div class="renewal-content">
                                <div class="renewal-title">
                                    <a href="client_details.php?id=<?php echo $renewal['mem_id']; ?>"
                                        style="color: inherit; text-decoration: none;">
                                        <?php echo htmlspecialchars($renewal['client_name']); ?> -
                                        <?php echo htmlspecialchars($renewal['membership_name']); ?>
                                    </a>
                                    <span class="badge <?php echo $renewal['status_class']; ?> ms-2"><?php echo htmlspecialchars($renewal['status']); ?></span>
                                </div>
                                <div class="renewal-meta">
                                    <span class="renewal-user">Assigned to:
                                        <?php echo !empty($renewal['assigned_to_name']) ?
                                            htmlspecialchars($renewal['assigned_to_name']) :
                                            '<span class="text-muted">Not assigned</span>'; ?>
                                    </span>
                                    <span class="renewal-time"><?php echo $renewal['days_left']; ?> days left</span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="renewal-item">
                        <div class="renewal-content text-center">
                            <div class="renewal-title">
                                <?php echo $is_admin ? 'No renewals due in next 30 days' : 'No renewals assigned to you'; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="activitiesDropdown" class="activities-dropdown">
            <div class="activities-header">
                <h6>System Activity Log</h6>
                <a href="activity_logs.php" class="view-all-link">View All</a>
            </div>
            <div class="activities-body">
                <?php if (!empty($recent_activities)): ?>
                    <?php foreach ($recent_activities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon" style="font-size: 16px; color: #fff; background-color: <?php
                            $bgColor = '#6c757d';
                            $type = strtolower($activity['activity_type']);
                            if (strpos($type, 'orders') !== false || strpos($type, 'order') !== false) { $bgColor = '#6fd943'; }
                            elseif (strpos($type, 'member') !== false && strpos($type, 'spouse') === false) { $bgColor = '#007bff'; }
                            elseif (strpos($type, 'billing_invoice') !== false || strpos($type, 'invoice') !== false) { $bgColor = '#28a745'; }
                            elseif (strpos($type, 'comments') !== false || strpos($type, 'comment') !== false) { $bgColor = '#6f42c1'; }
                            elseif (strpos($type, 'quotation_note') !== false) { $bgColor = '#fd7e14'; }
                            elseif (strpos($type, 'quotation') !== false || strpos($type, 'quotations') !== false) { $bgColor = '#fd7e14'; }
                            elseif (strpos($type, 'spouse') !== false) { $bgColor = '#20c997'; }
                            elseif (strpos($type, 'client_field_notes') !== false) { $bgColor = '#17a2b8'; }
                            echo $bgColor; ?>">
                                <?php
                                $icon = 'ti-activity';
                                $type = strtolower($activity['activity_type']);
                                if (strpos($type, 'orders') !== false || strpos($type, 'order') !== false) {
                                    $icon = 'ti-shopping-cart'; if (strpos($type, 'update') !== false) $icon = 'ti-edit'; elseif (strpos($type, 'export') !== false) $icon = 'ti-send'; elseif (strpos($type, 'delete') !== false) $icon = 'ti-trash';
                                } elseif (strpos($type, 'member') !== false && strpos($type, 'spouse') === false) { $icon = 'ti-user'; if (strpos($type, 'update') !== false) $icon = 'ti-edit'; }
                                elseif (strpos($type, 'billing_invoice') !== false || strpos($type, 'invoice') !== false) { $icon = 'ti-file-invoice'; if (strpos($type, 'update') !== false) $icon = 'ti-edit'; elseif (strpos($type, 'export') !== false) $icon = 'ti-send'; elseif (strpos($type, 'delete') !== false) $icon = 'ti-trash'; elseif (strpos($type, 'pay') !== false) $icon = 'ti-credit-card'; }
                                elseif (strpos($type, 'comments') !== false || strpos($type, 'comment') !== false) { $icon = 'ti-message'; if (strpos($type, 'update') !== false) $icon = 'ti-edit'; }
                                elseif (strpos($type, 'quotation_note') !== false) { $icon = 'ti-note'; if (strpos($type, 'update') !== false) $icon = 'ti-edit'; elseif (strpos($type, 'delete') !== false) $icon = 'ti-trash'; }
                                elseif (strpos($type, 'quotation') !== false || strpos($type, 'quotations') !== false) { $icon = 'ti-file-dollar'; if (strpos($type, 'update') !== false) $icon = 'ti-edit'; elseif (strpos($type, 'delete') !== false) $icon = 'ti-trash'; }
                                elseif (strpos($type, 'spouse') !== false) { $icon = 'ti-users'; }
                                elseif (strpos($type, 'client_field_notes') !== false) { $icon = 'ti-notes'; if (strpos($type, 'update') !== false) $icon = 'ti-edit'; }
                                ?>
                                <i class="ti <?php echo $icon; ?>"></i>
                                <?php if (!empty($activity['client_inactive'])): ?>
                                    <span class="activity-inactive-dot" title="This client is inactive"></span>
                                <?php endif; ?>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title<?php echo strpos($activity['activity_type'], 'delete') !== false ? ' activity-title-delete' : ''; ?>">
                                    <?php echo htmlspecialchars($activity['description'] ?? ''); ?>
                                    <?php if (!empty($activity['client_inactive'])): ?>
                                        <span class="inactive-client-badge" title="This client is currently inactive"><span class="ic-dot"></span> Inactive</span>
                                    <?php endif; ?>
                                </div>
                                <div class="activity-meta">
                                    <?php if (!empty($activity['added_by'])): ?><span class="activity-user">by <?php echo htmlspecialchars($activity['added_by']); ?></span>
                                    <?php elseif (!empty($activity['created_by'])): ?><span class="activity-user">by <?php echo htmlspecialchars($activity['created_by']); ?></span>
                                    <?php elseif (!empty($activity['author'])): ?><span class="activity-user">by <?php echo htmlspecialchars($activity['author']); ?></span>
                                    <?php elseif (!empty($activity['employee'])): ?><span class="activity-user">by <?php echo htmlspecialchars($activity['employee']); ?></span>
                                    <?php elseif (!empty($activity['user_name'])): ?><span class="activity-user">by <?php echo htmlspecialchars($activity['user_name']); ?></span>
                                    <?php endif; ?>
                                    <span class="activity-time"><?php echo ($activity['timestamp'] ?? '') ? time_elapsed_string($activity['timestamp']) : 'Recently'; ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="activity-item">
                        <div class="activity-content text-center">
                            <div class="activity-title">No recent activities</div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="quickActionDropdown" class="quick-action-dropdown">
            <div class="qa-header">Quick Action</div>
            <div class="qa-body">
                <div class="qa-item" id="qaAddInvoiceBtn" role="button" tabindex="0" data-allowed="<?php echo $qa_perm_billing ? 1 : 0; ?>">
                    <span class="qa-icon qa-tint-blue"><i class="ti ti-file-text"></i></span><span class="qa-label">Add Invoice</span>
                </div>
                <a href="add_client.php" class="qa-item" data-allowed="<?php echo $qa_perm_add_client ? 1 : 0; ?>">
                    <span class="qa-icon qa-tint-green"><i class="ti ti-user-plus"></i></span><span class="qa-label">Add Client</span>
                </a>
                <a href="add_new_projects.php" class="qa-item">
                    <span class="qa-icon qa-tint-orange"><i class="ti ti-folder"></i></span><span class="qa-label">Add Project</span>
                </a>
                <a href="quotations.php" class="qa-item" data-allowed="<?php echo $qa_perm_quotation ? 1 : 0; ?>">
                    <span class="qa-icon qa-tint-purple"><i class="ti ti-edit"></i></span><span class="qa-label">Add Quotation</span>
                </a>
                <div class="qa-divider"></div>
                <div class="qa-item qa-more" id="qaMoreToggle">
                    <span class="qa-icon qa-tint-gray"><i class="ti ti-dots"></i></span><span class="qa-label">More</span>
                    <i class="ti ti-chevron-right qa-more-arrow"></i>
                </div>
            </div>
            <div class="qa-more-panel" id="qaMorePanel">
                <div class="qa-header qa-subheader">Additional Actions</div>
                <div class="qa-body">
                    <a href="users.php" class="qa-item" data-allowed="<?php echo $qa_perm_add_user ? 1 : 0; ?>"><span class="qa-icon-plain qa-c-blue"><i class="ti ti-user-plus"></i></span><span class="qa-label">Add User</span></a>
                    <a href="payment_plan.php" class="qa-item"><span class="qa-icon-plain qa-c-green"><i class="ti ti-credit-card"></i></span><span class="qa-label">Add Payment Plan</span></a>
                    <a href="instant_invoice.php" class="qa-item"><span class="qa-icon-plain qa-c-yellow"><i class="ti ti-bolt"></i></span><span class="qa-label">Add Instant Invoice</span></a>
                    <a href="memberships.php" class="qa-item" data-allowed="<?php echo $qa_perm_add_service ? 1 : 0; ?>"><span class="qa-icon-plain qa-c-blue"><i class="ti ti-coins"></i></span><span class="qa-label">Add Service</span></a>
                    <a href="client_types.php" class="qa-item"><span class="qa-icon-plain qa-c-pink"><i class="ti ti-users"></i></span><span class="qa-label">Add Client Type</span></a>
                    <a href="reminder_calender.php" class="qa-item"><span class="qa-icon-plain qa-c-red"><i class="ti ti-bell"></i></span><span class="qa-label">Add Reminder</span></a>
                    <a href="bulk_monthly_payer_gen.php" class="qa-item"><span class="qa-icon-plain qa-c-teal"><i class="ti ti-stack-2"></i></span><span class="qa-label">Create Bulk Inv</span></a>
                    <a href="bulk_email.php" class="qa-item"><span class="qa-icon-plain qa-c-purple"><i class="ti ti-mail"></i></span><span class="qa-label">Send Bulk Email</span></a>
                    <a href="comm_hub.php" class="qa-item"><span class="qa-icon-plain qa-c-green"><i class="ti ti-message-2"></i></span><span class="qa-label">Send SMS</span></a>
                    <a href="stripe_pay_link_gen.php" class="qa-item"><span class="qa-icon-plain qa-c-stripe qa-stripe-text">stripe</span><span class="qa-label">Stripe Link</span></a>
                </div>
            </div>
        </div>
    </div>
</header>
<!-- [ Header ] end -->
<?php include __DIR__ . '/../quick_add.php'; // Quick Action: create Invoice / Estimate from the header ?>


<style>
/* ═══════════════════════════════════════════════════════════════════
   NEW HEADER — PRECISION COMMAND BAR
   Full replacement of the previous shell CSS. Keeps every class/id the
   header scripts rely on; positions all dropdowns correctly near their
   trigger icons (the old design anchored them to the far-left edge).
   Tokens come from theme.php (--sps-* with fallbacks).
   ═══════════════════════════════════════════════════════════════════ */

/* ── bar frame (solid/glass per --sps-header-bg, accent top slash) ── */
body.sps-root .dash-header {
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 60px;
    background: var(--sps-header-bg, var(--sps-surface, #fff));
    border-bottom: 1px solid var(--sps-border, #e5e7eb);
    box-shadow: 0 1px 3px rgba(18, 24, 42, .05), 0 10px 30px -18px rgba(18, 24, 42, .22);
    z-index: 1025;
    display: flex;
    align-items: center;
    padding: 0 20px;
    color: var(--sps-text, #212529);
    backdrop-filter: blur(16px) saturate(1.3);
    -webkit-backdrop-filter: blur(16px) saturate(1.3);
}
body.sps-bg-dark .dash-header,
body.sps-bg-dark .dash-header.transprent-bg {
    background: var(--sps-header-bg, var(--sps-surface, #12121f)) !important;
    color: var(--sps-text);
}
body.sps-root .dash-header::before {
    content: "";
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--sps-accent, #6FD943), var(--sps-accent-hover, #5bbf3a) 60%, transparent);
    opacity: .95;
    pointer-events: none;
}
body.sps-root .dash-header .header-wrapper {
    position: relative;
    display: flex;
    align-items: center;
    width: 100%;
    gap: 12px;
    height: 100%;
}

/* ── zones ──────────────────────────────────────────────────────── */
.header-zone { display: flex; align-items: center; gap: 8px; }
.header-left  { flex: 0 0 auto; }
.header-search { flex: 0 0 auto; position: relative; }
.header-right { flex: 1 1 auto; justify-content: flex-end; }

/* ── brand ──────────────────────────────────────────────────────── */
.header-brand .dash-head-link,
.brand-link {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    padding: 4px 6px;
    border-radius: 10px;
    text-decoration: none;
}
.brand-chip {
    width: 30px; height: 30px;
    border-radius: 9px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    font-weight: 800;
    color: var(--sps-btn-text, #fff);
    background: linear-gradient(141.55deg, var(--sps-accent, #6FD943), var(--sps-accent-hover, #5bbf3a));
    box-shadow: 0 4px 10px -3px rgba(var(--sps-accent-rgb, 111, 217, 67), .55);
    flex-shrink: 0;
    letter-spacing: 0;
}
.brand-text {
    font-weight: 800;
    font-size: 15px;
    letter-spacing: .25px;
    color: var(--sps-heading, var(--sps-text, #212529));
    white-space: nowrap;
}

/* ── shared head-link ───────────────────────────────────────────── */
.dash-head-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
    color: var(--sps-header-text, var(--sps-text, #212529));
    font-weight: 500;
    cursor: pointer;
    border-radius: 10px;
    transition: background .15s;
}
.dash-head-link:hover { background: var(--sps-surface2, rgba(0,0,0,.04)); }
.dash-head-link i { color: var(--sps-header-text, var(--sps-text, #212529)); }
.dash-head-link .nocolor { color: inherit; }
.dash-head-link .hide-mob { display: inline; }
.dash-head-link .drp-arrow { font-size: 13px; }
.dash-head-link .me-0 { margin-right: 0; }
.dash-head-link .ms-2 { margin-left: .5rem; }
.dash-head-link .rounded-circle { border-radius: 50%; }
.dash-head-link .img-fluid { max-width: 100%; }

/* ── compact icon chips ─────────────────────────────────────────── */
.fullscreen-btn,
.quick-action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px; height: 36px;
    border-radius: 11px;
    border: 1px solid var(--sps-border, #e5e7eb);
    background: var(--sps-surface, #fff);
    color: var(--sps-header-text, var(--sps-text, #212529));
    cursor: pointer;
    transition: background .15s, border-color .15s, transform .15s, box-shadow .15s;
}
.fullscreen-btn:hover,
.quick-action-btn:hover {
    background: var(--sps-surface2, rgba(0,0,0,.04));
    border-color: var(--sps-border2, #d1d5db);
    transform: translateY(-1px);
}
.fullscreen-btn i,
.quick-action-btn i { font-size: 18px; }
.quick-action-btn {
    width: auto;
    padding: 0 14px;
    gap: 6px;
    border: none;
    color: var(--sps-btn-text, #fff);
    background: linear-gradient(141.55deg, var(--sps-accent, #6FD943), var(--sps-accent-hover, #5bbf3a));
    font-size: 13px;
    font-weight: 700;
    box-shadow: 0 6px 16px -6px rgba(var(--sps-accent-rgb, 111, 217, 67), .6);
}
.quick-action-btn i { font-size: 16px; }
.quick-action-btn:hover {
    background: linear-gradient(141.55deg, var(--sps-accent-hover, #5bbf3a), var(--sps-accent, #6FD943));
    border: none;
    color: var(--sps-btn-text, #fff);
}
.quick-action-btn.active {
    background: var(--sps-accent-hover, #5bbf3a);
    color: var(--sps-btn-text, #fff);
}
#quickActionBtn.active .ti-plus { transform: rotate(90deg); transition: transform .2s; }

/* ── pill containers (renewals / notifications) ─────────────────── */
.renewal-container,
.bell-container {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 999px;
    border: 1px solid var(--sps-border, #e5e7eb);
    background: var(--sps-surface, #fff);
    box-shadow: 0 1px 2px rgba(18,24,42,.04);
    cursor: pointer;
    transition: border-color .15s, box-shadow .15s, transform .15s;
    white-space: nowrap;
}
.renewal-container:hover,
.bell-container:hover {
    border-color: var(--sps-border2, #d1d5db);
    box-shadow: 0 4px 14px -6px rgba(18,24,42,.18);
    transform: translateY(-1px);
}
.renewal-container .pill-label,
.bell-container .pill-label {
    font-size: 11px;
    font-weight: 700;
    color: var(--sps-muted-text, #6c757d);
    letter-spacing: .2px;
}
.renewal-container .pill,
.bell-container .pill {
    display: inline-flex;
    color: var(--sps-header-text, var(--sps-text, #212529));
}
.renewal-container:has(.notification-badge),
.bell-container:has(.notification-badge) {
    border-color: var(--sps-accent, #6FD943);
}
.renewal-container .notification-badge,
.bell-container .notification-badge {
    position: absolute;
    top: -5px; right: -3px;
    min-width: 17px; height: 17px;
    border-radius: 999px;
    background: #e5484d;
    color: #fff;
    font-size: 10px;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 4px;
    line-height: 1;
    box-shadow: 0 0 0 2px var(--sps-surface, #fff);
}

/* ── search capsule ─────────────────────────────────────────────── */
.search-form { width: 100%; }
.search-wrapper {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 4px 6px 4px 8px;
    border-radius: 12px;
    background: var(--sps-surface2, rgba(0,0,0,.04));
    border: 1px solid var(--sps-border, #e5e7eb);
    box-shadow: inset 0 1px 2px rgba(18,24,42,.03);
    transition: border-color .2s, box-shadow .2s, background .2s;
    min-width: 330px;
}
.search-wrapper:focus-within {
    border-color: var(--sps-accent, #6FD943);
    background: var(--sps-surface, #fff);
    box-shadow: 0 0 0 3px rgba(var(--sps-accent-rgb, 111, 217, 67), .14);
}

/* search-by dropdown */
.search-by-filter { position: relative; }
.search-by-dropdown { position: relative; display: inline-flex; }
.search-by-selected {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 5px 9px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    color: var(--sps-text, #212529);
    cursor: pointer;
    background: var(--sps-surface, #fff);
    border: 1px solid var(--sps-border, #e5e7eb);
    transition: background .15s, border-color .15s;
    white-space: nowrap;
}
.search-by-selected:hover { border-color: var(--sps-border2, #d1d5db); }
.search-by-selected.active { border-color: var(--sps-accent, #6FD943); background: var(--sps-soft, #eef9e8); }
.search-by-selected .search-icon { font-size: 13px; color: var(--sps-accent, #6FD943); }
.search-by-selected.active .search-icon { color: var(--sps-accent, #6FD943); }
.search-by-selected .dropdown-arrow { font-size: 11px; margin-left: 2px; color: var(--sps-muted-text, #6c757d); }
.search-by-options {
    display: none;
    position: absolute;
    top: calc(100% + 6px);
    left: 0;
    min-width: 190px;
    background: var(--sps-surface, #fff);
    border: 1px solid var(--sps-border, #e5e7eb);
    border-radius: 12px;
    box-shadow: var(--sps-shadow, 0 8px 24px rgba(0,0,0,.12));
    z-index: 1050;
    padding: 5px;
    flex-direction: column;
    gap: 2px;
    max-height: 320px;
    overflow-y: auto;
}
.search-by-options.show { display: flex; }
.search-option {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 7px 10px;
    border-radius: 8px;
    font-size: 13px;
    color: var(--sps-text, #212529);
    cursor: pointer;
    transition: background .12s;
}
.search-option:hover { background: var(--sps-surface2, rgba(0,0,0,.04)); }
.search-option.selected { background: var(--sps-soft, #eef9e8); color: var(--sps-accent, #6FD943); font-weight: 600; }
.search-option .option-icon { font-size: 14px; opacity: .7; }
.search-option.selected .option-icon { opacity: 1; }

/* status filter toggle */
.status-filter {
    display: inline-flex;
    border-radius: 9px;
    overflow: hidden;
    border: 1px solid var(--sps-border, #e5e7eb);
    background: var(--sps-surface, #fff);
    flex-shrink: 0;
}
.status-option { display: flex; align-items: center; }
.status-option input[type="radio"] { position: absolute; opacity: 0; width: 0; height: 0; }
.status-option label {
    padding: 5px 11px;
    font-size: 11px;
    font-weight: 700;
    color: var(--sps-muted-text, #6c757d);
    cursor: pointer;
    transition: background .15s, color .15s;
    user-select: none;
}
.status-option input:checked + label {
    background: var(--sps-accent, #6FD943);
    color: var(--sps-btn-text, #fff);
}
.status-option:first-child label { border-radius: 8px 0 0 8px; }
.status-option:last-child label  { border-radius: 0 8px 8px 0; }

/* search input + button */
.search-input-group { display: flex; align-items: center; flex: 1; min-width: 0; }
.search-input {
    border: none;
    outline: none;
    background: transparent;
    color: var(--sps-text, #212529);
    font-size: 13px;
    flex: 1;
    padding: 7px 8px;
    min-width: 60px;
    font-family: inherit;
}
.search-input::placeholder { color: var(--sps-muted-text, #6c757d); }
.search-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px; height: 32px;
    border: none;
    border-radius: 9px;
    background: linear-gradient(135deg, var(--sps-accent, #6FD943), var(--sps-accent-hover, #5bbf3a));
    color: var(--sps-btn-text, #fff);
    cursor: pointer;
    flex-shrink: 0;
    transition: transform .15s, box-shadow .15s;
}
.search-button:hover { transform: scale(1.07); box-shadow: 0 4px 12px rgba(var(--sps-accent-rgb, 111, 217, 67), .35); }
.search-button i { font-size: 14px; }

/* search results dropdown (anchored under the capsule) */
.search-results-dropdown {
    display: none;
    position: absolute;
    top: calc(100% + 8px);
    left: 0; right: 0;
    background: var(--sps-surface, #fff);
    border: 1px solid var(--sps-border, #e5e7eb);
    border-radius: 12px;
    box-shadow: var(--sps-shadow, 0 8px 24px rgba(0,0,0,.12));
    max-height: 360px;
    overflow-y: auto;
    z-index: 1050;
    padding: 6px;
}
.search-results-dropdown a.search-result-link {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    border-radius: 8px;
    text-decoration: none;
    color: var(--sps-text, #212529);
    transition: background .12s;
}
.search-results-dropdown a.search-result-link:hover { background: var(--sps-surface2, rgba(0,0,0,.04)); }
.inactive-clients-divider {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    font-size: 11px;
    color: var(--sps-muted-text, #6c757d);
}
.inactive-clients-divider .ic-dot { width: 6px; height: 6px; border-radius: 50%; background: #e5484d; }

/* ── dropdown cards (anchored near their trigger icons) ─────────── */
.renewals-dropdown,
.activities-dropdown,
.quick-action-dropdown {
    display: none;
    position: absolute;
    top: calc(100% + 8px);
    background: var(--sps-surface, #fff);
    border: 1px solid var(--sps-border, #e5e7eb);
    border-radius: 14px;
    box-shadow: var(--sps-shadow, 0 10px 30px rgba(0,0,0,.14));
    z-index: 1050;
    min-width: 310px;
    max-width: 380px;
    max-height: 380px;
    overflow-y: auto;
    padding: 8px;
}
.renewals-dropdown { left: auto; right: 16px; }
.activities-dropdown { left: auto; right: 16px; }
.quick-action-dropdown { left: auto; right: 16px; }
.renewals-dropdown::before,
.activities-dropdown::before,
.quick-action-dropdown::before {
    content: "";
    position: absolute;
    top: -6px; right: 26px;
    width: 12px; height: 12px;
    background: var(--sps-surface, #fff);
    transform: rotate(45deg);
    border-left: 1px solid var(--sps-border, #e5e7eb);
    border-top: 1px solid var(--sps-border, #e5e7eb);
    border-radius: 2px 0 0 0;
}
.renewals-dropdown.show,
.activities-dropdown.show,
.quick-action-dropdown.show { display: block; }

/* dropdown headers */
.renewals-header,
.activities-header,
.qa-header,
.qa-subheader {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 4px 10px;
    font-size: 13px;
    font-weight: 800;
    color: var(--sps-heading, var(--sps-text, #212529));
    border-bottom: 1px solid var(--sps-border, #e5e7eb);
    margin-bottom: 4px;
}
.renewals-header h6,
.activities-header h6 { margin: 0; font-size: 13px; font-weight: 800; color: var(--sps-text, #212529); }
.view-all-link {
    font-size: 11px;
    color: var(--sps-accent, #6FD943);
    text-decoration: none;
    font-weight: 700;
}
.view-all-link:hover { text-decoration: underline; }

/* dropdown bodies */
.renewals-body,
.activities-body,
.qa-body { display: flex; flex-direction: column; gap: 2px; }

/* renewal item */
.renewal-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 8px;
    border-radius: 10px;
    transition: background .12s;
}
.renewal-item:hover { background: var(--sps-surface2, rgba(0,0,0,.03)); }
.renewal-icon {
    width: 34px; height: 34px;
    border-radius: 10px;
    background: var(--sps-surface2, rgba(0,0,0,.04));
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.renewal-icon i { font-size: 16px; }
.renewal-content { flex: 1; min-width: 0; }
.renewal-title { font-size: 13px; font-weight: 600; line-height: 1.3; }
.renewal-title a { color: inherit; text-decoration: none; }
.renewal-title a:hover { text-decoration: underline; }
.renewal-meta { font-size: 11px; color: var(--sps-muted-text, #6c757d); margin-top: 2px; }
.renewal-user, .renewal-time { display: block; }
.renewal-time { margin-top: 2px; }

/* activity item */
.activity-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 8px;
    border-radius: 10px;
    transition: background .12s;
}
.activity-item:hover { background: var(--sps-surface2, rgba(0,0,0,.03)); }
.activity-icon {
    width: 34px; height: 34px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    color: #fff;
    font-size: 15px;
}
.activity-icon i { font-size: 15px; }
.activity-content { flex: 1; min-width: 0; }
.activity-title { font-size: 13px; line-height: 1.3; }
.activity-title-delete { color: #e5484d; }
.activity-meta { font-size: 11px; color: var(--sps-muted-text, #6c757d); margin-top: 2px; }
.activity-user { font-weight: 600; }
.activity-time { margin-left: 6px; }
.activity-inactive-dot { display: inline-block; width: 7px; height: 7px; border-radius: 50%; background: #e5484d; margin-left: 4px; vertical-align: middle; }
.inactive-client-badge {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    font-size: 10px;
    color: #e5484d;
    font-weight: 600;
    margin-left: 4px;
}
.ic-dot { display: inline-block; width: 5px; height: 5px; border-radius: 50%; background: #e5484d; }

/* badges */
.badge-urgent   { background: #e5484d; color: #fff; font-size: 10px; padding: 1px 6px; border-radius: 5px; font-weight: 700; }
.badge-pending  { background: #f5a623; color: #fff; font-size: 10px; padding: 1px 6px; border-radius: 5px; font-weight: 700; }
.badge-renewed  { background: #30a46c; color: #fff; font-size: 10px; padding: 1px 6px; border-radius: 5px; font-weight: 700; }

/* quick action */
.quick-action-dropdown .qa-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 10px;
    border-radius: 10px;
    cursor: pointer;
    text-decoration: none;
    color: var(--sps-text, #212529);
    transition: background .12s;
}
.quick-action-dropdown .qa-item:hover { background: var(--sps-surface2, rgba(0,0,0,.04)); }
.qa-icon, .qa-icon-plain {
    width: 32px; height: 32px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.qa-icon i, .qa-icon-plain i { font-size: 16px; color: #fff; }
.qa-tint-blue   { background: #3b82f6; }
.qa-tint-green  { background: #22b573; }
.qa-tint-orange { background: #f97316; }
.qa-tint-purple { background: #8b5cf6; }
.qa-tint-gray   { background: #6b7280; }
.qa-tint-red    { background: #e5484d; }
.qa-c-blue, .qa-icon-plain.qa-c-blue  { color: #3b82f6 !important; }
.qa-c-green, .qa-icon-plain.qa-c-green { color: #22b573 !important; }
.qa-c-orange, .qa-icon-plain.qa-c-orange { color: #f97316 !important; }
.qa-c-purple, .qa-icon-plain.qa-c-purple { color: #8b5cf6 !important; }
.qa-c-pink, .qa-icon-plain.qa-c-pink { color: #d4118c !important; }
.qa-c-red, .qa-icon-plain.qa-c-red { color: #e5484d !important; }
.qa-c-teal, .qa-icon-plain.qa-c-teal { color: #20c997 !important; }
.qa-c-yellow, .qa-icon-plain.qa-c-yellow { color: #f5a623 !important; }
.qa-c-stripe, .qa-icon-plain.qa-c-stripe { color: #635aff !important; }
.qa-stripe-text { font-weight: 700 !important; letter-spacing: .3px; }
.qa-label { font-size: 13px; font-weight: 500; }
.qa-divider { height: 1px; background: var(--sps-border, #e5e7eb); margin: 4px 0; }
.qa-more { cursor: pointer; }
.qa-more-arrow { margin-left: auto; font-size: 14px; color: var(--sps-muted-text, #6c757d); }
.qa-more-panel {
    display: none;
    flex-direction: column;
    gap: 2px;
    padding-top: 4px;
    border-top: 1px solid var(--sps-border, #e5e7eb);
    margin-top: 4px;
}
.qa-more-panel.show { display: flex; }
.qa-subheader { font-size: 11px; text-transform: uppercase; letter-spacing: .5px; color: var(--sps-muted-text, #6c757d); font-weight: 700; }

/* ── profile dropdown ───────────────────────────────────────────── */
.drp-company .dropdown-menu.dash-h-dropdown {
    min-width: 210px;
    border-radius: 12px;
    box-shadow: var(--sps-shadow, 0 10px 30px rgba(0,0,0,.14));
    padding: 6px;
    background: var(--sps-surface, #fff);
    border: 1px solid var(--sps-border, #e5e7eb);
    margin-top: 6px;
}
.drp-company .dropdown-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    border-radius: 8px;
    font-size: 13px;
    color: var(--sps-text, #212529);
    text-decoration: none;
    transition: background .12s;
}
.drp-company .dropdown-item:hover { background: var(--sps-surface2, rgba(0,0,0,.04)); }
.drp-company .dropdown-item i { font-size: 16px; color: var(--sps-text, #212529); }
.drp-company .dropdown-item .text-dark { color: var(--sps-text, #212529) !important; }
.theme-avtar img { object-fit: cover; width: 34px; height: 34px; border-radius: 50%; box-shadow: 0 2px 8px rgba(18,24,42,.18); }

/* list helper */
.list-unstyled { list-style: none; margin: 0; padding: 0; }
.list-unstyled li { display: inline-flex; }
.me-auto { margin-right: auto !important; }
.ms-auto { margin-left: auto !important; }
.hide-mob { display: inline; }

/* fullscreen btn state */
#fullscreenToggle { outline: none; }
#fullscreenToggle:focus-visible { box-shadow: 0 0 0 2px var(--sps-accent, #6FD943); }

/* ── swal2 quick-client picker ──────────────────────────────────── */
.qa-client-search-input { width: 100% !important; }
.qa-client-results { max-height: 260px; overflow-y: auto; }
.qa-client-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px;
    border-radius: 8px;
    cursor: pointer;
    transition: background .12s;
}
.qa-client-row:hover { background: var(--sps-surface2, rgba(0,0,0,.04)); }
.qa-client-row-bus { font-size: 12px; color: var(--sps-muted-text, #6c757d); }
.qa-client-empty { padding: 14px; text-align: center; font-size: 13px; color: var(--sps-muted-text, #6c757d); }
.swal2-html-container .qa-client-search-input { margin-bottom: 10px; }

/* ── responsive ─────────────────────────────────────────────────── */
@media (max-width: 1200px) {
    .header-search { flex: 0 0 270px; }
    .search-wrapper { min-width: 250px; }
    .search-by-selected span { max-width: 86px; overflow: hidden; text-overflow: ellipsis; }
}
@media (max-width: 1024px) {
    .hide-mob { display: none !important; }
    .header-search { display: none !important; }
    .dash-header .header-wrapper { gap: 6px; }
    .dash-header { padding: 0 12px; }
}
@media (max-width: 900px) {
    .header-right .pill-label { display: none; }
    .renewal-container, .bell-container { padding: 6px 9px; }
    .renewals-dropdown, .activities-dropdown, .quick-action-dropdown { right: 8px; }
    .brand-text { font-size: 13px; }
}
</style>


<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js  "></script>
<script>
    $(document).ready(function () {
        // Initialize tooltips
        $('[data-toggle="tooltip"]').tooltip();

        // Enhanced Search By Dropdown Functionality
        const searchBySelected = $('#searchBySelected');
        const searchByOptions = $('#searchByOptions');
        const searchByLabel = $('#searchByLabel');
        const searchByInput = $('#searchBy');

        // Toggle dropdown
        searchBySelected.on('click', function (e) {
            e.stopPropagation();
            const isOpen = searchByOptions.hasClass('show');

            if (isOpen) {
                searchByOptions.removeClass('show');
                searchBySelected.removeClass('active');
            } else {
                searchByOptions.addClass('show');
                searchBySelected.addClass('active');
            }

            // Close other dropdowns
            $('#searchResults').hide();
            $('#activitiesDropdown').hide();
            $('#renewalsDropdown').hide();
            $('#quickActionDropdown').hide();
            $('#quickActionBtn').removeClass('active');
            $('#qaMorePanel').removeClass('show');
            $('#qaMoreToggle').removeClass('active');
        });

        // Handle option selection
        $('.search-option').on('click', function (e) {
            e.stopPropagation();

            // Remove previous selection
            $('.search-option').removeClass('selected');

            // Add selection to clicked option
            $(this).addClass('selected');

            // Update hidden input and label
            const value = $(this).data('value');
            const label = $(this).find('span').text();

            searchByInput.val(value);
            searchByLabel.text('Search By: ' + label);

            // Close dropdown
            searchByOptions.removeClass('show');
            searchBySelected.removeClass('active');

            // Trigger search if there's text in the input
            const searchValue = $('#searchInput').val();
            if (searchValue.length > 0) {
                $('#searchInput').trigger('input');
            }
        });

        // Close dropdown when clicking outside
        $(document).on('click', function (e) {
            if (!$(e.target).closest('.search-by-dropdown').length) {
                searchByOptions.removeClass('show');
                searchBySelected.removeClass('active');
            }
        });

        let typingTimer;
        const typingDelay = 500;

        // When viewing Active results, also surface matching Inactive clients at
        // the very end — so a client that was deactivated still turns up in search
        // instead of looking like "not found". Only the client rows are pulled from
        // the inactive response (invoices are skipped to avoid duplicates).
        function appendInactiveClients(query, searchBy) {
            $.ajax({
                url: "search_clients.php",
                type: "POST",
                data: { search: query, profile_status: 0, search_by: searchBy },
                success: function (data) {
                    // Ignore a stale response if the user has since changed the query
                    if ($("#searchInput").val().trim() !== query.trim()) return;

                    let $inactive = $("<div>").html(data).find("a.search-result-link");
                    if (!$inactive.length) return;

                    let $results = $("#searchResults");
                    $results.append(
                        '<div class="inactive-clients-divider">' +
                        '<span class="ic-dot"></span> Inactive Clients (' + $inactive.length + ')' +
                        '</div>'
                    );
                    $results.append($inactive);
                    $results.show();
                }
            });
        }

        // Live search functionality
        $("#searchInput").on("input", function () {
            clearTimeout(typingTimer);
            let query = $(this).val();
            let profileStatus = $("input[name='profile_status']:checked").val();
            let searchBy = searchByInput.val();

            typingTimer = setTimeout(function () {
                $.ajax({
                    url: "search_clients.php",
                    type: "POST",
                    data: {
                        search: query,
                        profile_status: profileStatus,
                        search_by: searchBy
                    },
                    success: function (data) {
                        $("#searchResults").html(data).show();
                        // Active view (profile_status = 1) → append inactive matches at the end
                        if (String(profileStatus) === "1" && query.trim().length > 0) {
                            appendInactiveClients(query, searchBy);
                        }
                    }
                });
            }, typingDelay);
        });

        // Show recent searches on focus
        $("#searchInput").on("focus", function () {
            let profileStatus = $("input[name='profile_status']:checked").val();
            let searchBy = searchByInput.val();

            $.ajax({
                url: "search_clients.php",
                type: "POST",
                data: {
                    search: "",
                    profile_status: profileStatus,
                    search_by: searchBy
                },
                success: function (data) {
                    $("#searchResults").html(data).show();
                }
            });
        });

        // Close dropdowns when clicking outside
        $(document).on("click", function (e) {
            if (!$(e.target).closest("#searchInput, #searchResults, .search-wrapper").length) {
                $("#searchResults").hide();
            }

            // Hide activities dropdown when clicking outside
            if (!$(e.target).closest("#bellIcon, #activitiesDropdown").length) {
                $("#activitiesDropdown").hide();
            }

            // Hide renewals dropdown when clicking outside
            if (!$(e.target).closest("#renewalIcon, #renewalsDropdown").length) {
                $("#renewalsDropdown").hide();
            }

            // Hide quick action dropdown when clicking outside
            if (!$(e.target).closest("#quickActionBtn, #quickActionDropdown").length) {
                $("#quickActionDropdown").hide();
                $("#quickActionBtn").removeClass("active");
                $("#qaMorePanel").removeClass("show");
                $("#qaMoreToggle").removeClass("active");
            }
        });

        // Recent search click - open linked profile if one was recorded, otherwise search again
        $(document).on("click", ".recent-search", function (e) {
            e.preventDefault();
            let clickedId = $(this).data("clicked-id");
            let clickedStatus = $(this).data("clicked-status");
            let value = $(this).data("search");
            if (clickedId) {
                window.location.href = "http://192.168.15.29/client_details.php?id=" + clickedId + "&status=" + (clickedStatus || 1);
            } else {
                $("#searchInput").val(value).trigger("input");
            }
        });

        // Track when user opens a profile from search results so the keyword links to it
        $(document).on("click", ".search-result-link", function () {
            let memberId = $(this).data("member-id");
            let searchText = $(this).data("search-text");
            if (!memberId || !searchText) return;
            try {
                let fd = new FormData();
                fd.append("action", "track_click");
                fd.append("member_id", memberId);
                fd.append("search", searchText);
                if (navigator.sendBeacon) {
                    navigator.sendBeacon("search_clients.php", fd);
                } else {
                    $.ajax({
                        url: "search_clients.php",
                        type: "POST",
                        data: { action: "track_click", member_id: memberId, search: searchText },
                        async: false
                    });
                }
            } catch (err) { /* tracking failures must not block navigation */ }
        });

        // Toggle activities dropdown
        $("#bellIcon").on("click", function (e) {
            e.stopPropagation();
            $("#activitiesDropdown").toggle();
            $("#renewalsDropdown").hide();
            $("#searchResults").hide();
            $("#quickActionDropdown").hide();
            $("#quickActionBtn").removeClass("active");
            $("#qaMorePanel").removeClass("show");
            $("#qaMoreToggle").removeClass("active");
            searchByOptions.removeClass('show');
            searchBySelected.removeClass('active');
        });

        // Toggle renewals dropdown
        $("#renewalIcon").on("click", function (e) {
            e.stopPropagation();
            $("#renewalsDropdown").toggle();
            $("#activitiesDropdown").hide();
            $("#searchResults").hide();
            $("#quickActionDropdown").hide();
            $("#quickActionBtn").removeClass("active");
            $("#qaMorePanel").removeClass("show");
            $("#qaMoreToggle").removeClass("active");
            searchByOptions.removeClass('show');
            searchBySelected.removeClass('active');
        });

        // Toggle Quick Action dropdown
        $("#quickActionBtn").on("click", function (e) {
            e.stopPropagation();
            var $btn = $(this);
            var $dd = $("#quickActionDropdown");
            $dd.toggle();
            $btn.toggleClass("active", $dd.is(":visible"));
            if (!$dd.is(":visible")) {
                $("#qaMorePanel").removeClass("show");
                $("#qaMoreToggle").removeClass("active");
            }
            $("#activitiesDropdown").hide();
            $("#renewalsDropdown").hide();
            $("#searchResults").hide();
            searchByOptions.removeClass('show');
            searchBySelected.removeClass('active');
        });

        // Toggle Additional Actions panel
        $("#qaMoreToggle").on("click", function (e) {
            e.stopPropagation();
            $(this).toggleClass("active");
            $("#qaMorePanel").toggleClass("show");
        });

        // Close the whole Quick Action menu (used before a popup/picker takes over)
        function closeQuickActionMenu() {
            $("#quickActionDropdown").hide();
            $("#quickActionBtn").removeClass("active");
            $("#qaMorePanel").removeClass("show");
            $("#qaMoreToggle").removeClass("active");
        }

        function showQaPermissionDenied() {
            closeQuickActionMenu();
            Swal.fire({
                icon: "error",
                title: "Permission Denied",
                text: "You do not have permission to perform this action. Please contact your administrator.",
                confirmButtonColor: "#6fd943"
            });
        }

        // Any Quick Action item carrying data-allowed="0" is blocked client-side —
        // the link itself still points at the real page, so this is a UX guard,
        // not the enforcement boundary (each target page/handler owns that).
        $(document).on("click", ".qa-item[data-allowed]:not(#qaAddInvoiceBtn)", function (e) {
            if ($(this).data("allowed") == 0) {
                e.preventDefault();
                showQaPermissionDenied();
            }
        });

        // "Add Invoice" never navigates directly — pick a client first, then land
        // on that client's billing area (its Invoices tab is already the default).
        $(document).on("click keypress", "#qaAddInvoiceBtn", function (e) {
            if (e.type === "keypress" && e.which !== 13) return;
            if ($(this).data("allowed") == 0) {
                showQaPermissionDenied();
                return;
            }
            closeQuickActionMenu();
            qaOpenClientPicker(function (clientId) {
                return "client_billing.php?id=" + clientId;
            });
        });

        // Lightweight client search popup shared by any Quick Action that needs
        // "pick a client, then go to page X for that client". Reuses the same
        // search_clients.php endpoint the header search bar already calls.
        function qaOpenClientPicker(buildUrl) {
            Swal.fire({
                title: "Select a Client",
                html:
                    '<input type="text" id="qaClientSearch" class="swal2-input qa-client-search-input" ' +
                    'placeholder="Search by name, business, phone...">' +
                    '<div id="qaClientResults" class="qa-client-results"></div>',
                width: 440,
                showConfirmButton: false,
                showCloseButton: true,
                customClass: { popup: "qa-picker-popup" },
                didOpen: function () {
                    const $input = $("#qaClientSearch");
                    const $results = $("#qaClientResults");
                    let qaTimer;

                    function renderClients(html) {
                        const $links = $("<div>").html(html).find("a.search-result-link");
                        $results.empty();
                        if (!$links.length) {
                            $results.html('<div class="qa-client-empty">No clients found.</div>');
                            return;
                        }
                        $links.each(function () {
                            const id = $(this).data("member-id");
                            const name = $(this).find("strong").eq(0).text().trim();
                            const bus = $(this).find("strong").eq(1).text().trim();
                            const $row = $('<div class="qa-client-row"></div>')
                                .append($("<strong></strong>").text(name));
                            if (bus) {
                                $row.append($('<div class="qa-client-row-bus"></div>').text(bus));
                            }
                            $row.on("click", function () {
                                Swal.close();
                                window.location.href = buildUrl(id);
                            });
                            $results.append($row);
                        });
                    }

                    function runQaSearch(q) {
                        $.ajax({
                            url: "search_clients.php",
                            type: "POST",
                            data: { search: q, profile_status: 1, search_by: "all" },
                            success: renderClients
                        });
                    }

                    $input.on("input", function () {
                        clearTimeout(qaTimer);
                        const q = $(this).val();
                        if (q.trim().length < 2) {
                            $results.html('<div class="qa-client-empty">Type at least 2 characters to search.</div>');
                            return;
                        }
                        qaTimer = setTimeout(function () { runQaSearch(q); }, 350);
                    });

                    $results.html('<div class="qa-client-empty">Type at least 2 characters to search.</div>');
                    $input.trigger("focus");
                }
            });
        }

        // Fullscreen toggle
        $("#fullscreenToggle").on("click", function (e) {
            e.preventDefault();
            e.stopPropagation();
            const doc = document;
            const el = doc.documentElement;
            const isFs = doc.fullscreenElement || doc.webkitFullscreenElement || doc.msFullscreenElement;
            if (!isFs) {
                if (el.requestFullscreen) el.requestFullscreen();
                else if (el.webkitRequestFullscreen) el.webkitRequestFullscreen();
                else if (el.msRequestFullscreen) el.msRequestFullscreen();
            } else {
                if (doc.exitFullscreen) doc.exitFullscreen();
                else if (doc.webkitExitFullscreen) doc.webkitExitFullscreen();
                else if (doc.msExitFullscreen) doc.msExitFullscreen();
            }
        });

        // Swap icon + tooltip on fullscreen state change
        function updateFullscreenIcon() {
            const isFs = document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement;
            const icon = $("#fullscreenIcon");
            const btn = $("#fullscreenToggle");
            if (isFs) {
                icon.removeClass('ti-maximize').addClass('ti-minimize');
                btn.attr('title', 'Exit Fullscreen');
            } else {
                icon.removeClass('ti-minimize').addClass('ti-maximize');
                btn.attr('title', 'Toggle Fullscreen (F11)');
            }
        }
        document.addEventListener('fullscreenchange', updateFullscreenIcon);
        document.addEventListener('webkitfullscreenchange', updateFullscreenIcon);
        document.addEventListener('msfullscreenchange', updateFullscreenIcon);
    });
</script>

<?php
// Helper function to format time elapsed
function time_elapsed_string($datetime, $full = false)
{
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    // Calculate total days
    $total_days = (int) $now->diff($ago)->days;

    $weeks = floor($total_days / 7);
    $days = $total_days % 7;

    $units = [
        'y' => $diff->y,
        'm' => $diff->m,
        'w' => $weeks,
        'd' => $days,
        'h' => $diff->h,
        'i' => $diff->i,
        's' => $diff->s,
    ];

    $string = [];
    foreach ($units as $k => $v) {
        if ($v) {
            $label = [
                'y' => 'year',
                'm' => 'month',
                'w' => 'week',
                'd' => 'day',
                'h' => 'hour',
                'i' => 'minute',
                's' => 'second'
            ][$k];
            $string[] = $v . ' ' . $label . ($v > 1 ? 's' : '');
        }
    }

    return $string ? implode(', ', array_slice($string, 0, $full ? null : 1)) . ' ago' : 'just now';
}
?>
