<?php
include 'includes/db.php';

/* ---------- 1.  user picture (unchanged) ---------- */
$uuid = $_SESSION['user_id'] ?? 0;
$uuquery = mysqli_query($con, "SELECT user_pic FROM users WHERE user_id='$uuid' LIMIT 1");
$user_pic = 'user-8.jpg';
if ($row = mysqli_fetch_assoc($uuquery))
    $user_pic = $row['user_pic'] ?: $user_pic;

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

/* ---------- 4.  normalise activities ---------- */
$recent_activities = [];
foreach ($logs as $l) {
    $recent_activities[] = [
        'activity_type' => strtolower($l['object_type']) . '_' . strtolower($l['action']),
        'description' => $l['description'] ?: ucfirst(strtolower($l['action'])) . ' ' . $l['object_type'] . ' #' . $l['object_id'],
        'timestamp' => $l['created_at'],
        'added_by' => $l['actor_name'],
    ];
}

// Calculate counts
$renewal_count = count($renewals);
$activity_count = count($recent_activities);
$unread_count = $activity_count; // Regular notifications count
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
                                            </div>
                                            <div class="activity-content">
                                                <div class="activity-title">
                                                    <?php echo htmlspecialchars($activity['description'] ?? ''); ?>
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
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
<!-- [ Header ] end -->

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
        overflow: hidden;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        z-index: 1000;
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

    /* Responsive Adjustments */
    @media (max-width: 768px) {
        .search-wrapper {
            flex-direction: column;
            padding: 0.8rem;
            border-radius: 15px;
        }

        .search-by-filter {
            width: 100%;
            margin-right: 0;
            padding-right: 0;
            border-right: none;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .search-by-dropdown {
            width: 100%;
        }

        .status-filter {
            width: 100%;
            margin-right: 0;
            padding-right: 0;
            border-right: none;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 0.5rem;
            margin-bottom: 0.5rem;
            justify-content: space-around;
        }

        .search-input {
            width: 200px;
        }

        .search-results-dropdown {
            width: 300px;
        }

        .activities-dropdown,
        .renewals-dropdown {
            width: calc(100vw - 20px);
            max-width: 400px;
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
        });

        // Recent search click
        $(document).on("click", ".recent-search", function (e) {
            e.preventDefault();
            let value = $(this).data("search");
            $("#searchInput").val(value).trigger("input");
        });

        // Toggle activities dropdown
        $("#bellIcon").on("click", function (e) {
            e.stopPropagation();
            $("#activitiesDropdown").toggle();
            $("#renewalsDropdown").hide();
            $("#searchResults").hide();
            searchByOptions.removeClass('show');
            searchBySelected.removeClass('active');
        });

        // Toggle renewals dropdown
        $("#renewalIcon").on("click", function (e) {
            e.stopPropagation();
            $("#renewalsDropdown").toggle();
            $("#activitiesDropdown").hide();
            $("#searchResults").hide();
            searchByOptions.removeClass('show');
            searchBySelected.removeClass('active');
        });
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
