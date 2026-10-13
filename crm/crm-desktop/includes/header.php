<?php
include 'includes/db.php';

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
        <div class="me-auto dash-mob-drp">
            <ul class="list-unstyled">
                <li class="dash-h-item mob-hamburger">
                    <a href="#!" class="dash-head-link" id="mobile-collapse">
                        <div class="hamburger hamburger--arrowturn">
                            <div class="hamburger-box">
                                <div class="hamburger-inner"></div>
                            </div>
                        </div>
                    </a>
                </li>
                <li class="dropdown dash-h-item drp-company search-wrapper">
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
                            <i class="ti ti-user text-dark"></i>
                            <span>Profile</span>
                        </a>

                        <a href="staff_details.php" class="dropdown-item">
                            <i class="ti ti-id-badge"></i>
                            <span>Staff Details</span>
                        </a>

                        <a href="user_profile.php" class="dropdown-item">
                            <i class="ti ti-activity"></i>
                            <span>Workflow</span>
                        </a>
                        <!-- To send -->
                        <a href="quickphrases.php" class="dropdown-item">
                            <i class="ti ti-message"></i>
                            <span>Quick Phrases</span>
                        </a>

                        <a href="logout.php"
                            onclick="event.preventDefault(); document.getElementById('frm-logout').submit();"
                            class="dropdown-item">
                            <i class="ti ti-power"></i>
                            <span>Log out</span>
                        </a>

                        <form id="frm-logout" action="logout.php" method="POST" class="d-none"></form>
                    </div>

                </li>
            </ul>
        </div>

        <div class="ms-auto search-container">
            <ul class="list-unstyled">
                <li class="dropdown dash-h-item drp-notification">
                    <form id="searchForm" action="view_clients.php" method="GET">
                        <div class="search-wrapper">
                            <!-- QUICK ACTION BUTTON -->
                            <div class="quick-action-btn" id="quickActionBtn" title="Quick Action">
                                <i class="ti ti-plus"></i>
                            </div>

                            <!-- FULLSCREEN TOGGLE -->
                            <div class="fullscreen-btn" id="fullscreenToggle" title="Toggle Fullscreen (F11)">
                                <i class="ti ti-maximize" id="fullscreenIcon"></i>
                            </div>

                            <!-- RENEWALS BUTTON - WITH PROFESSIONAL COLOR -->
                            <div class="renewal-container" id="renewalIcon"
                                style="width:auto; padding:6px 14px; background-color:#5c6bc0; border-radius:20px; display:flex; align-items:center; justify-content:center; margin-right:10px; cursor:pointer; position:relative; color:#fff; font-size:14px; font-weight:500; z-index:1001;">
                                <span style="margin-right:6px;">Renewals</span>
                                <span
                                    style="display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; background:#fff; border-radius:50%; margin-left:4px;">
                                    <i class="ti ti-calendar-time" style="color:#5c6bc0; font-size:14px;"></i>
                                </span>
                                <?php if ($renewal_count > 0): ?>
                                    <span
                                        class="notification-badge"><?php echo $renewal_count > 9 ? '9+' : $renewal_count; ?></span>
                                <?php endif; ?>
                            </div>

                            <!-- NOTIFICATION BUTTON - FOR REGULAR ACTIVITIES -->
                            <div class="bell-container" id="bellIcon"
                                style="width:auto; padding:6px 14px; background-color:#6fd943; border-radius:20px; display:flex; align-items:center; justify-content:center; margin-right:10px; cursor:pointer; position:relative; color:#fff; font-size:14px; font-weight:500; z-index:1001;">
                                <span style="margin-right:6px;">Notifications</span>
                                <span
                                    style="display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; background:#fff; border-radius:50%; margin-left:4px;">
                                    <i class="ti ti-bell" style="color:#6fd943; font-size:14px;"></i>
                                </span>
                                <?php if ($unread_count > 0): ?>
                                    <span
                                        class="notification-badge"><?php echo $unread_count > 9 ? '9+' : $unread_count; ?></span>
                                <?php endif; ?>
                            </div>

                            <!-- Status Filter -->
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

                            <!-- ENHANCED SEARCH BY DROPDOWN -->
                            <div class="search-by-filter">
                                <div class="search-by-dropdown">
                                    <div class="search-by-selected" id="searchBySelected">
                                        <i class="ti ti-search search-icon"></i>
                                        <span id="searchByLabel">Search By: All</span>
                                        <i class="ti ti-chevron-down dropdown-arrow"></i>
                                    </div>
                                    <div class="search-by-options" id="searchByOptions">
                                        <div class="search-option" data-value="all">
                                            <i class="ti ti-apps option-icon"></i>
                                            <span>All Fields</span>
                                        </div>
                                        <div class="search-option" data-value="member_id">
                                            <i class="ti ti-id option-icon"></i>
                                            <span>Member ID</span>
                                        </div>
                                        <div class="search-option" data-value="name">
                                            <i class="ti ti-user option-icon"></i>
                                            <span>Name</span>
                                        </div>
                                        <div class="search-option" data-value="phone">
                                            <i class="ti ti-phone option-icon"></i>
                                            <span>Phone</span>
                                        </div>
                                        <div class="search-option" data-value="email">
                                            <i class="ti ti-mail option-icon"></i>
                                            <span>Email</span>
                                        </div>
                                        <div class="search-option" data-value="business">
                                            <i class="ti ti-building option-icon"></i>
                                            <span>Business</span>
                                        </div>
                                        <div class="search-option" data-value="ein">
                                            <i class="ti ti-file-invoice option-icon"></i>
                                            <span>EIN</span>
                                        </div>
                                        <div class="search-option" data-value="ssn">
                                            <i class="ti ti-id-badge option-icon"></i>
                                            <span>SSN</span>
                                        </div>
                                        <div class="search-option" data-value="pmb">
                                            <i class="ti ti-mailbox option-icon"></i>
                                            <span>PMB</span>
                                        </div>
                                        <div class="search-option" data-value="invoice">
                                            <i class="ti ti-file-invoice option-icon"></i>
                                            <span>Invoice</span>
                                        </div>
                                        <div class="search-option" data-value="instant_invoice">
                                            <i class="ti ti-receipt option-icon"></i>
                                            <span>Instant Invoice</span>
                                        </div>
                                    </div>
                                    <input type="hidden" id="searchBy" name="search_by" value="all">
                                </div>
                            </div>

                            <!-- Search Input -->
                            <div class="search-input-group">
                                <input type="text" class="form-control search-input" placeholder="Search Clients / Instant Invoices..."
                                    name="search" id="searchInput" data-toggle="tooltip" data-placement="top">
                                <button class="search-button" type="submit">
                                    <i class="fa fa-search"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Search Results -->
                        <div id="searchResults" class="search-results-dropdown">
                        </div>

                        <!-- Renewals Dropdown -->
                        <div id="renewalsDropdown" class="renewals-dropdown" style="display: none; z-index: 1000;">
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

                                                // Set color based on urgency
                                                switch ($renewal['status_class']) {
                                                    case 'badge-urgent':
                                                        $color = '#d32f2f';
                                                        break;
                                                    case 'badge-pending':
                                                        $color = '#ff8f00';
                                                        break;
                                                    case 'badge-renewed':
                                                        $color = '#388e3c';
                                                        break;
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
                                                    <span
                                                        class="badge <?php echo $renewal['status_class']; ?> ms-2"><?php echo htmlspecialchars($renewal['status']); ?></span>
                                                </div>
                                                <div class="renewal-meta">
                                                    <span class="renewal-user">Assigned to:
                                                        <?php echo !empty($renewal['assigned_to_name']) ?
                                                            htmlspecialchars($renewal['assigned_to_name']) :
                                                            '<span class="text-muted">Not assigned</span>'; ?>
                                                    </span>
                                                    <span class="renewal-time">
                                                        <?php echo $renewal['days_left']; ?> days left
                                                    </span>
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

                        <!-- Activities Dropdown - FOR REGULAR ACTIVITIES ONLY -->
                        <div id="activitiesDropdown" class="activities-dropdown" style="display: none; z-index: 1000;">
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

                                            if (strpos($type, 'orders') !== false || strpos($type, 'order') !== false) {
                                                $bgColor = '#6fd943';
                                            } elseif (strpos($type, 'member') !== false && strpos($type, 'spouse') === false) {
                                                $bgColor = '#007bff';
                                            } elseif (strpos($type, 'billing_invoice') !== false || strpos($type, 'invoice') !== false) {
                                                $bgColor = '#28a745';
                                            } elseif (strpos($type, 'comments') !== false || strpos($type, 'comment') !== false) {
                                                $bgColor = '#6f42c1';
                                            } elseif (strpos($type, 'quotation_note') !== false) {
                                                $bgColor = '#fd7e14';
                                            } elseif (strpos($type, 'quotation') !== false || strpos($type, 'quotations') !== false) {
                                                $bgColor = '#fd7e14';
                                            } elseif (strpos($type, 'spouse') !== false) {
                                                $bgColor = '#20c997';
                                            } elseif (strpos($type, 'client_field_notes') !== false) {
                                                $bgColor = '#17a2b8';
                                            }
                                            echo $bgColor;
                                            ?>;">
                                                <?php
                                                $icon = 'ti-activity';
                                                $type = strtolower($activity['activity_type']);

                                                if (strpos($type, 'orders') !== false || strpos($type, 'order') !== false) {
                                                    $icon = 'ti-shopping-cart';
                                                    if (strpos($type, 'update') !== false)
                                                        $icon = 'ti-edit';
                                                    elseif (strpos($type, 'export') !== false)
                                                        $icon = 'ti-send';
                                                    elseif (strpos($type, 'delete') !== false)
                                                        $icon = 'ti-trash';
                                                } elseif (strpos($type, 'member') !== false && strpos($type, 'spouse') === false) {
                                                    $icon = 'ti-user';
                                                    if (strpos($type, 'update') !== false)
                                                        $icon = 'ti-edit';
                                                } elseif (strpos($type, 'billing_invoice') !== false || strpos($type, 'invoice') !== false) {
                                                    $icon = 'ti-file-invoice';
                                                    if (strpos($type, 'update') !== false)
                                                        $icon = 'ti-edit';
                                                    elseif (strpos($type, 'export') !== false)
                                                        $icon = 'ti-send';
                                                    elseif (strpos($type, 'delete') !== false)
                                                        $icon = 'ti-trash';
                                                    elseif (strpos($type, 'pay') !== false)
                                                        $icon = 'ti-credit-card';
                                                } elseif (strpos($type, 'comments') !== false || strpos($type, 'comment') !== false) {
                                                    $icon = 'ti-message';
                                                    if (strpos($type, 'update') !== false)
                                                        $icon = 'ti-edit';
                                                } elseif (strpos($type, 'quotation_note') !== false) {
                                                    $icon = 'ti-note';
                                                    if (strpos($type, 'update') !== false)
                                                        $icon = 'ti-edit';
                                                    elseif (strpos($type, 'delete') !== false)
                                                        $icon = 'ti-trash';
                                                } elseif (strpos($type, 'quotation') !== false || strpos($type, 'quotations') !== false) {
                                                    $icon = 'ti-file-dollar';
                                                    if (strpos($type, 'update') !== false)
                                                        $icon = 'ti-edit';
                                                    elseif (strpos($type, 'delete') !== false)
                                                        $icon = 'ti-trash';
                                                } elseif (strpos($type, 'spouse') !== false) {
                                                    $icon = 'ti-users';
                                                } elseif (strpos($type, 'client_field_notes') !== false) {
                                                    $icon = 'ti-notes';
                                                    if (strpos($type, 'update') !== false)
                                                        $icon = 'ti-edit';
                                                } 
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
                                                        <span class="inactive-client-badge"
                                                            title="This client is currently inactive">
                                                            <span class="ic-dot"></span> Inactive
                                                        </span>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="activity-meta">
                                                    <?php if (!empty($activity['added_by'])): ?>
                                                        <span class="activity-user">by
                                                            <?php echo htmlspecialchars($activity['added_by']); ?></span>
                                                    <?php elseif (!empty($activity['created_by'])): ?>
                                                        <span class="activity-user">by
                                                            <?php echo htmlspecialchars($activity['created_by']); ?></span>
                                                    <?php elseif (!empty($activity['author'])): ?>
                                                        <span class="activity-user">by
                                                            <?php echo htmlspecialchars($activity['author']); ?></span>
                                                    <?php elseif (!empty($activity['employee'])): ?>
                                                        <span class="activity-user">by
                                                            <?php echo htmlspecialchars($activity['employee']); ?></span>
                                                    <?php elseif (!empty($activity['user_name'])): ?>
                                                        <span class="activity-user">by
                                                            <?php echo htmlspecialchars($activity['user_name']); ?></span>
                                                    <?php endif; ?>
                                                    <span class="activity-time">
                                                        <?php
                                                        $timestamp = $activity['timestamp'] ?? '';
                                                        echo $timestamp ? time_elapsed_string($timestamp) : 'Recently';
                                                        ?>
                                                    </span>
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

                        <!-- Quick Action Dropdown -->
                        <div id="quickActionDropdown" class="quick-action-dropdown" style="display: none;">
                            <div class="qa-header">Quick Action</div>
                            <div class="qa-body">
                                <div class="qa-item" id="qaAddInvoiceBtn" role="button" tabindex="0"
                                    data-allowed="<?php echo $qa_perm_billing ? 1 : 0; ?>">
                                    <span class="qa-icon qa-tint-blue"><i class="ti ti-file-text"></i></span>
                                    <span class="qa-label">Add Invoice</span>
                                </div>
                                <a href="add_client.php" class="qa-item"
                                    data-allowed="<?php echo $qa_perm_add_client ? 1 : 0; ?>">
                                    <span class="qa-icon qa-tint-green"><i class="ti ti-user-plus"></i></span>
                                    <span class="qa-label">Add Client</span>
                                </a>
                                <a href="add_new_projects.php" class="qa-item">
                                    <span class="qa-icon qa-tint-orange"><i class="ti ti-folder"></i></span>
                                    <span class="qa-label">Add Project</span>
                                </a>
                                <a href="quotations.php" class="qa-item"
                                    data-allowed="<?php echo $qa_perm_quotation ? 1 : 0; ?>">
                                    <span class="qa-icon qa-tint-purple"><i class="ti ti-edit"></i></span>
                                    <span class="qa-label">Add Quotation</span>
                                </a>
                                <div class="qa-divider"></div>
                                <div class="qa-item qa-more" id="qaMoreToggle">
                                    <span class="qa-icon qa-tint-gray"><i class="ti ti-dots"></i></span>
                                    <span class="qa-label">More</span>
                                    <i class="ti ti-chevron-right qa-more-arrow"></i>
                                </div>
                            </div>

                            <!-- Additional Actions submenu -->
                            <div class="qa-more-panel" id="qaMorePanel">
                                <div class="qa-header qa-subheader">Additional Actions</div>
                                <div class="qa-body">
                                    <a href="users.php" class="qa-item"
                                        data-allowed="<?php echo $qa_perm_add_user ? 1 : 0; ?>">
                                        <span class="qa-icon-plain qa-c-blue"><i class="ti ti-user-plus"></i></span>
                                        <span class="qa-label">Add User</span>
                                    </a>
                                    <a href="payment_plan.php" class="qa-item">
                                        <span class="qa-icon-plain qa-c-green"><i class="ti ti-credit-card"></i></span>
                                        <span class="qa-label">Add Payment Plan</span>
                                    </a>
                                    <a href="instant_invoice.php" class="qa-item">
                                        <span class="qa-icon-plain qa-c-yellow"><i class="ti ti-bolt"></i></span>
                                        <span class="qa-label">Add Instant Invoice</span>
                                    </a>
                                    <a href="memberships.php" class="qa-item"
                                        data-allowed="<?php echo $qa_perm_add_service ? 1 : 0; ?>">
                                        <span class="qa-icon-plain qa-c-blue"><i class="ti ti-coins"></i></span>
                                        <span class="qa-label">Add Service</span>
                                    </a>
                                    <a href="client_types.php" class="qa-item">
                                        <span class="qa-icon-plain qa-c-pink"><i class="ti ti-users"></i></span>
                                        <span class="qa-label">Add Client Type</span>
                                    </a>
                                    <a href="reminder_calender.php" class="qa-item">
                                        <span class="qa-icon-plain qa-c-red"><i class="ti ti-bell"></i></span>
                                        <span class="qa-label">Add Reminder</span>
                                    </a>
                                    <a href="bulk_monthly_payer_gen.php" class="qa-item">
                                        <span class="qa-icon-plain qa-c-teal"><i class="ti ti-stack-2"></i></span>
                                        <span class="qa-label">Create Bulk Inv</span>
                                    </a>
                                    <a href="bulk_email.php" class="qa-item">
                                        <span class="qa-icon-plain qa-c-purple"><i class="ti ti-mail"></i></span>
                                        <span class="qa-label">Send Bulk Email</span>
                                    </a>
                                    <a href="comm_hub.php" class="qa-item">
                                        <span class="qa-icon-plain qa-c-green"><i class="ti ti-message-2"></i></span>
                                        <span class="qa-label">Send SMS</span>
                                    </a>
                                    <a href="stripe_pay_link_gen.php" class="qa-item">
                                        <span class="qa-icon-plain qa-c-stripe qa-stripe-text">stripe</span>
                                        <span class="qa-label">Stripe Link</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
<!-- [ Header ] end -->
<?php include __DIR__ . '/../quick_add.php'; // Quick Action: create Invoice / Estimate from the header ?>

<style>
    /* Search Container */
    .search-container {
        margin-top: 0 !important;
    }

    .search-wrapper {
        background: #fff;
        border-radius: 30px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        padding: 0.5rem 1rem;
        display: flex;
        align-items: center;
        transition: all 0.3s ease;
        border: 1px solid #e9ecef;
    }

    .search-wrapper:hover {
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.12);
    }

    /* Status Filter */
    .status-filter {
        display: flex;
        align-items: center;
        margin-right: 15px;
        padding-right: 15px;
        border-right: 1px solid #e9ecef;
    }

    .status-option {
        display: flex;
        align-items: center;
        margin-right: 15px;
    }

    .status-option:last-child {
        margin-right: 0;
    }

    .status-option input[type="radio"] {
        appearance: none;
        width: 18px;
        height: 18px;
        border: 2px solid #6fd943;
        border-radius: 50%;
        margin-right: 6px;
        position: relative;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .status-option input[type="radio"]:checked::after {
        content: '';
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #6fd943;
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
    }

    .status-option label {
        font-weight: 500;
        color: #495057;
        cursor: pointer;
        margin: 0;
        user-select: none;
    }

    /* ENHANCED SEARCH BY DROPDOWN STYLES */
    .search-by-filter {
        display: flex;
        align-items: center;
        margin-right: 15px;
        padding-right: 15px;
        border-right: 1px solid #e9ecef;
    }

    .search-by-dropdown {
        position: relative;
        min-width: 160px;
    }

    .search-by-selected {
        display: flex;
        align-items: center;
        padding: 8px 14px;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border: 2px solid #6fd943;
        border-radius: 25px;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        user-select: none;
        box-shadow: 0 2px 8px rgba(111, 217, 67, 0.15);
    }

    .search-by-selected:hover {
        background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(111, 217, 67, 0.25);
    }

    .search-by-selected.active {
        background: linear-gradient(135deg, #6fd943 0%, #5cb838 100%);
        border-color: #5cb838;
        box-shadow: 0 4px 16px rgba(111, 217, 67, 0.35);
    }

    .search-by-selected.active .search-icon,
    .search-by-selected.active #searchByLabel,
    .search-by-selected.active .dropdown-arrow {
        color: #fff;
    }

    .search-icon {
        font-size: 14px;
        color: #6fd943;
        margin-right: 8px;
        transition: all 0.3s ease;
    }

    #searchByLabel {
        font-size: 13px;
        font-weight: 600;
        color: #495057;
        flex: 1;
        transition: all 0.3s ease;
    }

    .dropdown-arrow {
        font-size: 12px;
        color: #6c757d;
        margin-left: 8px;
        transition: all 0.3s ease;
    }

    .search-by-selected.active .dropdown-arrow {
        transform: rotate(180deg);
    }

    .search-by-options {
        position: absolute;
        top: calc(100% + 8px);
        left: 0;
        right: 0;
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        border: 1px solid rgba(111, 217, 67, 0.2);
        max-height: 260px;
        overflow-y: auto;
        overflow-x: hidden;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        z-index: 1000;
    }

    .search-by-options::-webkit-scrollbar {
        width: 6px;
    }

    .search-by-options::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }

    .search-by-options::-webkit-scrollbar-thumb {
        background: #6fd943;
        border-radius: 10px;
    }

    .search-by-options::-webkit-scrollbar-thumb:hover {
        background: #5cb838;
    }

    .search-by-options.show {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .search-option {
        display: flex;
        align-items: center;
        padding: 12px 16px;
        cursor: pointer;
        transition: all 0.2s ease;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }

    .search-option:last-child {
        border-bottom: none;
    }

    .search-option:hover {
        background: linear-gradient(90deg, rgba(111, 217, 67, 0.1) 0%, rgba(111, 217, 67, 0.05) 100%);
        padding-left: 20px;
    }

    .search-option.selected {
        background: linear-gradient(90deg, rgba(111, 217, 67, 0.2) 0%, rgba(111, 217, 67, 0.1) 100%);
        border-left: 3px solid #6fd943;
    }

    .option-icon {
        font-size: 16px;
        color: #6fd943;
        margin-right: 12px;
        width: 20px;
        text-align: center;
        transition: all 0.2s ease;
    }

    .search-option:hover .option-icon {
        transform: scale(1.1);
        color: #5cb838;
    }

    .search-option span {
        font-size: 13px;
        font-weight: 500;
        color: #495057;
    }

    /* Search Input */
    .search-input-group {
        display: flex;
        align-items: center;
        position: relative;
    }

    .search-input {
        border: none;
        outline: none;
        padding: 0.4rem 0;
        width: 250px;
        font-size: 0.9rem;
        background: transparent;
    }

    .search-input:focus {
        box-shadow: none;
    }

    .search-button {
        background: linear-gradient(135deg, #6fd943 0%, #5cb838 100%);
        border: none;
        border-radius: 50%;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-left: 10px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .search-button:hover {
        transform: scale(1.1);
        box-shadow: 0 4px 8px rgba(111, 217, 67, 0.3);
    }

    .search-button i {
        color: white;
        font-size: 0.9rem;
    }

    /* Search Results */
    .search-results-dropdown {
        position: absolute;
        right: 0;
        top: 100%;
        margin-top: 10px;
        width: 350px;
        max-height: 400px;
        overflow-y: auto;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        border: 1px solid #e9ecef;
        padding: 0.5rem 0;
        z-index: 1000;
        display: none;
    }

    .search-results-dropdown::-webkit-scrollbar {
        width: 6px;
    }

    .search-results-dropdown::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }

    .search-results-dropdown::-webkit-scrollbar-thumb {
        background: #6fd943;
        border-radius: 10px;
    }

    .search-results-dropdown::-webkit-scrollbar-thumb:hover {
        background: #5cb838;
    }

    /* Invoice search result — dual action buttons (View Invoice / Client Profile) */
    .invoice-result-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 8px;
    }

    .inv-action {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        font-size: 11px;
        font-weight: 600;
        line-height: 1.4;
        border-radius: 14px;
        text-decoration: none;
        border: 1px solid transparent;
        transition: all .2s ease;
    }

    .inv-action i {
        font-size: 11px;
    }

    .inv-action-view {
        color: #c9700d;
        background: #fff3e0;
        border-color: #f5d6a8;
    }

    .inv-action-view:hover {
        background: #f0a23a;
        color: #fff;
        border-color: #f0a23a;
    }

    .inv-action-profile {
        color: #2f7a18;
        background: #eefbea;
        border-color: #cdeab9;
    }

    .inv-action-profile:hover {
        background: #6fd943;
        color: #fff;
        border-color: #5cb838;
    }

    /* Renewals Dropdown - WITH PROFESSIONAL COLOR SCHEME */
    .renewals-dropdown {
        position: absolute;
        left: 0 !important;
        right: auto !important;
        top: 100%;
        margin-top: 10px;
        width: 500px;
        max-height: 500px;
        overflow-y: auto;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, .12);
        border: 1px solid #e9ecef;
        z-index: 1000;
    }

    .renewals-dropdown::-webkit-scrollbar {
        width: 6px;
    }

    .renewals-dropdown::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }

    .renewals-dropdown::-webkit-scrollbar-thumb {
        background: #5c6bc0;
        border-radius: 10px;
    }

    .renewals-header {
        padding: 12px 15px;
        border-bottom: 1px solid #e9ecef;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #e8eaf6;
        border-radius: 12px 12px 0 0;
    }

    .renewals-header h6 {
        margin: 0;
        font-weight: 600;
        color: #3949ab;
        font-size: 14px;
    }

    .renewals-header .view-all-link {
        font-size: 0.75rem;
        color: #3949ab;
        text-decoration: none;
        font-weight: 500;
    }

    .renewals-header .view-all-link:hover {
        text-decoration: underline;
    }

    .renewals-body {
        padding: 10px 0;
    }

    .renewal-item {
        display: flex;
        align-items: flex-start;
        padding: 12px 15px;
        transition: background-color 0.2s;
        border-bottom: 1px solid #f8f9fa;
        background-color: #f5f7ff;
        border-left: 3px solid #5c6bc0;
    }

    .renewal-item:last-child {
        border-bottom: none;
    }

    .renewal-item:hover {
        background-color: #e8eaf6;
    }

    .renewal-icon {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background-color: #e8eaf6;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 12px;
        flex-shrink: 0;
        border: 1px solid #c5cae9;
    }

    .renewal-content {
        flex: 1;
        min-width: 0;
    }

    .renewal-title {
        font-weight: 500;
        font-size: 13px;
        margin-bottom: 6px;
        color: #495057;
        line-height: 1.4;
        word-wrap: break-word;
    }

    .renewal-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 11px;
    }

    .renewal-user {
        color: #6c757d;
        font-weight: 500;
    }

    .renewal-time {
        color: #6c757d;
        font-style: italic;
    }

    /* Activities Dropdown */
    .activities-dropdown {
        position: absolute;
        left: 0 !important;
        right: auto !important;
        top: 100%;
        margin-top: 10px;
        width: 500px;
        max-height: 500px;
        overflow-y: auto;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, .12);
        border: 1px solid #e9ecef;
        z-index: 1000;
    }

    .activities-dropdown::-webkit-scrollbar {
        width: 6px;
    }

    .activities-dropdown::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }

    .activities-dropdown::-webkit-scrollbar-thumb {
        background: #6fd943;
        border-radius: 10px;
    }

    .activities-header {
        padding: 12px 15px;
        border-bottom: 1px solid #e9ecef;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #f8f9fa;
        border-radius: 12px 12px 0 0;
    }

    .activities-header h6 {
        margin: 0;
        font-weight: 600;
        color: #495057;
        font-size: 14px;
    }

    .view-all-link {
        font-size: 0.75rem;
        color: #6fd943;
        text-decoration: none;
        font-weight: 500;
    }

    .view-all-link:hover {
        text-decoration: underline;
    }

    .activities-body {
        padding: 10px 0;
    }

    .activity-item {
        display: flex;
        align-items: flex-start;
        padding: 12px 15px;
        transition: background-color 0.2s;
        border-bottom: 1px solid #f8f9fa;
    }

    .activity-item:last-child {
        border-bottom: none;
    }

    .activity-item:hover {
        background-color: #f8f9fa;
    }

    .activity-icon {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background-color: #f8f9fa;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 12px;
        flex-shrink: 0;
        border: 1px solid #e9ecef;
        position: relative;
    }

    /* Inactive-client markers in the activity log */
    .activity-inactive-dot {
        position: absolute;
        top: -2px;
        right: -2px;
        width: 11px;
        height: 11px;
        border-radius: 50%;
        background: #ff4757;
        border: 2px solid #fff;
        box-shadow: 0 0 0 0 rgba(255, 71, 87, .5);
        animation: inactivePulse 1.8s infinite;
    }

    @keyframes inactivePulse {
        0%   { box-shadow: 0 0 0 0 rgba(255, 71, 87, .5); }
        70%  { box-shadow: 0 0 0 6px rgba(255, 71, 87, 0); }
        100% { box-shadow: 0 0 0 0 rgba(255, 71, 87, 0); }
    }

    .inactive-client-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        margin-left: 6px;
        padding: 1px 7px;
        font-size: 10px;
        font-weight: 600;
        line-height: 1.6;
        color: #d32f2f;
        background: #fdecea;
        border: 1px solid #f5c6cb;
        border-radius: 2px;
        vertical-align: middle;
    }

    .inactive-client-badge .ic-dot,
    .inactive-clients-divider .ic-dot {
        display: inline-block;
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #ff4757;
        flex-shrink: 0;
    }

    /* "Inactive Clients" divider appended to active search results */
    .inactive-clients-divider {
        display: flex;
        align-items: center;
        gap: 7px;
        margin: 8px 4px 2px;
        padding: 6px 10px;
        font-size: 12px;
        font-weight: 600;
        color: #b02a37;
        background: #fdecea;
        border-radius: 6px;
        border-left: 3px solid #ff4757;
    }

    .activity-content {
        flex: 1;
        min-width: 0;
    }

    .activity-title {
        font-weight: 500;
        font-size: 13px;
        margin-bottom: 6px;
        color: #495057;
        line-height: 1.4;
        word-wrap: break-word;
    }

    .activity-title-delete {
        color: #dc3545;
    }

    .activity-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 11px;
    }

    .activity-user {
        color: #6c757d;
        font-weight: 500;
    }

    .activity-time {
        color: #6c757d;
        font-style: italic;
    }

    /* Fullscreen Toggle Button */
    .fullscreen-btn {
        width: 34px;
        height: 34px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f1f3f5;
        border: 1px solid #e9ecef;
        border-radius: 50%;
        margin-right: 10px;
        cursor: pointer;
        color: #495057;
        transition: all 0.25s ease;
        flex-shrink: 0;
    }

    .fullscreen-btn:hover {
        background: #6fd943;
        color: #fff;
        border-color: #5cb838;
        transform: scale(1.05);
        box-shadow: 0 3px 8px rgba(111, 217, 67, 0.3);
    }

    .fullscreen-btn i {
        font-size: 16px;
        line-height: 1;
    }

    /* Notification Badge */
    .notification-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background: #ff4757;
        color: white;
        border-radius: 50%;
        width: 18px;
        height: 18px;
        font-size: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        border: 2px solid white;
    }

    .bell-container,
    .renewal-container {
        position: relative;
    }

    /* Badge styles for renewal notifications - WITH PROFESSIONAL COLORS */
    .badge-urgent {
        background: #d32f2f !important;
        color: #fff
    }

    .badge-pending {
        background: #ff8f00 !important;
        color: #fff
    }

    .badge-renewed {
        background: #388e3c !important;
        color: #fff
    }

    /* ========== QUICK ACTION BUTTON + DROPDOWN ========== */
    .quick-action-btn {
        width: 34px;
        height: 34px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #4F8EF7 0%, #2f6ee0 100%);
        border: 1px solid #2f6ee0;
        border-radius: 50%;
        margin-right: 10px;
        cursor: pointer;
        color: #fff;
        transition: all 0.25s ease;
        flex-shrink: 0;
        box-shadow: 0 2px 6px rgba(47, 110, 224, .25);
        position: relative;
    }

    .quick-action-btn:hover {
        transform: scale(1.08) rotate(90deg);
        box-shadow: 0 4px 10px rgba(47, 110, 224, .4);
    }

    .quick-action-btn.active {
        transform: rotate(45deg);
        background: linear-gradient(135deg, #2f6ee0 0%, #1e5bc6 100%);
    }

    .quick-action-btn i {
        font-size: 20px;
        line-height: 1;
    }

    .quick-action-dropdown {
        position: absolute;
        left: 0 !important;
        right: auto !important;
        top: 100%;
        margin-top: 10px;
        width: 260px;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 8px 28px rgba(0, 0, 0, .14);
        border: 1px solid #e9ecef;
        z-index: 1005;
        overflow: visible;
    }

    .qa-header {
        padding: 10px 16px 6px;
        font-size: 11px;
        font-weight: 700;
        color: #8896a6;
        letter-spacing: 0.8px;
        text-transform: uppercase;
        border-bottom: 1px solid #f1f3f5;
    }

    .qa-subheader {
        background: #f8f9fa;
        border-bottom: 1px solid #eef1f4;
        border-radius: 12px 12px 0 0;
    }

    .qa-body {
        padding: 6px 0;
    }

    .qa-item {
        display: flex;
        align-items: center;
        padding: 9px 16px;
        color: #333;
        text-decoration: none;
        font-size: 13.5px;
        font-weight: 500;
        transition: background-color .18s ease, padding-left .18s ease;
        cursor: pointer;
        position: relative;
    }

    .qa-item:hover {
        background: #f5faff;
        color: #2f6ee0;
        padding-left: 20px;
    }

    .qa-icon {
        width: 30px;
        height: 30px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-right: 12px;
        flex-shrink: 0;
        font-size: 15px;
    }

    .qa-icon i {
        font-size: 16px;
        line-height: 1;
    }

    /* Quick Action tinted-box icons (light bg + colored icon) */
    .qa-tint-blue   { background: #E3F2FD; color: #2196F3; }
    .qa-tint-green  { background: #E8F5E9; color: #4CAF50; }
    .qa-tint-orange { background: #FFF3E0; color: #FF9800; }
    .qa-tint-purple { background: #F3E5F5; color: #9C27B0; }
    .qa-tint-gray   { background: #F5F5F5; color: #9E9E9E; }

    /* Additional Actions plain colored icons (no background box) */
    .qa-icon-plain {
        width: 24px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-right: 14px;
        flex-shrink: 0;
        font-size: 18px;
        line-height: 1;
    }

    .qa-icon-plain i {
        font-size: 18px;
        line-height: 1;
    }

    .qa-c-blue   { color: #2196F3; }
    .qa-c-green  { color: #4CAF50; }
    .qa-c-yellow { color: #FFC107; }
    .qa-c-pink   { color: #E91E63; }
    .qa-c-red    { color: #F44336; }
    .qa-c-teal   { color: #009688; }
    .qa-c-purple { color: #9C27B0; }
    .qa-c-stripe { color: #635BFF; }

    .qa-stripe-text {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
        font-weight: 700;
        font-size: 12px;
        font-style: italic;
        letter-spacing: -0.3px;
    }

    .qa-label {
        flex: 1;
        line-height: 1.2;
    }

    .qa-divider {
        height: 1px;
        background: #f1f3f5;
        margin: 4px 12px;
    }

    .qa-more {
        color: #6c757d;
    }

    .qa-more-arrow {
        margin-left: auto;
        color: #adb5bd;
        font-size: 14px;
        transition: transform .2s ease;
    }

    .qa-more.active .qa-more-arrow {
        transform: rotate(90deg);
        color: #2f6ee0;
    }

    /* Additional Actions panel — appears next to the main dropdown */
    .qa-more-panel {
        position: absolute;
        top: 0;
        left: calc(100% + 8px);
        width: 240px;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 8px 28px rgba(0, 0, 0, .14);
        border: 1px solid #e9ecef;
        display: none;
        z-index: 1006;
        max-height: 420px;
        overflow-y: auto;
    }

    .qa-more-panel.show {
        display: block;
        animation: qaFadeIn .18s ease;
    }

    .qa-more-panel::-webkit-scrollbar {
        width: 6px;
    }

    .qa-more-panel::-webkit-scrollbar-thumb {
        background: #d0d7de;
        border-radius: 10px;
    }

    @keyframes qaFadeIn {
        from { opacity: 0; transform: translateX(-6px); }
        to   { opacity: 1; transform: translateX(0); }
    }

    /* Responsive: on small screens, show additional actions below instead of beside */
    @media (max-width: 768px) {
        .quick-action-dropdown {
            width: 100%;
            left: 0 !important;
            right: 0 !important;
        }

        .qa-more-panel {
            position: static;
            width: 100%;
            margin-top: 6px;
            box-shadow: none;
            border-top: 2px solid #f1f3f5;
            border-radius: 0 0 12px 12px;
        }
    }
    /* ========== /QUICK ACTION ========== */

    /* Quick Action → "Add Invoice" client picker (SweetAlert popup contents) */
    .qa-picker-popup {
        overflow-x: hidden;
    }

    .qa-picker-popup .swal2-html-container {
        overflow-x: hidden !important;
    }

    .qa-client-search-input.swal2-input {
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
        margin-left: 0;
        margin-right: 0;
    }

    .qa-client-results {
        max-height: 300px;
        overflow-y: auto;
        overflow-x: hidden;
        text-align: left;
        margin-top: 10px;
        border-radius: 8px;
        box-sizing: border-box;
    }

    .qa-client-row {
        padding: 9px 12px;
        cursor: pointer;
        border-bottom: 1px solid #f1f3f5;
        transition: background-color .15s ease;
        box-sizing: border-box;
    }

    .qa-client-row:last-child {
        border-bottom: none;
    }

    .qa-client-row:hover {
        background: #f5faff;
    }

    .qa-client-row strong {
        font-size: 13.5px;
        color: #2c3e50;
    }

    .qa-client-row-bus {
        font-size: 12px;
        color: #6c757d;
        margin-top: 2px;
    }

    .qa-client-empty {
        padding: 14px 10px;
        text-align: center;
        color: #8896a6;
        font-size: 13px;
    }

    /* Responsive Adjustments */

    /* Laptop widths: let the search input flex so the toolbar never spills off-screen */
    @media (min-width: 1025px) and (max-width: 1699px) {
        .search-container {
            flex: 1 1 auto;
            min-width: 0;
        }

        .search-container>ul,
        .search-container .dash-h-item,
        .search-container form {
            width: 100%;
            min-width: 0;
        }

        .search-wrapper {
            width: 100%;
        }

        .fullscreen-btn,
        .quick-action-btn,
        .renewal-container,
        .bell-container,
        .status-filter,
        .search-by-filter {
            flex-shrink: 0;
        }

        .search-input-group {
            flex: 1 1 80px;
            min-width: 0;
        }

        .search-input {
            width: 100%;
            min-width: 0;
        }
    }

    /* Smaller laptops: compact the toolbar — icon-only pills, hide greeting text */
    @media (min-width: 1025px) and (max-width: 1450px) {

        .renewal-container>span:first-of-type,
        .bell-container>span:first-of-type {
            display: none;
        }

        .renewal-container,
        .bell-container {
            padding: 4px !important;
            border-radius: 50% !important;
        }

        .drp-company .hide-mob {
            display: none;
        }

        .status-filter,
        .search-by-filter {
            margin-right: 10px;
            padding-right: 10px;
        }

        .search-by-dropdown {
            min-width: 140px;
        }
    }

    @media (max-width: 1024px) {

        /* Template hides the hamburger at exactly 1024px (iPad landscape)
           while the sidebar is already off-canvas — keep it visible */
        .dash-header .dash-h-item.mob-hamburger {
            display: flex;
        }

        /* Header flows with the page so the expanded search panel
           pushes content down instead of overlapping it */
        .dash-header.transprent-bg {
            position: relative;
            left: 0;
            top: 0;
        }

        .dash-header.transprent-bg~.dash-container {
            top: 0;
        }

        .dash-header .header-wrapper {
            flex-wrap: wrap;
            padding: 0 12px 10px;
        }

        .search-container {
            flex: 0 0 100%;
            max-width: 100%;
            margin-left: 0 !important;
        }

        .search-container>ul,
        .search-container .dash-h-item,
        .search-container form {
            width: 100%;
        }

        .search-container .dash-h-item {
            min-height: 0;
        }

        .search-wrapper {
            flex-wrap: wrap;
            justify-content: center;
            row-gap: 10px;
            column-gap: 10px;
        }

        /* gap handles the spacing — drop desktop margins and divider lines */
        .fullscreen-btn,
        .quick-action-btn,
        .renewal-container,
        .bell-container {
            margin-right: 0 !important;
        }

        .status-filter,
        .search-by-filter {
            margin-right: 0;
            padding-right: 0;
            border-right: none;
        }

        /* Search By + input share one row; the input flexes */
        .search-input-group {
            flex: 1 1 240px;
            width: auto;
            min-width: 0;
        }

        .search-input {
            flex: 1;
            width: auto;
            min-width: 0;
        }
    }

    @media (max-width: 768px) {
        .search-wrapper {
            padding: 0.8rem;
            border-radius: 15px;
        }

        .search-by-filter {
            width: 100%;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 0.5rem;
        }

        .search-by-dropdown {
            width: 100%;
        }

        .status-filter {
            width: 100%;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 0.5rem;
            justify-content: space-around;
        }

        .search-input-group {
            flex-basis: 100%;
        }

        .search-input {
            width: 100%;
        }

        .search-results-dropdown {
            width: 100%;
            left: 0;
            right: 0;
        }

        .activities-dropdown,
        .renewals-dropdown {
            width: 100%;
            max-width: none;
            left: 0 !important;
            right: auto !important;
        }
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
