<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

include "includes/db.php";
include "includes/auth.php";

$user_id = $_SESSION['user_id'];
$update_last_seen = "UPDATE users SET last_seen = NOW() WHERE user_id = ?";
$update_stmt = mysqli_prepare($con, $update_last_seen);
mysqli_stmt_bind_param($update_stmt, "i", $user_id);
mysqli_stmt_execute($update_stmt);

function getInitials($name)
{
   $words = explode(' ', $name);
   $initials = '';
   foreach ($words as $word) {
      $initials .= strtoupper(substr($word, 0, 1));
   }
   return $initials;
}

/* ===== TODAY'S CLIENT REMINDERS (with creator) ===== */
$today = date('Y-m-d');
$reminders = [];
$sql = "SELECT r.reminder_id,
               r.client_id,
               r.reminder_title,
               r.reminder_description,
               r.reminder_date,
               r.reminder_type,
               r.status,
               r.created_by,
               CONCAT(m.name, ' ', m.lname) AS client_name
        FROM client_calender_reminder r
        JOIN members m ON m.member_id = r.client_id
        WHERE DATE(r.reminder_date) = ? AND r.status = 1 AND r.created_by = ?
        ORDER BY r.reminder_date ASC";
$stmt = $con->prepare($sql);
$stmt->bind_param('si', $today, $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
   if (isset($row['reminder_description'])) {
      $row['reminder_description'] = trim((string) preg_replace('/\s*<!--REM_META:[A-Za-z0-9+\/=]+-->\s*$/', '', (string) $row['reminder_description']));
   }
   $reminders[] = $row;
}
$stmt->close();

/* ===== ALL REMINDERS FOR CALENDAR ===== */
$all_reminders = [];
$sql_all = "SELECT r.reminder_id,
                  r.client_id,
                  r.reminder_title,
                  r.reminder_description,
                  r.reminder_date,
                  r.reminder_type,
                  r.status,
                  r.created_by,
                  CONCAT(m.name, ' ', m.lname) AS client_name
           FROM client_calender_reminder r
           JOIN members m ON m.member_id = r.client_id
           WHERE r.status = 1 AND r.created_by = ?
           ORDER BY r.reminder_date ASC";
$stmt_all = $con->prepare($sql_all);
$stmt_all->bind_param('i', $user_id);
$stmt_all->execute();
$result_all = $stmt_all->get_result();
while ($row_all = $result_all->fetch_assoc()) {
   if (isset($row_all['reminder_description'])) {
      $row_all['reminder_description'] = trim((string) preg_replace('/\s*<!--REM_META:[A-Za-z0-9+\/=]+-->\s*$/', '', (string) $row_all['reminder_description']));
   }
   $all_reminders[] = $row_all;
}
$stmt_all->close();

$user_full_name = $_SESSION['fullname'];
$user_initials = getInitials($user_full_name);
$user_type = $_SESSION['user_type'];

?>

<!DOCTYPE html>
<html lang="en" dir="">
<meta name="csrf-token" id="csrf-token" content="8c2VdHOgBJmeryjWyCs9cx1G8chmj75MpBN38dRd">

<head>
   <meta name="viewport" content="width=device-width, initial-scale=1">
   <title>Syed Professional Services - Dashboard</title>
   <?php include "includes/links.php"; ?>
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
   <style>
      .global-search-container {
         position: relative;
      }

      .search-results-container {
         background: white;
         border: 1px solid #ddd;
         border-radius: 5px;
         box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
         position: absolute;
         width: 100%;
         z-index: 1000;
         max-height: 500px;
         overflow-y: auto;
         margin-top: 10px;
         animation: fadeIn 0.3s ease-in-out;
      }

      @keyframes fadeIn {
         from {
            opacity: 0;
            transform: translateY(-10px);
         }

         to {
            opacity: 1;
            transform: translateY(0);
         }
      }

      .search-results-header {
         display: flex;
         justify-content: space-between;
         align-items: center;
         padding: 10px 15px;
         border-bottom: 1px solid #eee;
         background-color: #f8f9fa;
      }

      .search-results-header h5 {
         margin: 0;
      }

      .result-section {
         margin-bottom: 15px;
      }

      .result-section-title {
         font-weight: bold;
         padding: 8px 15px;
         background-color: #f1f1f1;
         border-bottom: 1px solid #eee;
      }

      .result-item {
         padding: 10px 15px;
         border-bottom: 1px solid #eee;
         cursor: pointer;
         transition: background-color 0.2s;
      }

      .result-item:hover {
         background-color: #f8f9fa;
      }

      .result-item:last-child {
         border-bottom: none;
      }

      .result-title {
         font-weight: 500;
         margin-bottom: 3px;
      }

      .result-subtitle {
         font-size: 0.9em;
         color: #666;
      }

      .no-results {
         padding: 15px;
         text-align: center;
         color: #666;
      }

      .search-input-group {
         position: relative;
      }

      .search-input-group .form-control {
         padding-right: 40px;
      }

      .search-input-group .input-group-append {
         position: absolute;
         right: 0;
         top: 0;
         height: 100%;
         z-index: 10;
      }

      .search-input-group .input-group-append .btn {
         border-top-left-radius: 0;
         border-bottom-left-radius: 0;
         height: 100%;
      }

      .highlight {
         background-color: #fff3cd;
         padding: 1px 2px;
         border-radius: 2px;
      }

      #remindersTable_wrapper {
         padding: 0 1rem 1rem 1rem;
      }

      #remindersTable thead th {
         border-top: none;
         font-weight: 600;
         font-size: .85rem;
         white-space: nowrap;
      }

      #remindersTable tbody td {
         font-size: .8125rem;
         padding: .6rem .75rem;
      }

      .card-header .badge {
         font-size: .7rem;
      }

      .card-3-in-row {
         width: 33.33%;
         padding: 0 7.5px;
      }

      /* Tablets and small laptops: two cards per row so the charts keep room */
      @media (max-width: 1399.98px) {
         .card-3-in-row {
            width: 50%;
         }
      }

      @media (max-width: 576px) {
         .card-3-in-row {
            width: 100%;
         }
      }

      /* =====================================================
         UPGRADED CALENDAR STYLES
         ===================================================== */
      :root {
         --cal-brand: #1A1F2E;
         --cal-accent: #4F8EF7;
         --cal-teal: #22C9A2;
         --cal-danger: #E85D56;
         --cal-warn: #F5A623;
         --cal-surface: #FFFFFF;
         --cal-surface2: #F6F8FC;
         --cal-border: rgba(20, 30, 60, .09);
         --cal-shadow: 0 4px 18px rgba(14, 17, 23, .08), 0 1px 4px rgba(14, 17, 23, .04);
         --cal-r: 10px;
         --cal-ease: .18s cubic-bezier(.4, 0, .2, 1);
      }

      /* Calendar card wrapper */
      .cal-card {
         background: var(--cal-surface);
         border: 1px solid var(--cal-border);
         border-radius: var(--cal-r);
         box-shadow: var(--cal-shadow);
         overflow: hidden;
         position: relative;
         margin-bottom: 1rem;
      }

      .cal-card-header {
         display: flex;
         align-items: center;
         justify-content: space-between;
         padding: 13px 16px;
         border-bottom: 1px solid var(--cal-border);
         background: var(--cal-surface2);
       box-shadow: rgba(0, 0, 0, 0.15) 0px 2px 8px;
      }

      .cal-card-title {
         font-size: 13.5px;
         font-weight: 600;
         color: var(--cal-brand);
         display: flex;
         align-items: center;
         gap: 7px;
      }

      .cal-card-title i {
         color: var(--cal-accent);
      }

      .cal-today-btn {
         font-size: 11px;
         font-weight: 600;
         color: var(--cal-accent);
         background: rgba(79, 142, 247, .1);
         border: none;
         border-radius: 6px;
         padding: 4px 11px;
         cursor: pointer;
         transition: background var(--cal-ease);
      }

      .cal-today-btn:hover {
         background: rgba(79, 142, 247, .22);
      }

      /* Nav + grid */
      .cal-body {
         padding: 14px 14px 10px;
      }

      .cal-nav {
         display: flex;
         align-items: center;
         justify-content: space-between;
         margin-bottom: 12px;
      }

      .cal-nav-btn {
         width: 28px;
         height: 28px;
         border-radius: 7px;
         background: var(--cal-surface2);
         border: 1px solid var(--cal-border);
         display: flex;
         align-items: center;
         justify-content: center;
         cursor: pointer;
         font-size: 11px;
         color: #6c757d;
         transition: background var(--cal-ease), color var(--cal-ease), border-color var(--cal-ease);
         box-shadow:  rgba(0, 0, 0, 0.16) 0px 3px 6px, rgba(0, 0, 0, 0.23) 0px 3px 6px; 
      }

      .cal-nav-btn:hover {
         background: var(--cal-accent);
         color: #fff;
         border-color: var(--cal-accent);
      }

      .cal-month-title {
         font-size: 13.5px;
         font-weight: 600;
         color: var(--cal-brand);
      }

      .cal-grid {
         display: grid;
         grid-template-columns: repeat(7, 1fr);
         gap: 2px;
      }

      .cal-dh {
         text-align: center;
         font-size: 9.5px;
         font-weight: 600;
         color: #8896AA;
         text-transform: uppercase;
         letter-spacing: .05em;
         padding: 5px 0;
         box-shadow: rgba(9, 30, 66, 0.25) 0px 4px 8px -2px, rgba(9, 30, 66, 0.08) 0px 0px 0px 1px;
      }

      .cal-day {
         aspect-ratio: 1;
         display: flex;
         flex-direction: column;
         align-items: center;
         justify-content: center;
         border-radius: 7px;
         cursor: pointer;
         position: relative;
         font-size: 11.5px;
         font-weight: 400;
         color: #4A5568;
         box-shadow: rgba(0, 0, 0, 0.16) 0px 3px 6px, rgba(0, 0, 0, 0.23) 0px 3px 6px;
         transition: background var(--cal-ease), color var(--cal-ease);
         user-select: none;
      }

      .cal-day:hover {
         background: var(--cal-surface2);
      }

      .cal-day.other {
         color: rgba(136, 150, 170, .3);
         cursor: default;
      }

      .cal-day.other:hover {
         background: none;
      }

      .cal-day.today {
         background: #5EB839;
         color: #fff;
         font-weight: 600;
      }

      .cal-day.selected {
         background: var(--cal-accent);
         color: #fff;
         font-weight: 500;
      }

      .cal-day.has-rem::after {
         content: '';
         position: absolute;
         bottom: 3px;
         width: 4px;
         height: 4px;
         border-radius: 50%;
         background: var(--cal-danger);
      }

      .cal-day.today.has-rem::after {
         background: rgba(255, 255, 255, .8);
      }

      .cal-day.selected.has-rem::after {
         background: rgba(255, 255, 255, .85);
      }

      /* Legend */
      .cal-legend {
         display: flex;
         gap: 14px;
         margin-top: 11px;
         justify-content: center;
      }

      .cal-legend-item {
         display: flex;
         align-items: center;
         gap: 5px;
         font-size: 11px;
         color: #8896AA;
      }

      .cal-legend-dot {
         width: 7px;
         height: 7px;
         border-radius: 50%;
      }

      /* Inline popup overlay (inside card) */
      .cal-popup-backdrop {
         position: absolute;
         inset: 0;
         background: rgba(14, 17, 23, .46);
         z-index: 30;
         display: none;
         align-items: center;
         justify-content: center;
         padding: 14px;
         border-radius: var(--cal-r);
      }

      .cal-popup-backdrop.open {
         display: flex;
         animation: calBdIn .15s ease;
      }

      @keyframes calBdIn {
         from {
            opacity: 0
         }

         to {
            opacity: 1
         }
      }

      .cal-popup {
         background: var(--cal-surface);
         border-radius: 10px;
         border: 1px solid rgba(20, 30, 60, .14);
         width: 100%;
         max-width: 295px;
         overflow: hidden;
         box-shadow: 0 12px 40px rgba(14, 17, 23, .14);
         animation: calPopIn .18s cubic-bezier(.34, 1.3, .64, 1);
      }

      @keyframes calPopIn {
         from {
            opacity: 0;
            transform: scale(.93)
         }

         to {
            opacity: 1;
            transform: none
         }
      }

      .cal-popup-hdr {
         display: flex;
         align-items: center;
         justify-content: space-between;
         padding: 10px 13px;
         background: var(--cal-surface2);
         border-bottom: 1px solid var(--cal-border);
      }

      .cal-popup-date {
         font-size: 12px;
         font-weight: 600;
         color: var(--cal-brand);
      }

      .cal-popup-close {
         background: none;
         border: none;
         cursor: pointer;
         color: #8896AA;
         font-size: 19px;
         line-height: 1;
         padding: 0;
         transition: color var(--cal-ease);
      }

      .cal-popup-close:hover {
         color: var(--cal-danger);
      }

      .cal-popup-items {
         max-height: 228px;
         overflow-y: auto;
      }

      .cal-popup-item {
         padding: 10px 13px;
         border-bottom: 1px solid var(--cal-border);
      }

      .cal-popup-item:last-child {
         border-bottom: none;
      }

      .cal-popup-item-row {
         display: flex;
         align-items: center;
         gap: 7px;
         margin-bottom: 3px;
      }

      .cal-popup-dot {
         width: 7px;
         height: 7px;
         border-radius: 50%;
         flex-shrink: 0;
      }

      .cal-popup-title {
         font-size: 12.5px;
         font-weight: 600;
         color: #0E1117;
      }

      .cal-popup-client {
         font-size: 11px;
         color: var(--cal-accent);
         margin-top: 2px;
         padding-left: 14px;
      }

      .cal-popup-time {
         font-size: 11px;
         color: #8896AA;
         margin-top: 1px;
         padding-left: 14px;
      }

      .cal-popup-desc {
         font-size: 11px;
         color: #4A5568;
         margin-top: 5px;
         line-height: 1.45;
         padding: 5px 0 0 14px;
         border-top: 1px dashed var(--cal-border);
      }

      .cal-popup-actions {
         display: flex;
         gap: 7px;
         padding: 9px 13px;
         border-top: 1px solid var(--cal-border);
      }

      .cal-popup-btn {
         flex: 1;
         font-size: 11px;
         font-weight: 600;
         padding: 6px 0;
         border-radius: 7px;
         border: 1px solid rgba(20, 30, 60, .14);
         background: var(--cal-surface2);
         color: #4A5568;
         cursor: pointer;
         transition: background var(--cal-ease), transform var(--cal-ease);
      }

      .cal-popup-btn:hover {
         background: #EEF1F8;
         transform: translateY(-1px);
      }

      .cal-popup-btn.snooze {
         background: rgba(245, 166, 35, .1);
         color: var(--cal-warn);
         border-color: rgba(245, 166, 35, .3);
      }

      .cal-popup-btn.snooze:hover {
         background: rgba(245, 166, 35, .22);
      }

      .cal-popup-btn.dismiss {
         background: rgba(232, 93, 86, .1);
         color: var(--cal-danger);
         border-color: rgba(232, 93, 86, .25);
      }

      .cal-popup-btn.dismiss:hover {
         background: rgba(232, 93, 86, .22);
      }

      /* Runtime auto-due popup — keeps original class names for JS compatibility */
      .runtime-reminder-popup {
         --popup-theme: #6FD943;
         --popup-danger: var(--bs-danger, #dc3545);
         position: fixed;
         top: 24px;
         right: 20px;
         width: 380px;
         max-width: calc(100vw - 40px);
         background: #ffffff;
         border: 1px solid #e8edf3;
         border-left: 6px solid var(--popup-theme);
         border-radius: 14px;
         box-shadow: 0 18px 38px rgba(14, 30, 62, 0.16);
         z-index: 9999;
         overflow: hidden;
         opacity: 0;
         transform: translateY(-14px) scale(0.98);
         visibility: hidden;
         pointer-events: none;
         transition: opacity 0.22s ease, transform 0.22s ease, visibility 0s linear 0.22s;
      }

      .runtime-reminder-popup.is-visible {
         opacity: 1;
         transform: translateY(0) scale(1);
         visibility: visible;
         pointer-events: auto;
         transition: opacity 0.22s ease, transform 0.22s ease;
      }

      .runtime-reminder-popup-header {
         padding: 12px 14px;
         background: var(--popup-theme);
         border-bottom: 1px solid rgba(0, 0, 0, 0.06);
         color: #ffffff;
         font-weight: 700;
         font-size: 0.93rem;
         letter-spacing: 0.02em;
      }

      .runtime-reminder-popup-body {
         padding: 12px 14px 10px;
         font-size: 0.83rem;
         background: linear-gradient(180deg, #ffffff 0%, #fcfdff 100%);
      }

      .runtime-reminder-popup-row {
         margin-bottom: 7px;
         display: grid;
         grid-template-columns: 74px 1fr;
         gap: 8px;
         align-items: start;
      }

      .runtime-reminder-label {
         color: #556070;
         font-weight: 600;
      }

      .runtime-reminder-value {
         color: #1f2a37;
         font-weight: 500;
         word-break: break-word;
      }

      .runtime-reminder-description {
         margin-top: 9px;
         padding: 8px 10px;
         border-radius: 9px;
         background: #f7fbff;
         border: 1px solid rgba(0, 0, 0, 0.08);
      }

      .runtime-reminder-description-label {
         color: #4f5d6f;
         font-weight: 600;
         margin-bottom: 3px;
      }

      .runtime-reminder-description-text {
         color: #5b6573;
         line-height: 1.45;
         max-height: 92px;
         overflow-y: auto;
      }

      .runtime-reminder-popup-actions {
         display: flex;
         gap: 8px;
         justify-content: flex-end;
         padding: 10px 14px 14px;
         border-top: 1px solid #edf1f5;
         background: #fbfcfe;
      }

      #runtimeReminderSnooze,
      #runtimeReminderStop {
         border-radius: 8px;
         border: none;
         padding: 6px 12px;
         font-weight: 600;
         transition: transform 0.12s ease, box-shadow 0.12s ease, opacity 0.12s ease;
      }

      #runtimeReminderSnooze:hover,
      #runtimeReminderStop:hover {
         transform: translateY(-1px);
         box-shadow: 0 6px 12px rgba(0, 0, 0, .12);
      }

      #runtimeReminderSnooze {
         background: #6FD943;
         color: #ffffff;
      }

      #runtimeReminderStop {
         background: var(--popup-danger);
         color: #fff;
      }

      /* =====================================================
         SMALL-SCREEN RESPONSIVENESS
         ===================================================== */
      .chat-fallback-container {
         max-width: calc(100vw - 40px) !important;
         max-height: calc(100vh - 40px);
      }

      .card-body canvas {
         max-width: 100%;
      }

      @media (max-width: 768px) {
         .search-results-container {
            max-height: 60vh;
         }
      }

      @media (max-width: 576px) {
         .page-block .breadcrumb {
            margin-bottom: .5rem;
         }

         .search-input-group .form-control {
            font-size: 13px;
         }

         .runtime-reminder-popup {
            top: 12px;
            right: 12px;
            left: 12px;
            width: auto;
         }

         .cal-body {
            padding: 10px 8px 8px;
         }

         .cal-legend {
            gap: 10px;
            flex-wrap: wrap;
         }
      }
   </style>
</head>

<body class="theme-3">
   <!-- ===== CHAT SYSTEM HOOK ===== -->
   <div id="chatSystemContainer" style="display:block;"></div>
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
   <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
   <!-- ===== /CHAT SYSTEM HOOK ===== -->
   <div class="loader-bg">
      <div class="loader-track">
         <div class="loader-fill"></div>
      </div>
   </div>

   <?php include "includes/sidebar.php"; ?>
   <?php include "includes/header.php"; ?>
   <div class="dash-container">
      <div class="dash-content">
         <div class="card card-body">
            <div class="page-block">
               <div class="row align-items-center">
                  <div class="col-12 col-lg-auto">
                     <div class="page-header-title">
                        <h4 class="m-b-10">Dashboard</h4>
                     </div>
                     <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                        <li class="breadcrumb-item">Home</li>
                     </ul>
                  </div>
                  <div class="col-12 col-lg">
                     <div class="row mt-3">
                        <div class="col-12">
                           <div class="card">
                              <div class="card-body">
                                 <div class="global-search-container">
                                    <div class="search-input-group">
                                       <div class="input-group">
                                          <input type="text" id="globalSearchInput" class="form-control"
                                             placeholder="Type to search (min. 2 characters) for clients, invoices, projects, quotations (by Sr#), payments, staff...">
                                          <div class="input-group-append">
                                             <button id="globalSearchBtn" class="btn btn-primary">
                                                <i class="ti ti-search"></i> <span class="d-none d-sm-inline">Search</span>
                                             </button>
                                          </div>
                                       </div>
                                    </div>
                                    <div id="searchResultsContainer" class="search-results-container"
                                       style="display: none;">
                                       <div class="search-results-header">
                                          <h5>Search Results</h5>
                                          <button id="closeSearchResults" class="btn btn-sm btn-outline-secondary">
                                             <i class="ti ti-x"></i>
                                          </button>
                                       </div>
                                       <div id="searchResultsContent" class="search-results-content"></div>
                                    </div>
                                 </div>
                              </div>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
            </div>
         </div>

         <!-- Main Content with Two Columns -->
         <div class="row mt-3">
            <!-- Left Column - Statistics Cards -->
            <div class="col-lg-8">
               <div class="row">
                  <?php if ($user_type == '1' || $user_type == '2') { ?>
                     <div class="col-lg-4 col-md-6 card-3-in-row">
                        <div class="card">
                           <div class="card-body d-flex align-items-center justify-content-between">
                              <div>
                                 <div class="theme-avtar bg-primary"><i class="ti ti-users"></i></div>
                                 <p class="text-muted text-sm mt-4 mb-2">Total</p>
                                 <a href="view_clients.php">
                                    <h6 class="mb-3">Active Clients</h6>
                                 </a>
                                 <h3 class="mb-0">
                                    <?php
                                    $result_active_clients = mysqli_query($con, "SELECT count(*) as total from members where status='1'");
                                    $data_active_clients = mysqli_fetch_assoc($result_active_clients);
                                    $active_clients = $data_active_clients['total'];
                                    $result_total_clients = mysqli_query($con, "SELECT count(*) as total from members");
                                    $data_total_clients = mysqli_fetch_assoc($result_total_clients);
                                    $total_clients = $data_total_clients['total'];
                                    $inactive_clients = $total_clients - $active_clients;
                                    echo $active_clients;
                                    ?>
                                 </h3>
                              </div>
                              <div style="flex: 1; display: flex; justify-content: flex-end;">
                                 <canvas id="activeClientsChart" style="height: 150px; width: 100%;"></canvas>
                              </div>
                           </div>
                        </div>
                     </div>
                     <div class="col-lg-4 col-md-6 card-3-in-row">
                        <div class="card">
                           <div class="card-body d-flex align-items-center justify-content-between">
                              <div>
                                 <div class="theme-avtar bg-info"><i class="ti ti-user"></i></div>
                                 <p class="text-muted text-sm mt-4 mb-2">Total</p>
                                 <a href="users.php">
                                    <h6 class="mb-3">Staff Members</h6>
                                 </a>
                                 <h3 class="mb-0">
                                    <?php
                                    $result_active_staff = mysqli_query($con, "SELECT count(*) as total from users where user_login_status='1'");
                                    $data_active_staff = mysqli_fetch_assoc($result_active_staff);
                                    $active_staff = $data_active_staff['total'];
                                    $result_total_staff = mysqli_query($con, "SELECT count(*) as total from users");
                                    $data_total_staff = mysqli_fetch_assoc($result_total_staff);
                                    $total_staff = $data_total_staff['total'];
                                    $inactive_staff = $total_staff - $active_staff;
                                    echo $active_staff;
                                    ?>
                                 </h3>
                              </div>
                              <div style="flex: 1; display: flex; justify-content: flex-end;">
                                 <canvas id="activeStaffChart" style="height: 150px; width: 100%;"></canvas>
                              </div>
                           </div>
                        </div>
                     </div>
                     <div class="col-lg-4 col-md-6 card-3-in-row">
                        <div class="card">
                           <div class="card-body d-flex align-items-center justify-content-between">
                              <div>
                                 <div class="theme-avtar bg-warning"><i class="ti ti-report-money"></i></div>
                                 <p class="text-muted text-sm mt-4 mb-2">Total</p>
                                 <a href="invoice_billings.php?status=1">
                                    <h6 class="mb-3">Pending Invoices</h6>
                                 </a>
                                 <h3 class="mb-0">
                                    <?php
                                    $result_pending_invoices = mysqli_query($con, "SELECT count(*) as total from billing_invoice where invoice_status='1'");
                                    $data_pending_invoices = mysqli_fetch_assoc($result_pending_invoices);
                                    $pending_invoices = $data_pending_invoices['total'];
                                    $result_total_invoices = mysqli_query($con, "SELECT count(*) as total from billing_invoice");
                                    $data_total_invoices = mysqli_fetch_assoc($result_total_invoices);
                                    $total_invoices = $data_total_invoices['total'];
                                    $paid_invoices = $total_invoices - $pending_invoices;
                                    echo $pending_invoices;
                                    ?>
                                 </h3>
                              </div>
                              <div style="flex: 1; display: flex; justify-content: flex-end;">
                                 <canvas id="pendingInvoicesChart" style="height: 150px; width: 100%;"></canvas>
                              </div>
                           </div>
                        </div>
                     </div>
                     <div class="col-lg-4 col-md-6 card-3-in-row">
                        <div class="card">
                           <div class="card-body d-flex align-items-center justify-content-between">
                              <div>
                                 <div class="theme-avtar bg-danger"><i class="ti ti-report-money"></i></div>
                                 <p class="text-muted text-sm mt-4 mb-2">Invoices</p>
                                 <a href="invoice_billings.php?status=1&due_date=30">
                                    <h6 class="mb-3">Due In <small>(next 30 days)</small></h6>
                                 </a>
                                 <h3 class="mb-0">
                                    <?php
                                    $todaydate = date('Y-m-d');
                                    $days = '+ 30 days';
                                    $nextdate = date('Y-m-d', strtotime($todaydate . $days));
                                    $result_due_invoices = mysqli_query($con, "SELECT count(*) as total from billing_invoice where invoice_status='1' AND due_date BETWEEN '$todaydate' AND '$nextdate'");
                                    $data_due_invoices = mysqli_fetch_assoc($result_due_invoices);
                                    $due_invoices = $data_due_invoices['total'];
                                    echo $due_invoices;
                                    ?>
                                 </h3>
                              </div>
                              <div style="flex: 1; display: flex; justify-content: flex-end;">
                                 <canvas id="dueInvoicesChart" style="height: 150px; width: 100%;"></canvas>
                              </div>
                           </div>
                        </div>
                     </div>
                  <?php } ?>

                  <?php
                  $user_type = $_SESSION['user_type'];
                  $user_id = $_SESSION['user_id'];
                  $base_query = "SELECT 
                                (SELECT COUNT(*) FROM projects) AS total_projects,
                                (SELECT COUNT(*) FROM projects WHERE project_status = 'open') AS open_projects,
                                (SELECT COUNT(*) FROM projects WHERE project_status = 'completed') AS completed_projects,
                                (SELECT COUNT(*) FROM projects WHERE project_invoices IS NOT NULL AND project_invoices <> '') AS project_invoices";
                  if ($user_type != '1' && $user_type != '2') {
                     $base_query = "SELECT 
                                    (SELECT COUNT(*) FROM projects WHERE assign_to = '$user_id') AS total_projects,
                                    (SELECT COUNT(*) FROM projects WHERE project_status = 'open' AND assign_to = '$user_id') AS open_projects,
                                    (SELECT COUNT(*) FROM projects WHERE project_status = 'completed' AND assign_to = '$user_id') AS completed_projects,
                                    (SELECT COUNT(*) FROM projects WHERE project_invoices IS NOT NULL AND project_invoices <> '' AND assign_to = '$user_id') AS project_invoices";
                  }
                  $result = mysqli_query($con, $base_query);
                  $data = mysqli_fetch_assoc($result);
                  ?>
                  <div class="col-lg-4 col-md-6 card-3-in-row">
                     <div class="card">
                        <div class="card-body d-flex align-items-center justify-content-between">
                           <div>
                              <div class="theme-avtar bg-primary"><i class="ti ti-briefcase"></i></div>
                              <p class="text-muted text-sm mt-4 mb-2">Total</p>
                              <a href="show_projects.php">
                                 <h6 class="mb-3">Total Projects</h6>
                              </a>
                              <h3 class="mb-0"><?php echo $data['total_projects']; ?></h3>
                           </div>
                           <div style="flex: 1; display: flex; justify-content: flex-end;">
                              <canvas id="totalProjectsChart" style="height: 150px; width: 100%;"></canvas>
                           </div>
                        </div>
                     </div>
                  </div>
                  <div class="col-lg-4 col-md-6 card-3-in-row">
                     <div class="card">
                        <div class="card-body d-flex align-items-center justify-content-between">
                           <div>
                              <div class="theme-avtar bg-info"><i class="ti ti-folder"></i></div>
                              <p class="text-muted text-sm mt-4 mb-2">Total</p>
                              <a href="show_projects.php">
                                 <h6 class="mb-3">Open Projects</h6>
                              </a>
                              <h3 class="mb-0"><?php echo $data['open_projects']; ?></h3>
                           </div>
                           <div style="flex: 1; display: flex; justify-content: flex-end;">
                              <canvas id="openProjectsChart" style="height: 150px; width: 100%;"></canvas>
                           </div>
                        </div>
                     </div>
                  </div>
                  <div class="col-lg-4 col-md-6 card-3-in-row">
                     <div class="card">
                        <div class="card-body d-flex align-items-center justify-content-between">
                           <div>
                              <div class="theme-avtar bg-success"><i class="ti ti-check"></i></div>
                              <p class="text-muted text-sm mt-4 mb-2">Total</p>
                              <a href="show_projects.php">
                                 <h6 class="mb-3">Completed Projects</h6>
                              </a>
                              <h3 class="mb-0"><?php echo $data['completed_projects']; ?></h3>
                           </div>
                           <div style="flex: 1; display: flex; justify-content: flex-end;">
                              <canvas id="completedProjectsChart" style="height: 150px; width: 100%;"></canvas>
                           </div>
                        </div>
                     </div>
                  </div>
                  <?php if ($user_type == '1' || $user_type == '2') { ?>
                     <div class="col-lg-4 col-md-6 card-3-in-row">
                        <div class="card">
                           <div class="card-body d-flex align-items-center justify-content-between">
                              <div>
                                 <div class="theme-avtar bg-warning"><i class="ti ti-file-invoice"></i></div>
                                 <p class="text-muted text-sm mt-4 mb-2">Total</p>
                                 <a href="show_projects.php">
                                    <h6 class="mb-3">Project Invoices</h6>
                                 </a>
                                 <h3 class="mb-0"><?php echo $data['project_invoices']; ?></h3>
                              </div>
                              <div style="flex: 1; display: flex; justify-content: flex-end;">
                                 <canvas id="projectInvoicesChart" style="height: 150px; width: 100%;"></canvas>
                              </div>
                           </div>
                        </div>
                     </div>
                  <?php } ?>
               </div>
            </div>

            <!-- Right Column - Calendar and Client Reminders -->
            <div class="col-lg-4">

               <!-- ══ UPGRADED CALENDAR CARD ══ -->
               <div class="cal-card" id="calendarCard">
                  <div class="cal-card-header">
                     <div class="cal-card-title">
                        <i class="ti ti-calendar"></i> Reminder Calendar
                     </div>
                     <button class="cal-today-btn" id="todayBtn">Today</button>
                  </div>

                  <div class="cal-body">
                     <div class="cal-nav">
                        <button class="cal-nav-btn" id="prevMonthBtn"><i class="ti ti-chevron-left"></i></button>
                        <span class="cal-month-title" id="calendarTitle"></span>
                        <button class="cal-nav-btn" id="nextMonthBtn"><i class="ti ti-chevron-right"></i></button>
                     </div>
                     <div class="cal-grid" id="calendarGrid"></div>
                     <div class="cal-legend">
                        <div class="cal-legend-item">
                           <div class="cal-legend-dot" style="background:#1A1F2E;"></div> Today
                        </div>
                        <div class="cal-legend-item">
                           <div class="cal-legend-dot" style="background:#E85D56;"></div> Has Reminder
                        </div>
                     </div>
                  </div>

                  <!-- Inline popup overlay — sits inside the card -->
                  <div class="cal-popup-backdrop" id="calPopupBackdrop">
                     <div class="cal-popup">
                        <div class="cal-popup-hdr">
                           <span class="cal-popup-date" id="calPopupDate"></span>
                           <button class="cal-popup-close" id="calPopupClose" title="Close">×</button>
                        </div>
                        <div class="cal-popup-items" id="calPopupItems"></div>
                        <div class="cal-popup-actions">
                           <button class="cal-popup-btn snooze" id="calSnoozeBtn">Snooze 5 min</button>
                           <button class="cal-popup-btn dismiss" id="calDismissBtn">Dismiss</button>
                           <button class="cal-popup-btn" id="calCloseBtn">Close</button>
                        </div>
                     </div>
                  </div>
               </div>
               <!-- ══ /UPGRADED CALENDAR CARD ══ -->

            </div>
         </div>
      </div>
   </div>

   <?php include "includes/footer.php"; ?>
   <?php include "includes/scripts.php"; ?>
   <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

   <script type="text/javascript">
      <?php if ($user_type == '1' || $user_type == '2') { ?>
         var ctxActiveClients = document.getElementById('activeClientsChart').getContext('2d');
         new Chart(ctxActiveClients, {
            type: 'doughnut',
            data: {
               labels: ['Active', 'Inactive'],
               datasets: [{ data: [<?php echo $active_clients; ?>, <?php echo $inactive_clients; ?>], backgroundColor: ['#007bff', '#dc3545'] }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { font: { size: 10 } } } } }
         });

         var ctxActiveStaff = document.getElementById('activeStaffChart').getContext('2d');
         new Chart(ctxActiveStaff, {
            type: 'doughnut',
            data: {
               labels: ['Active', 'Inactive'],
               datasets: [{ data: [<?php echo $active_staff; ?>, <?php echo $inactive_staff; ?>], backgroundColor: ['#17a2b8', '#ffc107'] }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { font: { size: 10 } } } } }
         });

         var ctxPendingInvoices = document.getElementById('pendingInvoicesChart').getContext('2d');
         new Chart(ctxPendingInvoices, {
            type: 'doughnut',
            data: {
               labels: ['Pending', 'Paid'],
               datasets: [{ data: [<?php echo $pending_invoices; ?>, <?php echo $paid_invoices; ?>], backgroundColor: ['#ffc107', '#28a745'] }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { font: { size: 10 } } } } }
         });

         var ctxDueInvoices = document.getElementById('dueInvoicesChart').getContext('2d');
         new Chart(ctxDueInvoices, {
            type: 'bar',
            data: {
               labels: ['Total Pending', 'Due in 30 Days'],
               datasets: [{ label: 'Invoices', data: [<?php echo $pending_invoices; ?>, <?php echo $due_invoices; ?>], backgroundColor: ['#dc3545', '#6f42c1'] }]
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { font: { size: 10 } } }, x: { ticks: { font: { size: 10 } } } }, plugins: { legend: { display: false } } }
         });
      <?php } ?>

      var ctxTotalProjects = document.getElementById('totalProjectsChart').getContext('2d');
      new Chart(ctxTotalProjects, {
         type: 'pie',
         data: {
            labels: ['Open', 'Completed'],
            datasets: [{ data: [<?php echo $data['open_projects']; ?>, <?php echo $data['completed_projects']; ?>], backgroundColor: ['#17a2b8', '#28a745'] }]
         },
         options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { font: { size: 10 } } } } }
      });

      var ctxOpenProjects = document.getElementById('openProjectsChart').getContext('2d');
      new Chart(ctxOpenProjects, {
         type: 'doughnut',
         data: {
            labels: ['Open', 'Others'],
            datasets: [{ data: [<?php echo $data['open_projects']; ?>, <?php echo $data['total_projects'] - $data['open_projects']; ?>], backgroundColor: ['#17a2b8', '#6c757d'] }]
         },
         options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { font: { size: 10 } } } } }
      });

      var ctxCompletedProjects = document.getElementById('completedProjectsChart').getContext('2d');
      new Chart(ctxCompletedProjects, {
         type: 'doughnut',
         data: {
            labels: ['Completed', 'Others'],
            datasets: [{ data: [<?php echo $data['completed_projects']; ?>, <?php echo $data['total_projects'] - $data['completed_projects']; ?>], backgroundColor: ['#28a745', '#6c757d'] }]
         },
         options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { font: { size: 10 } } } } }
      });

      <?php if ($user_type == '1' || $user_type == '2') { ?>
         var ctxProjectInvoices = document.getElementById('projectInvoicesChart').getContext('2d');
         new Chart(ctxProjectInvoices, {
            type: 'doughnut',
            data: {
               labels: ['Invoiced', 'Non-Invoiced'],
               datasets: [{ data: [<?php echo $data['project_invoices']; ?>, <?php echo $data['total_projects'] - $data['project_invoices']; ?>], backgroundColor: ['#ffc107', '#6c757d'] }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { font: { size: 10 } } } } }
         });
      <?php } ?>

      $(document).ready(function () {
         $('#remindersTable').DataTable({
            responsive: true, pageLength: 5, lengthChange: false, searching: false, order: [[3, 'asc']]
         });
      });
   </script>

   <script>
      $(document).ready(function () {
         let searchTimeout;
         const minSearchLength = 2;

         $('#globalSearchInput').on('input', function () {
            const searchTerm = $(this).val().trim();
            clearTimeout(searchTimeout);
            if (searchTerm.length < minSearchLength) { $('#searchResultsContainer').hide(); return; }
            searchTimeout = setTimeout(function () { performGlobalSearch(); }, 300);
         });

         $('#globalSearchBtn').click(function () { performGlobalSearch(); });
         $('#globalSearchInput').keypress(function (e) { if (e.which == 13) { clearTimeout(searchTimeout); performGlobalSearch(); } });
         $('#closeSearchResults').click(function () { $('#searchResultsContainer').hide(); });
         $(document).click(function (event) {
            if (!$(event.target).closest('.global-search-container').length) { $('#searchResultsContainer').hide(); }
         });

         function performGlobalSearch() {
            const searchTerm = $('#globalSearchInput').val().trim();
            if (searchTerm === '') { $('#searchResultsContainer').hide(); return; }
            $('#searchResultsContent').html('<div class="text-center p-3"><i class="fas fa-spinner fa-spin"></i> Searching...</div>');
            $('#searchResultsContainer').show();
            $.ajax({
               url: 'globel_search.php', type: 'GET', data: { q: searchTerm }, dataType: 'json',
               success: function (response) { displaySearchResults(response); },
               error: function () { $('#searchResultsContent').html('<div class="no-results">Error occurred while searching. Please try again.</div>'); }
            });
         }

         function displaySearchResults(results) {
            let html = '';
            if (Object.keys(results).length === 0) {
               html = '<div class="no-results">No results found for your search.</div>';
            } else {
               const typeIcons = { 'Clients': 'ti ti-user', 'Invoices': 'ti ti-file-invoice-dollar', 'Projects': 'ti ti-briefcase', 'Quotations': 'ti ti-file-invoice', 'Payments': 'ti ti-money-check-alt', 'Staff': 'ti ti-users' };
               for (const [type, items] of Object.entries(results)) {
                  html += `<div class="result-section"><div class="result-section-title"><i class="${typeIcons[type] || 'ti ti-file'}"></i> ${type} (${items.length})</div>`;
                  items.forEach(item => {
                     html += `<div class="result-item" onclick="redirectToPage('${item.link}')"><div class="result-title">${item.title}</div><div class="result-subtitle">${item.subtitle}</div></div>`;
                  });
                  html += '</div>';
               }
            }
            $('#searchResultsContent').html(html);
         }

         window.redirectToPage = function (url) { window.location.href = url; }
      });
   </script>

   <script>
      const checkbox = document.getElementById('show');
      const box = document.getElementById('box');
      if (checkbox && box) {
         checkbox.addEventListener('click', function handleClick() {
            if (checkbox.checked) { box.style.display = 'none'; } else { box.style.display = 'block'; }
         });
      }

      /* Repeating typewriter placeholder */
      const txt = "Type to search (min. 2 characters) for clients, invoices, projects, quotations (by Sr#), payments, staff...";
      const inp = document.getElementById('globalSearchInput');
      let idx = 0, tId = null;
      function type() {
         inp.setAttribute('placeholder', txt.slice(0, ++idx));
         if (idx < txt.length) { tId = setTimeout(type, 45); } else { setTimeout(reset, 5000); }
      }
      function reset() { idx = 0; inp.setAttribute('placeholder', ''); type(); }
      type();
      inp.addEventListener('focus', kill, { once: true });
      inp.addEventListener('input', kill, { once: true });
      function kill() { clearTimeout(tId); idx = txt.length; }
   </script>

   <!-- ══════════════════════════════════════════════
        UPGRADED CALENDAR + REMINDER ENGINE
   ══════════════════════════════════════════════ -->
   <script>
      document.addEventListener('DOMContentLoaded', function () {

         /* PHP data */
         const allReminders = <?php echo json_encode($all_reminders); ?>;
         const currentUserId = <?php echo (int) $user_id; ?>;
         const SNOOZE_DELAY_MS = 5 * 60 * 1000;
         const reminderStorageKeyPrefix = `calendarReminderState_${currentUserId}_`;

         /* Build & append runtime popup (original behaviour preserved) */
         const runtimeReminderPopup = buildRuntimeReminderPopup();
         let runtimeReminderOpen = false;
         let reminderWakeTimer = null;

         /* Index reminders by YYYY-MM-DD */
         const remindersByDate = {};
         allReminders.forEach(r => {
            const d = r.reminder_date.split(' ')[0];
            if (!remindersByDate[d]) remindersByDate[d] = [];
            remindersByDate[d].push(r);
         });

         /* Calendar state */
         let currentDate = new Date();
         let selectedDate = null;
         let popupReminder = null;

         /* Elements */
         const calendarTitle = document.getElementById('calendarTitle');
         const calendarGrid = document.getElementById('calendarGrid');
         const calBackdrop = document.getElementById('calPopupBackdrop');
         const calPopupDate = document.getElementById('calPopupDate');
         const calPopupItems = document.getElementById('calPopupItems');

         /* Nav buttons */
         document.getElementById('prevMonthBtn').addEventListener('click', () => { currentDate.setMonth(currentDate.getMonth() - 1); renderCalendar(); });
         document.getElementById('nextMonthBtn').addEventListener('click', () => { currentDate.setMonth(currentDate.getMonth() + 1); renderCalendar(); });
         document.getElementById('todayBtn').addEventListener('click', () => { currentDate = new Date(); renderCalendar(); });

         /* ── Helpers ── */
         function fmtDate(d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
         function parseReminderTimestamp(s) { return new Date(s.replace(' ', 'T')).getTime(); }
         function formatTime(s) { return new Date(parseReminderTimestamp(s)).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }); }
         function formatDateForDisplay(d) { return d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }); }
         function escapeHtml(s) { return String(s ?? '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m])); }
         function typeColor(t) { return t === 'Call' ? '#4F8EF7' : t === 'Meeting' ? '#22C9A2' : '#8896AA'; }

         function isReminderForToday(r) {
            const d = new Date(parseReminderTimestamp(r.reminder_date)), n = new Date();
            return d.getFullYear() === n.getFullYear() && d.getMonth() === n.getMonth() && d.getDate() === n.getDate();
         }

         /* ── localStorage state ── */
         function getReminderState(r) {
            const key = reminderStorageKeyPrefix + r.reminder_id;
            let state = {};
            try { state = JSON.parse(localStorage.getItem(key) || '{}'); } catch (e) { }
            const dueTs = parseReminderTimestamp(r.reminder_date);
            if (!state.nextTriggerAt || Number.isNaN(Number(state.nextTriggerAt))) state.nextTriggerAt = dueTs;
            if (!state.snoozedUntil || Number.isNaN(Number(state.snoozedUntil))) state.snoozedUntil = 0;
            if (Number(state.snoozedUntil) > Number(state.nextTriggerAt)) state.nextTriggerAt = Number(state.snoozedUntil);
            if (typeof state.stopped !== 'boolean') state.stopped = false;
            return state;
         }
         function setReminderState(id, state) { localStorage.setItem(reminderStorageKeyPrefix + id, JSON.stringify(state)); }
         function getActiveReminders(arr) { return (arr || []).filter(r => !getReminderState(r).stopped); }

         /* ══════════════════════
            CALENDAR RENDER
         ══════════════════════ */
         function renderCalendar() {
            const y = currentDate.getFullYear(), m = currentDate.getMonth();
            const names = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
            calendarTitle.textContent = names[m] + ' ' + y;
            calendarGrid.innerHTML = '';

            ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'].forEach(d => {
               const h = document.createElement('div'); h.className = 'cal-dh'; h.textContent = d; calendarGrid.appendChild(h);
            });

            const firstDay = new Date(y, m, 1).getDay();
            const daysInMonth = new Date(y, m + 1, 0).getDate();
            const prevDays = new Date(y, m, 0).getDate();
            const today = new Date();

            for (let i = firstDay - 1; i >= 0; i--)  addCell(prevDays - i, true, new Date(y, m - 1, prevDays - i), today);
            for (let d = 1; d <= daysInMonth; d++)  addCell(d, false, new Date(y, m, d), today);
            const trailing = 35 - (calendarGrid.children.length - 7);
            for (let d = 1; d <= trailing; d++)     addCell(d, true, new Date(y, m + 1, d), today);
         }

         function addCell(d, other, date, today) {
            const el = document.createElement('div');
            el.className = 'cal-day';
            if (other) { el.classList.add('other'); el.textContent = d; calendarGrid.appendChild(el); return; }

            if (date.toDateString() === today.toDateString()) el.classList.add('today');
            if (selectedDate && date.toDateString() === selectedDate.toDateString()) el.classList.add('selected');
            el.textContent = d;

            const ds = fmtDate(date);
            const rems = getActiveReminders(remindersByDate[ds]);
            if (rems.length) el.classList.add('has-rem');

            el.addEventListener('click', () => {
               /* Toggle off if same day clicked twice */
               if (selectedDate && date.toDateString() === selectedDate.toDateString() && calBackdrop.classList.contains('open')) {
                  closeCalPopup(); selectedDate = null; renderCalendar(); return;
               }
               selectedDate = date; renderCalendar();
               if (rems.length) openCalPopup(rems, date);
            });

            calendarGrid.appendChild(el);
         }

         /* ══════════════════════
            INLINE POPUP
         ══════════════════════ */
         function openCalPopup(rems, date) {
            calPopupDate.textContent = date.toLocaleDateString('en-US', { weekday: 'short', month: 'long', day: 'numeric' });
            calPopupItems.innerHTML = '';
            popupReminder = rems[0];

            rems.forEach(r => {
               const div = document.createElement('div');
               div.className = 'cal-popup-item';
               div.innerHTML = `
                  <div class="cal-popup-item-row">
                     <div class="cal-popup-dot" style="background:${typeColor(r.reminder_type)};"></div>
                     <div class="cal-popup-title">${escapeHtml(r.reminder_title)}</div>
                  </div>
                  <div class="cal-popup-client">
                     <a href="client_details.php?id=${r.client_id}&status=1" style="color:inherit;text-decoration:none;" onclick="event.stopPropagation()">${escapeHtml(r.client_name)}</a>
                  </div>
                  <div class="cal-popup-time">${formatTime(r.reminder_date)}</div>
                  ${r.reminder_description ? `<div class="cal-popup-desc">${escapeHtml(r.reminder_description)}</div>` : ''}
               `;
               calPopupItems.appendChild(div);
            });

            calBackdrop.classList.add('open');
         }

         function closeCalPopup() { calBackdrop.classList.remove('open'); popupReminder = null; }

         document.getElementById('calPopupClose').addEventListener('click', closeCalPopup);
         document.getElementById('calCloseBtn').addEventListener('click', closeCalPopup);
         calBackdrop.addEventListener('click', e => { if (e.target === calBackdrop) closeCalPopup(); });

         document.getElementById('calSnoozeBtn').addEventListener('click', () => {
            if (!popupReminder) return;
            const state = getReminderState(popupReminder), sn = Date.now() + SNOOZE_DELAY_MS;
            state.lastTriggeredAt = Date.now(); state.nextTriggerAt = sn; state.snoozedUntil = sn; state.stopped = false;
            setReminderState(popupReminder.reminder_id, state);
            closeCalPopup(); renderCalendar(); scheduleNextDueReminderCheck();
         });

         document.getElementById('calDismissBtn').addEventListener('click', () => {
            if (!popupReminder) return;
            const state = getReminderState(popupReminder);
            state.lastTriggeredAt = Date.now(); state.snoozedUntil = 0; state.stopped = true;
            setReminderState(popupReminder.reminder_id, state);
            closeCalPopup(); renderCalendar(); scheduleNextDueReminderCheck();
         });

         /* ══════════════════════════════════
            RUNTIME AUTO-DUE POPUP (original)
         ══════════════════════════════════ */
         function buildRuntimeReminderPopup() {
            const wrapper = document.createElement('div');
            wrapper.className = 'runtime-reminder-popup';
            wrapper.id = 'runtimeReminderPopup';
            wrapper.innerHTML = `
               <div class="runtime-reminder-popup-header" id="runtimeReminderHeader">Reminder Due</div>
               <div class="runtime-reminder-popup-body" id="runtimeReminderBody"></div>
               <div class="runtime-reminder-popup-actions">
                  <button type="button" class="btn btn-sm btn-warning"   id="runtimeReminderSnooze">Snooze 5 min</button>
                  <button type="button" class="btn btn-sm btn-danger"    id="runtimeReminderStop">Stop</button>
               </div>
            `;
            document.body.appendChild(wrapper);
            return wrapper;
         }

         function showRuntimeReminder(reminder, triggerAt, manualOpen = false) {
            runtimeReminderOpen = true;
            runtimeReminderPopup.querySelector('#runtimeReminderHeader').textContent = manualOpen ? 'Reminder Details' : 'Reminder Due';
            runtimeReminderPopup.querySelector('#runtimeReminderBody').innerHTML = `
               <div class="runtime-reminder-popup-row"><div class="runtime-reminder-label">Client</div><div class="runtime-reminder-value">${escapeHtml(reminder.client_name || '-')}</div></div>
               <div class="runtime-reminder-popup-row"><div class="runtime-reminder-label">Title</div><div class="runtime-reminder-value">${escapeHtml(reminder.reminder_title || '-')}</div></div>
               <div class="runtime-reminder-popup-row"><div class="runtime-reminder-label">Type</div><div class="runtime-reminder-value">${escapeHtml(reminder.reminder_type || '-')}</div></div>
               <div class="runtime-reminder-popup-row"><div class="runtime-reminder-label">Time</div><div class="runtime-reminder-value">${formatDateForDisplay(new Date(parseReminderTimestamp(reminder.reminder_date)))} ${formatTime(reminder.reminder_date)}</div></div>
               <div class="runtime-reminder-description">
                  <div class="runtime-reminder-description-label">Description</div>
                  <div class="runtime-reminder-description-text">${escapeHtml(reminder.reminder_description || 'No description')}</div>
               </div>
            `;
            runtimeReminderPopup.classList.add('is-visible');

            runtimeReminderPopup.querySelector('#runtimeReminderSnooze').onclick = () => {
               const state = getReminderState(reminder), sn = Date.now() + SNOOZE_DELAY_MS;
               state.lastTriggeredAt = Number(triggerAt) || Date.now(); state.nextTriggerAt = sn; state.snoozedUntil = sn; state.stopped = false;
               setReminderState(reminder.reminder_id, state);
               runtimeReminderPopup.classList.remove('is-visible'); runtimeReminderOpen = false; scheduleNextDueReminderCheck();
            };

            runtimeReminderPopup.querySelector('#runtimeReminderStop').onclick = () => {
               const state = getReminderState(reminder);
               state.lastTriggeredAt = Number(triggerAt) || Date.now(); state.snoozedUntil = 0; state.stopped = true;
               setReminderState(reminder.reminder_id, state);
               runtimeReminderPopup.classList.remove('is-visible'); runtimeReminderOpen = false; scheduleNextDueReminderCheck();
            };
         }

         function checkDueReminders() {
            if (runtimeReminderOpen) return;
            const now = Date.now();
            for (const reminder of allReminders) {
               if (!isReminderForToday(reminder)) continue;
               const state = getReminderState(reminder);
               const snoozedUntil = Number(state.snoozedUntil) || 0;
               if (now < snoozedUntil) continue;
               const triggerAt = Math.max(Number(state.nextTriggerAt), snoozedUntil);
               if (!Number.isFinite(triggerAt)) continue;
               if (state.stopped) continue;
               if (now >= triggerAt && Number(state.lastTriggeredAt) !== triggerAt) { showRuntimeReminder(reminder, triggerAt); return; }
            }
         }

         function scheduleNextDueReminderCheck() {
            if (reminderWakeTimer) { clearTimeout(reminderWakeTimer); reminderWakeTimer = null; }
            const now = Date.now(); let nextDueAt = null;
            for (const reminder of allReminders) {
               if (!isReminderForToday(reminder)) continue;
               const state = getReminderState(reminder); if (state.stopped) continue;
               const triggerAt = Number(state.nextTriggerAt); if (!Number.isFinite(triggerAt)) continue;
               if (nextDueAt === null || triggerAt < nextDueAt) nextDueAt = triggerAt;
            }
            if (nextDueAt === null) return;
            reminderWakeTimer = setTimeout(() => { checkDueReminders(); scheduleNextDueReminderCheck(); }, Math.max(0, nextDueAt - now) + 250);
         }

         /* Boot */
         renderCalendar();
         checkDueReminders();
         scheduleNextDueReminderCheck();
         setInterval(checkDueReminders, 5000);
      });
   </script>

   <!-- ===== CHAT ENGINE ===== -->
   <script>
      $(function () {
         if (typeof feather !== 'undefined') feather.replace();
         loadChatSystem();
         setInterval(updateChatNotifications, 30_000);

         function loadChatSystem() {
            $.get('chat_system.php', { action: 'load_chat_ui' })
               .done(html => { $('#chatSystemContainer').html(html); if (typeof feather !== 'undefined') feather.replace(); fixMissingIcons(); })
               .fail(() => showFallbackChat());
         }
         function updateChatNotifications() {
            $.get('chat_system.php', { action: 'get_unread_count' }, cnt => {
               const $b = $('#chatMenuBadge'); parseInt(cnt) > 0 ? $b.text(cnt).show() : $b.hide();
            });
         }
         function fixMissingIcons() {
            $('[data-feather]').each(function () { const $t = $(this); if ($t.is(':empty')) $t.html(getIconFallback($t.attr('data-feather'))); });
            $('.chat-system-container i:empty,.chat-system-container svg:empty').not('.fa,.bi').replaceWith('<i class="fas fa-comment"></i>');
         }
         function getIconFallback(n) {
            const m = { 'message-circle': '<i class="fas fa-comment"></i>', 'send': '<i class="fas fa-paper-plane"></i>', 'user': '<i class="fas fa-user"></i>', 'users': '<i class="fas fa-users"></i>', 'clock': '<i class="fas fa-clock"></i>', 'check': '<i class="fas fa-check"></i>', 'x': '<i class="fas fa-times"></i>', 'more-vertical': '<i class="fas fa-ellipsis-v"></i>', 'paperclip': '<i class="fas fa-paperclip"></i>', 'smile': '<i class="fas fa-smile"></i>', 'phone': '<i class="fas fa-phone"></i>', 'video': '<i class="fas fa-video"></i>', 'settings': '<i class="fas fa-cog"></i>', 'search': '<i class="fas fa-search"></i>' };
            return m[n] || '<i class="fas fa-comment"></i>';
         }
         function showFallbackChat() {
            $('#chatSystemContainer').html(`
               <div class="chat-fallback-container" style="position:fixed;bottom:20px;right:20px;width:350px;height:500px;background:#fff;border:1px solid #ddd;border-radius:10px;box-shadow:0 4px 12px rgba(0,0,0,.15);z-index:9999;display:flex;flex-direction:column;">
                  <div class="chat-header" style="background:#007bff;color:white;padding:15px;border-radius:10px 10px 0 0;display:flex;justify-content:space-between;align-items:center;">
                     <h5 style="margin:0"><i class="fas fa-comments"></i> Chat System</h5>
                     <button onclick='$("#chatSystemContainer").hide()' style="background:none;border:none;color:white;font-size:18px;cursor:pointer"><i class="fas fa-times"></i></button>
                  </div>
                  <div class="chat-body" style="flex:1;padding:15px;overflow-y:auto;">
                     <div class="text-center text-muted" style="margin-top:50px">
                        <i class="fas fa-exclamation-triangle" style="font-size:48px;margin-bottom:15px"></i>
                        <p>Chat system is loading…</p>
                        <button class="btn btn-sm btn-primary retry-chat-load"><i class="fas fa-redo"></i> Retry</button>
                     </div>
                  </div>
                  <div class="chat-footer" style="padding:10px;border-top:1px solid #eee">
                     <div style="display:flex;gap:10px">
                        <input type="text" placeholder="Type a message…" style="flex:1;padding:8px;border:1px solid #ddd;border-radius:5px">
                        <button style="background:#007bff;color:white;border:none;padding:8px 15px;border-radius:5px;cursor:pointer"><i class="fas fa-paper-plane"></i></button>
                     </div>
                  </div>
               </div>`);
         }
         $(document).on('click', '.retry-chat-load', loadChatSystem);
      });
      function toggleChat() { $('#chatSystemContainer').toggle(); }
   </script>
   <!-- ===== /CHAT ENGINE ===== -->
</body>

</html>
