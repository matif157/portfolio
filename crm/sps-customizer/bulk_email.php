<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

include "includes/db.php";
include "includes/auth.php";

$user_id = $_SESSION['user_id'];

/* =====================================================================
 *  BULK CLIENT EMAIL MODULE  (everything self-contained in bulk_email.php)
 * =====================================================================
 *  - Select multiple clients and email them in throttled batches.
 *  - Tracks who has been emailed, who failed, and who is remaining.
 *  - A per-request server cap + client-side batching keep the SMTP
 *    account from being flagged / blocked for bulk sending.
 * ===================================================================== */

/* Hard server-side ceiling: never send more than this many emails in a
 * single AJAX request, no matter what the browser asks for. This protects
 * the SMTP account even if the front-end is tampered with. */
const BULK_EMAIL_MAX_PER_REQUEST = 20;

/* Make sure the log table exists (best effort, ignore failures). */
function bulk_email_ensure_log_table($con)
{
   $sql = "CREATE TABLE IF NOT EXISTS bulk_email_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            member_id INT NULL,
            email VARCHAR(255) NULL,
            client_name VARCHAR(255) NULL,
            subject VARCHAR(255) NULL,
            status ENUM('sent','failed') NOT NULL DEFAULT 'failed',
            error_message TEXT NULL,
            sent_by INT NULL,
            sent_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
   @mysqli_query($con, $sql);
}

/* Build the WHERE clause for the client list from the search + active filters.
 * Shared by the paginated row loader and the "select all matching" id loader. */
function bulk_email_build_where($con, $q, $activeOnly)
{
   $where = ["prm_email IS NOT NULL AND TRIM(prm_email) <> ''"];
   if ($activeOnly) {
      $where[] = "status = 1";
   }
   $q = trim($q);
   if ($q !== '') {
      $esc = mysqli_real_escape_string($con, $q);
      if (ctype_digit($q)) {
         $where[] = "(member_id = '$esc'
                       OR name LIKE '%$esc%' OR lname LIKE '%$esc%'
                       OR bus_name LIKE '%$esc%' OR prm_email LIKE '%$esc%')";
      } else {
         // Each whitespace-separated word must match somewhere (AND of ORs).
         $parts = [];
         foreach (preg_split('/\s+/', $esc) as $word) {
            if ($word === '')
               continue;
            $parts[] = "(member_id LIKE '%$word%'
                          OR name LIKE '%$word%' OR mname LIKE '%$word%' OR lname LIKE '%$word%'
                          OR bus_name LIKE '%$word%' OR prm_email LIKE '%$word%')";
         }
         if ($parts) {
            $where[] = '(' . implode(' AND ', $parts) . ')';
         }
      }
   }
   return implode(' AND ', $where);
}

/* ---------------------------------------------------------------------
 *  AJAX: send one batch of emails. Returns JSON and exits before any HTML.
 * ------------------------------------------------------------------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'send_batch') {

   header('Content-Type: application/json; charset=utf-8');

   $respond = function ($ok, $payload = []) {
      echo json_encode(array_merge(['success' => $ok], $payload));
      exit;
   };

   // --- Validate input -------------------------------------------------
   $subject = trim((string) ($_POST['subject'] ?? ''));
   $body_raw = trim((string) ($_POST['message'] ?? ''));
   $ids_json = $_POST['ids'] ?? '[]';
   $ids = json_decode($ids_json, true);

   if ($subject === '' || $body_raw === '') {
      $respond(false, ['message' => 'Subject and message are both required.']);
   }
   if (!is_array($ids) || count($ids) === 0) {
      $respond(false, ['message' => 'No clients were provided for this batch.']);
   }

   // Sanitize + clamp to the per-request ceiling.
   $ids = array_values(array_unique(array_map('intval', $ids)));
   $ids = array_filter($ids, fn($v) => $v > 0);
   if (count($ids) > BULK_EMAIL_MAX_PER_REQUEST) {
      $respond(false, [
         'message' => 'Batch too large. Max ' . BULK_EMAIL_MAX_PER_REQUEST . ' emails per request.'
      ]);
   }

   bulk_email_ensure_log_table($con);

   // --- Configure the mailer once and reuse the SMTP connection -------
   require_once 'PHPMailer/PHPMailerAutoload.php';
   $mail = new PHPMailer;
   $mail->isSMTP();
   $mail->Host = 'smtp.office365.com';
   $mail->SMTPAuth = true;
   $mail->Username = 'invoice@syedpro.com';
   $mail->Password = 'bgtqpxznmghqwkrc';
   $mail->SMTPSecure = 'tls';
   $mail->Port = 587;
   $mail->CharSet = 'UTF-8';
   $mail->SMTPKeepAlive = true; // keep one connection open for the whole batch
   $mail->setFrom('invoice@syedpro.com', 'Syed Professional Services');
   $mail->isHTML(true);

   $body_html = nl2br(htmlspecialchars($body_raw, ENT_QUOTES, 'UTF-8'));

   $log_stmt = mysqli_prepare(
      $con,
      "INSERT INTO bulk_email_log
         (member_id, email, client_name, subject, status, error_message, sent_by, sent_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
   );

   $results = [];

   foreach ($ids as $mid) {
      // Fetch a fresh, trusted copy of the recipient.
      $q = mysqli_query($con, "SELECT member_id, name, mname, lname, prm_email
                                 FROM members WHERE member_id=" . (int) $mid . " LIMIT 1");
      $client = $q ? mysqli_fetch_assoc($q) : null;

      $name = $client
         ? trim(($client['name'] ?? '') . ' ' . ($client['mname'] ?? '') . ' ' . ($client['lname'] ?? ''))
         : ('#' . $mid);
      $email = $client['prm_email'] ?? '';

      $status = 'failed';
      $error = '';

      if (!$client) {
         $error = 'Client not found.';
      } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
         $error = 'No valid email address on file.';
      } else {
         try {
            $mail->clearAddresses();
            $mail->addAddress($email, $name);
            $mail->Subject = $subject;

            $mail->Body = '<div style="font-family:Segoe UI,Roboto,Arial,sans-serif;font-size:14px;color:#333;line-height:1.6;">'
               . '<p>Dear ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ',</p>'
               . '<div>' . $body_html . '</div>'
               . '<br><p style="color:#777;">Regards,<br>Syed Professional Services</p>'
               . '</div>';
            $mail->AltBody = $body_raw;

            if ($mail->send()) {
               $status = 'sent';
            } else {
               $error = $mail->ErrorInfo ?: 'Unknown SMTP error.';
            }
         } catch (\Throwable $e) {
            $error = $e->getMessage();
         }
      }

      // Persist to the log (best effort).
      if ($log_stmt) {
         mysqli_stmt_bind_param(
            $log_stmt,
            'isssssi',
            $mid,
            $email,
            $name,
            $subject,
            $status,
            $error,
            $user_id
         );
         @mysqli_stmt_execute($log_stmt);
      }

      $results[] = [
         'id' => $mid,
         'name' => $name,
         'email' => $email,
         'status' => $status,
         'error' => $error,
      ];
   }

   if ($log_stmt) {
      mysqli_stmt_close($log_stmt);
   }
   $mail->smtpClose();

   $respond(true, ['results' => $results]);
}

/* ---------------------------------------------------------------------
 *  AJAX: paginated/searchable client list (JSON). Only a small page of
 *  rows is ever sent, so the browser keeps a tiny DOM and stays fast even
 *  with tens of thousands of clients.
 * ------------------------------------------------------------------- */
if (($_GET['action'] ?? '') === 'load_clients') {
   header('Content-Type: application/json; charset=utf-8');

   $q = (string) ($_GET['q'] ?? '');
   $activeOnly = ($_GET['active'] ?? '') === '1';
   $where = bulk_email_build_where($con, $q, $activeOnly);

   // Make sure the log table exists so the "already emailed" flag below works.
   bulk_email_ensure_log_table($con);

   // One page of rows.
   $pageSize = 50;
   $page = max(1, (int) ($_GET['page'] ?? 1));
   $offset = ($page - 1) * $pageSize;

   $total = 0;
   $cres = mysqli_query($con, "SELECT COUNT(*) FROM members WHERE $where");
   if ($cres && ($cr = mysqli_fetch_row($cres))) {
      $total = (int) $cr[0];
   }

   $rows = [];
   $res = mysqli_query(
      $con,
      "SELECT member_id, name, mname, lname, bus_name, prm_email, status,
              EXISTS(SELECT 1 FROM bulk_email_log l
                      WHERE l.member_id = members.member_id AND l.status = 'sent') AS emailed
         FROM members WHERE $where
        ORDER BY name ASC, lname ASC
        LIMIT $offset, $pageSize"
   );
   if ($res) {
      while ($r = mysqli_fetch_assoc($res)) {
         $full = trim($r['name'] . ' ' . $r['mname'] . ' ' . $r['lname']);
         $rows[] = [
            'id' => (int) $r['member_id'],
            'name' => $full !== '' ? $full : ('#' . $r['member_id']),
            'bus' => trim((string) $r['bus_name']),
            'email' => $r['prm_email'],
            'active' => ((int) $r['status'] === 1) ? 1 : 0,
            'emailed' => ((int) $r['emailed'] === 1) ? 1 : 0,
         ];
      }
   }

   echo json_encode([
      'rows' => $rows,
      'total' => $total,
      'page' => $page,
      'pages' => (int) ceil($total / $pageSize),
      'pageSize' => $pageSize,
   ]);
   exit;
}

/* ---------------------------------------------------------------------
 *  Normal page render below.
 * ------------------------------------------------------------------- */

function getInitials($name)
{
   $words = preg_split('/\s+/', trim($name));
   $initials = '';
   foreach ($words as $word) {
      if ($word === '')
         continue;
      $initials .= strtoupper(substr($word, 0, 1));
      if (strlen($initials) >= 2)
         break;
   }
   return $initials !== '' ? $initials : '?';
}

$user_full_name = $_SESSION['fullname'];
$user_initials = getInitials($user_full_name);
$user_type = $_SESSION['user_type'];

/* Cheap counts only — the full (large) client list is fetched lazily via
 * the load_clients AJAX endpoint so the page shell paints instantly. */
$total_clients = 0;
$active_clients = 0;
$count_q = mysqli_query(
   $con,
   "SELECT COUNT(*) AS total, SUM(status = 1) AS active
      FROM members
     WHERE prm_email IS NOT NULL AND TRIM(prm_email) <> ''"
);
if ($count_q && ($cr = mysqli_fetch_assoc($count_q))) {
   $total_clients = (int) $cr['total'];
   $active_clients = (int) $cr['active'];
}

/* Recent send history for the activity panel. */
$history = [];
@bulk_email_ensure_log_table($con);
$hist_q = @mysqli_query(
   $con,
   "SELECT member_id, client_name, email, subject, status, error_message, sent_at
      FROM bulk_email_log
     ORDER BY id DESC
     LIMIT 50"
);
if ($hist_q) {
   while ($r = mysqli_fetch_assoc($hist_q)) {
      $history[] = $r;
   }
}
$sent_total = 0;
$hist_q2 = @mysqli_query($con, "SELECT COUNT(*) AS c FROM bulk_email_log WHERE status='sent'");
if ($hist_q2 && ($hr = mysqli_fetch_assoc($hist_q2)))
   $sent_total = (int) $hr['c'];
?>

<!DOCTYPE html>
<html lang="en" dir="">
<meta name="csrf-token" id="csrf-token" content="8c2VdHOgBJmeryjWyCs9cx1G8chmj75MpBN38dRd">

<head>
   <title>Syed Professional Services - Bulk Client Email</title>
   <?php include "includes/links.php"; ?>
   <style>
      /* Prevent a flash of unstyled content on reload: the module's scoped
         stylesheet is lower in the page (after the heavy scripts.php scripts),
         so keep the module hidden until that stylesheet has been parsed.
         Same selector specificity is used below, where the later rule wins. */
      .be-wrap { visibility: hidden; }
   </style>
</head>

<body class="theme-3">

   <div class="loader-bg">
      <div class="loader-track">
         <div class="loader-fill"></div>
      </div>
   </div>

   <?php include "includes/sidebar.php"; ?>
   <?php include "includes/header.php"; ?>

   <div class="dash-container">
      <div class="dash-content">
         <div id="beWrap" class="be-wrap">

            <!-- ===================== HERO HEADER ===================== -->
            <div class="be-hero">
               <div class="be-hero-left">
                  <div class="be-hero-icon"><i class="ti ti-mail-forward"></i></div>
                  <div>
                     <h3 class="be-hero-title">Bulk Client Email</h3>
                  </div>
               </div>
               <div class="be-hero-stats">
                  <div class="be-mini-stat">
                     <span class="be-mini-num"><?php echo $total_clients; ?></span>
                     <span class="be-mini-lbl">Emailable</span>
                  </div>
                  <div class="be-mini-stat">
                     <span class="be-mini-num"><?php echo $active_clients; ?></span>
                     <span class="be-mini-lbl">Active</span>
                  </div>
                  <div class="be-mini-stat">
                     <span class="be-mini-num"><?php echo $sent_total; ?></span>
                     <span class="be-mini-lbl">Sent ever</span>
                  </div>
               </div>
            </div>

            <div class="row g-3 be-eq-row">

               <!-- ===================== LEFT: client picker ===================== -->
               <div class="col-lg-7">
                  <div class="be-card">
                     <div class="be-card-head">
                        <div class="be-card-head-title">
                           <i class="ti ti-users"></i> Select Clients
                        </div>
                        <span class="be-chip" id="selectedCountBadge">0 selected</span>
                     </div>

                     <div class="be-toolbar">
                        <div class="be-search">
                           <i class="ti ti-search"></i>
                           <input type="text" id="clientSearch"
                              placeholder="Search name, business, client ID or email…">
                        </div>
                        <label class="be-switch">
                           <input type="checkbox" id="activeOnly">
                           <span>Active only</span>
                        </label>
                        <button type="button" class="be-btn be-btn-ghost" id="clearSelection">
                           <i class="ti ti-eraser"></i> Clear
                        </button>
                     </div>

                     <div class="be-list" id="clientList" data-total="<?php echo $total_clients; ?>">
                        <!-- Rows are injected here, one page at a time -->
                        <div id="clientRows"></div>

                        <!-- Loading skeleton (shown for the first page) -->
                        <div class="be-skeleton" id="clientSkeleton">
                           <?php for ($i = 0; $i < 9; $i++): ?>
                              <div class="be-skel-row">
                                 <span class="be-skel be-skel-check"></span>
                                 <span class="be-skel be-skel-avatar"></span>
                                 <span class="be-skel-main">
                                    <span class="be-skel be-skel-line lg"></span>
                                    <span class="be-skel be-skel-line sm"></span>
                                 </span>
                                 <span class="be-skel be-skel-pill"></span>
                              </div>
                           <?php endfor; ?>
                           <div class="be-skel-note"><span class="be-spinner"></span> Loading clients…</div>
                        </div>

                        <div class="be-empty d-none" id="noMatch">No clients match your search.</div>
                        <div class="be-empty d-none" id="loadError">
                           Could not load clients. <a href="#" id="retryLoad">Retry</a>
                        </div>

                        <!-- Infinite-scroll trigger + footer -->
                        <div id="scrollSentinel"></div>
                        <div class="be-list-foot d-none" id="listFoot"></div>
                     </div>
                  </div>
               </div>

               <!-- ===================== RIGHT: compose + progress ===================== -->
               <div class="col-lg-5">

                  <!-- Compose -->
                  <div class="be-card">
                     <div class="be-card-head">
                        <div class="be-card-head-title"><i class="ti ti-pencil"></i> Compose Message</div>
                     </div>
                     <div class="be-card-body">
                        <div class="be-field">
                           <label>Subject</label>
                           <input type="text" id="emailSubject" placeholder="e.g. Important update about your account">
                        </div>
                        <div class="be-field">
                           <label>Message</label>
                           <textarea id="emailMessage" rows="6"
                              placeholder="Write your message here…"></textarea>
                        </div>

                        <div class="be-limits">
                           <div class="be-limits-title"><i class="ti ti-shield-check"></i> Sending limits
                              <span>keeps your account from being blocked</span>
                           </div>
                           <div class="be-limits-grid">
                              <div class="be-field be-field-sm">
                                 <label>Emails / batch</label>
                                 <input type="number" id="batchSize" value="15" min="10"
                                    max="<?php echo BULK_EMAIL_MAX_PER_REQUEST; ?>">
                              </div>
                              <div class="be-field be-field-sm">
                                 <label>Pause / batch (sec)</label>
                                 <input type="number" id="batchPause" value="30" min="0" max="3600">
                              </div>
                           </div>
                           <div class="be-input-msg d-none" id="batchSizeMsg"></div>
                        </div>

                        <div class="be-actions">
                           <button type="button" class="be-btn be-btn-primary" id="startSendBtn">
                              <i class="ti ti-send"></i> Start Sending
                           </button>
                           <button type="button" class="be-btn be-btn-danger d-none" id="stopSendBtn">
                              <i class="ti ti-player-stop"></i> Stop after batch
                           </button>
                        </div>
                     </div>
                  </div>

                  <!-- Progress -->
                  <div class="be-card d-none" id="progressCard">
                     <div class="be-card-head">
                        <div class="be-card-head-title"><i class="ti ti-chart-bar"></i> Sending Progress</div>
                     </div>
                     <div class="be-card-body">
                        <div class="be-progress">
                           <div class="be-progress-bar" id="progressBar"><span>0%</span></div>
                        </div>

                        <div class="be-stat-grid">
                           <div class="be-stat be-stat-total">
                              <div class="be-stat-num" id="statTotal">0</div>
                              <div class="be-stat-lbl">Total</div>
                           </div>
                           <div class="be-stat be-stat-sent">
                              <div class="be-stat-num" id="statSent">0</div>
                              <div class="be-stat-lbl">Sent</div>
                           </div>
                           <div class="be-stat be-stat-failed">
                              <div class="be-stat-num" id="statFailed">0</div>
                              <div class="be-stat-lbl">Failed</div>
                           </div>
                           <div class="be-stat be-stat-remaining">
                              <div class="be-stat-num" id="statRemaining">0</div>
                              <div class="be-stat-lbl">Remaining</div>
                           </div>
                        </div>

                        <div class="be-status" id="statusLine"><i class="ti ti-info-circle"></i> Idle.</div>
                        <p class="be-hint"><i class="ti ti-arrow-down"></i> Detailed per-client results appear in the
                           table below.</p>
                     </div>
                  </div>

               </div>
            </div>

            <!-- ===================== Full-width detailed results ===================== -->
            <div class="be-card be-dt-card d-none" id="resultsCard">
               <div class="be-card-head">
                  <div class="be-card-head-title"><i class="ti ti-table"></i> Detailed Results</div>
                  <span class="be-chip" id="resultsCount">0 recipients</span>
               </div>
               <div class="be-card-body">
                  <div class="table-responsive">
                     <table id="resultsTable" class="be-results-table">
                        <thead>
                           <tr>
                              <th>#</th>
                              <th>Client</th>
                              <th>Email</th>
                              <th>Status</th>
                              <th>Detail</th>
                           </tr>
                        </thead>
                        <tbody id="resultsTableBody"></tbody>
                     </table>
                  </div>
               </div>
            </div>

            <!-- ===================== Full-width send history ===================== -->
            <?php if (!empty($history)): ?>
               <div class="be-card be-dt-card" id="historyCard">
                  <div class="be-card-head">
                     <div class="be-card-head-title"><i class="ti ti-history"></i> Recent Send History</div>
                     <span class="be-chip"><?php echo count($history); ?> records</span>
                  </div>
                  <div class="be-card-body">
                     <div class="table-responsive">
                        <table id="historyTable" class="be-results-table">
                           <thead>
                              <tr>
                                 <th>Client ID</th>
                                 <th>Client</th>
                                 <th>Email</th>
                                 <th>Subject</th>
                                 <th>Status</th>
                                 <th>Detail</th>
                                 <th>When</th>
                              </tr>
                           </thead>
                           <tbody>
                              <?php
                              $subjLimit = 45;
                              foreach ($history as $h):
                                 $hsent = ($h['status'] === 'sent');
                                 $subj = (string) $h['subject'];
                                 $isLong = mb_strlen($subj) > $subjLimit; ?>
                                 <tr>
                                    <td><?php echo $h['member_id'] !== null ? (int) $h['member_id'] : '—'; ?></td>
                                    <td><?php echo htmlspecialchars($h['client_name'] ?: '—'); ?></td>
                                    <td><?php echo htmlspecialchars($h['email']); ?></td>
                                    <td>
                                       <?php if ($isLong): ?>
                                          <span class="be-subj">
                                             <span class="be-subj-short"><?php echo htmlspecialchars(mb_substr($subj, 0, $subjLimit)); ?>…</span>
                                             <span class="be-subj-full" style="display:none"><?php echo htmlspecialchars($subj); ?></span>
                                             <a href="#" class="be-subj-toggle">See more</a>
                                          </span>
                                       <?php else: ?>
                                          <?php echo htmlspecialchars($subj); ?>
                                       <?php endif; ?>
                                    </td>
                                    <td><span class="be-pill <?php echo $hsent ? 'be-pill-on' : 'be-pill-off'; ?>">
                                          <?php echo $hsent ? 'Sent' : 'Failed'; ?></span></td>
                                    <td><?php echo htmlspecialchars($h['error_message'] ?: ($hsent ? 'Delivered' : '')); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($h['sent_at']))); ?></td>
                                 </tr>
                              <?php endforeach; ?>
                           </tbody>
                        </table>
                     </div>
                  </div>
               </div>
            <?php endif; ?>

         </div>
      </div>
   </div>

   <?php include "includes/footer.php"; ?>
   <?php include "includes/scripts.php"; ?>

   <!-- ===== DataTables Buttons (export) stack — same as Show Projects ===== -->
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
   <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
   <style>
      /* Export buttons row (brand navy) */
      .be-dt-card .dt-toolbar-buttons div.dt-buttons {
         display: flex;
         flex-wrap: wrap;
         width: 100%;
         gap: 8px;
         margin-bottom: 0;
      }

      .be-dt-card div.dt-buttons .dt-button {
         flex: 1 1 0;
         margin: 0;
         border-radius: 8px;
         padding: 8px 14px;
         font-weight: 500;
         font-size: .82rem;
         text-align: center;
         justify-content: center;
         background: #252161 !important;
         color: #fff !important;
         border: 1px solid #252161 !important;
      }

      .be-dt-card div.dt-buttons .dt-button:hover,
      .be-dt-card div.dt-buttons .dt-button:focus {
         background: #1a1745 !important;
         color: #fff !important;
         box-shadow: 0 2px 5px rgba(37, 33, 97, .35);
      }

      .be-dt-card div.dt-buttons .dt-button i {
         margin-right: 5px;
      }

      /* Column-visibility dropdown */
      .dt-button-collection {
         background: #fff !important;
         border: 1px solid #e3e3ec !important;
         border-radius: 8px !important;
         padding: 6px !important;
         box-shadow: 0 8px 22px rgba(37, 33, 97, .18) !important;
         min-width: 200px;
      }

      .dt-button-collection .dt-button {
         display: block !important;
         flex: none !important;
         width: 100% !important;
         margin: 2px 0 !important;
         text-align: left !important;
         background: #fff !important;
         color: #252161 !important;
         border: none !important;
         box-shadow: none !important;
         border-radius: 5px !important;
         padding: 7px 12px !important;
      }

      .dt-button-collection .dt-button:hover,
      .dt-button-collection .dt-button.active {
         background: #f2f1f8 !important;
         color: #252161 !important;
      }

      /* Search / length / info / pagination */
      .be-dt-card .dataTables_wrapper .dataTables_filter {
         text-align: right;
         margin: 0;
      }

      .be-dt-card .dataTables_wrapper .dataTables_filter input {
         border: 1px solid var(--be-line, #e7ecf1);
         border-radius: 8px;
         padding: 7px 12px;
         margin-left: 8px;
         outline: none;
         max-width: 260px;
         font-size: .85rem;
      }

      .be-dt-card .dataTables_wrapper .dataTables_filter input:focus {
         border-color: #252161;
         box-shadow: 0 0 0 3px rgba(37, 33, 97, .15);
      }

      .be-dt-card .dataTables_wrapper .dataTables_length select {
         border: 1px solid var(--be-line, #e7ecf1);
         border-radius: 8px;
         padding: 6px 26px 6px 10px;
         margin: 0 6px;
         outline: none;
         font-size: .85rem;
      }

      .be-dt-card .dataTables_wrapper .dataTables_info {
         color: #6b7785;
         font-size: .82rem;
         padding-top: 10px;
      }

      .be-dt-card .dataTables_wrapper .dataTables_paginate {
         padding-top: 8px;
      }

      .be-dt-card .dataTables_wrapper .dataTables_paginate .paginate_button {
         padding: 6px 11px !important;
         margin: 0 2px !important;
         border-radius: 8px !important;
         background: #f6f8fa !important;
         color: #1f2a37 !important;
         border: none !important;
         cursor: pointer;
      }

      .be-dt-card .dataTables_wrapper .dataTables_paginate .paginate_button.current,
      .be-dt-card .dataTables_wrapper .dataTables_paginate .paginate_button:hover:not(.disabled) {
         background: #252161 !important;
         color: #fff !important;
         box-shadow: none;
      }

      .be-dt-card .dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
         opacity: .45;
         cursor: default;
      }

      .be-dt-card table.dataTable thead th {
         cursor: pointer;
      }

      /* Kill the horizontal scrollbar: let the table fit its card instead. */
      .be-dt-card .table-responsive {
         overflow-x: visible;
      }

      .be-dt-card table.dataTable {
         width: 100% !important;
         table-layout: auto;
      }

      .be-dt-card table.dataTable td,
      .be-dt-card table.dataTable th {
         white-space: normal;
         word-break: break-word;
      }
   </style>
   <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
   <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
   <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
   <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
   <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
   <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
   <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
   <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>

   <!-- ===================== Scoped styles ===================== -->
   <style>
      .be-wrap {
         visibility: visible;
         --be-primary: #252161;
         --be-primary-d: #1a1745;
         --be-ink: #1f2a37;
         --be-muted: #6b7785;
         --be-line: #e7ecf1;
         --be-soft: #f6f8fa;
         color: var(--be-ink);
      }

      /* Hero */
      .be-hero {
         display: flex;
         flex-wrap: wrap;
         gap: 16px;
         justify-content: space-between;
         align-items: center;
         padding: 22px 24px;
         margin-bottom: 18px;
         border-radius: 16px;
         background: linear-gradient(120deg, #1a1745 0%, #252161 55%, #4b45b0 100%);
         color: #fff;
         box-shadow: 0 12px 28px rgba(47, 127, 23, 0.25);
      }

      .be-hero-left {
         display: flex;
         align-items: center;
         gap: 16px;
      }

      .be-hero-icon {
         width: 54px;
         height: 54px;
         border-radius: 14px;
         display: grid;
         place-items: center;
         font-size: 26px;
         background: rgba(255, 255, 255, .18);
         backdrop-filter: blur(2px);
      }

      .be-hero-title {
         margin: 0;
         font-weight: 700;
         font-size: 1.5rem;
         color: #fff !important;
      }

      .be-hero-sub {
         margin: 2px 0 0;
         opacity: .9;
         font-size: .9rem;
      }

      .be-hero-stats {
         display: flex;
         gap: 10px;
      }

      .be-mini-stat {
         text-align: center;
         min-width: 86px;
         padding: 10px 14px;
         border-radius: 12px;
         background: rgba(255, 255, 255, .16);
      }

      .be-mini-num {
         display: block;
         font-size: 1.4rem;
         font-weight: 700;
         line-height: 1;
      }

      .be-mini-lbl {
         font-size: .72rem;
         opacity: .9;
      }

      /* Equal-height top row: the Select Clients card and Compose card stretch
         to the same height on large screens. */
      @media (min-width: 992px) {
         .be-eq-row {
            align-items: stretch;
         }

         .be-eq-row>[class*="col-lg-"] {
            display: flex;
            flex-direction: column;
         }

         .be-eq-row>[class*="col-lg-"]>.be-card:first-child {
            flex: 1 1 auto;
         }
      }

      /* Card */
      .be-card {
         background: #fff;
         border: 1px solid var(--be-line);
         border-radius: 16px;
         box-shadow: 0 6px 18px rgba(16, 24, 40, .05);
         margin-bottom: 18px;
         overflow: hidden;
      }

      .be-card-head {
         display: flex;
         align-items: center;
         justify-content: space-between;
         padding: 16px 18px;
         border-bottom: 1px solid var(--be-line);
      }

      .be-card-head-title {
         font-weight: 700;
         font-size: 1rem;
         display: flex;
         align-items: center;
         gap: 8px;
      }

      .be-card-head-title i {
         color: var(--be-primary);
      }

      .be-card-body {
         padding: 18px;
      }

      .be-chip {
         background: rgba(37, 33, 97,.12);
         color: var(--be-primary-d);
         font-weight: 700;
         font-size: .78rem;
         padding: 6px 12px;
         border-radius: 999px;
      }

      /* Toolbar */
      .be-toolbar {
         display: flex;
         flex-wrap: wrap;
         gap: 10px;
         align-items: center;
         padding: 14px 18px;
         border-bottom: 1px solid var(--be-line);
      }

      .be-search {
         position: relative;
         flex: 1 1 220px;
      }

      .be-search i {
         position: absolute;
         left: 12px;
         top: 50%;
         transform: translateY(-50%);
         color: var(--be-muted);
      }

      .be-search input {
         width: 100%;
         padding: 10px 12px 10px 36px;
         border: 1px solid var(--be-line);
         border-radius: 10px;
         outline: none;
         font-size: .9rem;
      }

      .be-search input:focus {
         border-color: var(--be-primary);
         box-shadow: 0 0 0 3px rgba(37, 33, 97,.15);
      }

      .be-switch {
         display: inline-flex;
         align-items: center;
         gap: 6px;
         font-size: .85rem;
         color: var(--be-muted);
         margin: 0;
         cursor: pointer;
         user-select: none;
      }

      /* List */
      .be-list {
         max-height: 480px;
         overflow-y: auto;
         padding: 8px;
      }

      .be-row {
         display: flex;
         align-items: center;
         gap: 12px;
         padding: 10px 12px;
         border-radius: 12px;
         cursor: pointer;
         margin: 0;
         transition: background .12s ease;
      }

      .be-row:hover {
         background: var(--be-soft);
      }

      .be-row:has(.be-check:checked) {
         background: rgba(37, 33, 97,.10);
         box-shadow: inset 0 0 0 1px rgba(37, 33, 97,.35);
      }

      .be-check {
         width: 18px;
         height: 18px;
         accent-color: var(--be-primary);
         flex: 0 0 auto;
         cursor: pointer;
      }

      .be-avatar {
         width: 38px;
         height: 38px;
         border-radius: 50%;
         flex: 0 0 auto;
         display: grid;
         place-items: center;
         font-weight: 700;
         font-size: .8rem;
         color: var(--be-primary-d);
         background: rgba(37, 33, 97,.14);
      }

      .be-row-main {
         flex: 1 1 auto;
         min-width: 0;
      }

      .be-row-name {
         display: block;
         font-weight: 600;
         font-size: .9rem;
         white-space: nowrap;
         overflow: hidden;
         text-overflow: ellipsis;
      }

      .be-row-name small {
         color: var(--be-muted);
         font-weight: 500;
         margin-left: 6px;
      }

      .be-row-email {
         display: block;
         font-size: .8rem;
         color: var(--be-muted);
         white-space: nowrap;
         overflow: hidden;
         text-overflow: ellipsis;
      }

      .be-pill {
         font-size: .7rem;
         font-weight: 700;
         padding: 4px 9px;
         border-radius: 999px;
         flex: 0 0 auto;
      }

      .be-pill-on {
         background: rgba(40, 167, 69, .14);
         color: #1e7e34;
      }

      .be-pill-off {
         background: rgba(220, 53, 69, .12);
         color: #c1303f;
      }

      .be-pill-emailed {
         background: rgba(37, 33, 97, .10);
         color: #252161;
         margin-right: 6px;
         display: inline-flex;
         align-items: center;
         gap: 3px;
      }

      .be-subj-toggle {
         color: #252161;
         font-weight: 600;
         font-size: .78rem;
         text-decoration: none;
         margin-left: 4px;
         white-space: nowrap;
      }

      .be-subj-toggle:hover {
         text-decoration: underline;
      }

      .be-empty {
         text-align: center;
         color: var(--be-muted);
         padding: 28px 10px;
         font-size: .9rem;
      }

      /* ---- Loading skeleton + shimmer ---- */
      .be-skel-row {
         display: flex;
         align-items: center;
         gap: 12px;
         padding: 11px 12px;
      }

      .be-skel {
         display: inline-block;
         border-radius: 6px;
         background: #eef1f4;
         position: relative;
         overflow: hidden;
      }

      .be-skel::after {
         content: "";
         position: absolute;
         inset: 0;
         transform: translateX(-100%);
         background: linear-gradient(90deg, transparent, rgba(255, 255, 255, .85), transparent);
         animation: be-shimmer 1.2s infinite;
      }

      @keyframes be-shimmer {
         100% {
            transform: translateX(100%);
         }
      }

      .be-skel-check {
         width: 18px;
         height: 18px;
         border-radius: 5px;
         flex: 0 0 auto;
      }

      .be-skel-avatar {
         width: 38px;
         height: 38px;
         border-radius: 50%;
         flex: 0 0 auto;
      }

      .be-skel-main {
         flex: 1 1 auto;
         display: flex;
         flex-direction: column;
         gap: 7px;
      }

      .be-skel-line {
         height: 10px;
      }

      .be-skel-line.lg {
         width: 55%;
      }

      .be-skel-line.sm {
         width: 35%;
         height: 8px;
      }

      .be-skel-pill {
         width: 54px;
         height: 18px;
         border-radius: 999px;
         flex: 0 0 auto;
      }

      .be-skel-note {
         text-align: center;
         color: var(--be-muted);
         font-size: .82rem;
         padding: 14px 10px 6px;
         display: flex;
         align-items: center;
         justify-content: center;
         gap: 8px;
      }

      .be-spinner {
         width: 16px;
         height: 16px;
         border-radius: 50%;
         border: 2px solid rgba(37, 33, 97,.25);
         border-top-color: var(--be-primary);
         animation: be-spin .7s linear infinite;
         display: inline-block;
      }

      @keyframes be-spin {
         100% {
            transform: rotate(360deg);
         }
      }

      .be-list-foot {
         text-align: center;
         color: var(--be-muted);
         font-size: .78rem;
         padding: 10px;
         display: flex;
         align-items: center;
         justify-content: center;
         gap: 8px;
      }

      /* Fields */
      .be-field {
         margin-bottom: 14px;
      }

      .be-field label {
         display: block;
         font-weight: 600;
         font-size: .82rem;
         margin-bottom: 6px;
      }

      .be-field input,
      .be-field textarea {
         width: 100%;
         border: 1px solid var(--be-line);
         border-radius: 10px;
         padding: 10px 12px;
         font-size: .9rem;
         outline: none;
         resize: vertical;
      }

      .be-field input:focus,
      .be-field textarea:focus {
         border-color: var(--be-primary);
         box-shadow: 0 0 0 3px rgba(37, 33, 97,.15);
      }

      .be-field small {
         display: block;
         color: var(--be-muted);
         margin-top: 5px;
         font-size: .76rem;
      }

      /* Limits box */
      .be-limits {
         background: var(--be-soft);
         border: 1px dashed var(--be-line);
         border-radius: 12px;
         padding: 14px;
         margin: 4px 0 16px;
      }

      .be-limits-title {
         font-weight: 700;
         font-size: .85rem;
         display: flex;
         align-items: center;
         gap: 7px;
      }

      .be-limits-title i {
         color: var(--be-primary);
      }

      .be-limits-title span {
         font-weight: 500;
         color: var(--be-muted);
         font-size: .75rem;
      }

      .be-limits-grid {
         display: grid;
         grid-template-columns: 1fr 1fr;
         gap: 10px;
         margin-top: 10px;
      }

      .be-field-sm {
         margin-bottom: 0;
      }

      .be-limits-note {
         margin: 8px 0 0;
         font-size: .74rem;
         color: var(--be-muted);
      }

      .be-input-msg {
         margin: 10px 0 0;
         font-size: .76rem;
         font-weight: 600;
         color: #c1303f;
      }

      input.be-input-error {
         border-color: #e2475a !important;
         box-shadow: 0 0 0 3px rgba(226, 71, 90, .15) !important;
      }

      /* Buttons */
      .be-actions {
         display: flex;
         gap: 10px;
      }

      .be-btn {
         border: none;
         border-radius: 10px;
         padding: 10px 16px;
         font-weight: 600;
         font-size: .88rem;
         cursor: pointer;
         display: inline-flex;
         align-items: center;
         gap: 7px;
         transition: transform .1s ease, box-shadow .12s ease, background .12s ease;
      }

      .be-btn:active {
         transform: translateY(1px);
      }

      .be-btn-primary {
         flex: 1;
         justify-content: center;
         color: #fff;
         background: linear-gradient(120deg, var(--be-primary), var(--be-primary-d));
         box-shadow: 0 8px 18px rgba(37, 33, 97,.3);
      }

      .be-btn-primary:hover {
         box-shadow: 0 10px 22px rgba(37, 33, 97,.4);
      }

      .be-btn-primary:disabled {
         opacity: .6;
         cursor: not-allowed;
         box-shadow: none;
      }

      .be-btn-danger {
         flex: 1;
         justify-content: center;
         color: #fff;
         background: #e2475a;
      }

      .be-btn-ghost {
         background: #fff;
         border: 1px solid var(--be-line);
         color: var(--be-ink);
         padding: 8px 12px;
         font-size: .8rem;
      }

      .be-btn-ghost:hover {
         border-color: var(--be-primary);
         color: var(--be-primary-d);
      }

      /* Progress */
      .be-progress {
         height: 14px;
         border-radius: 999px;
         background: var(--be-soft);
         overflow: hidden;
         margin-bottom: 16px;
      }

      .be-progress-bar {
         height: 100%;
         width: 0%;
         border-radius: 999px;
         background: linear-gradient(90deg, var(--be-primary), #4b45b0);
         color: #fff;
         font-size: .68rem;
         font-weight: 700;
         display: flex;
         align-items: center;
         justify-content: flex-end;
         padding-right: 8px;
         transition: width .4s ease;
         white-space: nowrap;
      }

      .be-stat-grid {
         display: grid;
         grid-template-columns: repeat(4, 1fr);
         gap: 10px;
         margin-bottom: 14px;
      }

      .be-stat {
         text-align: center;
         padding: 12px 6px;
         border-radius: 12px;
         background: var(--be-soft);
      }

      .be-stat-num {
         font-size: 1.4rem;
         font-weight: 700;
         line-height: 1;
      }

      .be-stat-lbl {
         font-size: .72rem;
         color: var(--be-muted);
         margin-top: 4px;
      }

      .be-stat-sent {
         background: rgba(40, 167, 69, .1);
      }

      .be-stat-sent .be-stat-num {
         color: #1e7e34;
      }

      .be-stat-failed {
         background: rgba(220, 53, 69, .1);
      }

      .be-stat-failed .be-stat-num {
         color: #c1303f;
      }

      .be-stat-remaining {
         background: rgba(255, 171, 0, .12);
      }

      .be-stat-remaining .be-stat-num {
         color: #b97c00;
      }

      .be-status {
         background: var(--be-soft);
         border-radius: 10px;
         padding: 10px 12px;
         font-size: .82rem;
         color: var(--be-muted);
         margin-bottom: 14px;
         display: flex;
         align-items: center;
         gap: 7px;
      }

      .be-hint {
         margin: 12px 0 0;
         font-size: .8rem;
         color: var(--be-muted);
         display: flex;
         align-items: center;
         gap: 6px;
      }

      .be-pill-wait {
         background: rgba(255, 171, 0, .14);
         color: #b97c00;
      }

      /* ---- Detailed results table ---- */
      .be-results-table {
         width: 100%;
         border-collapse: collapse;
         font-size: .85rem;
      }

      .be-results-table thead th {
         text-align: left;
         font-weight: 700;
         font-size: .76rem;
         text-transform: uppercase;
         letter-spacing: .03em;
         color: var(--be-muted);
         padding: 10px 12px;
         border-bottom: 2px solid var(--be-line);
         cursor: pointer;
      }

      .be-results-table tbody td {
         padding: 10px 12px;
         border-bottom: 1px solid var(--be-line);
         vertical-align: middle;
      }

      .be-results-table tbody tr:hover {
         background: var(--be-soft);
      }

      /* simple-datatables controls (bundled lib uses the dataTable- prefix) */
      .be-dt-card .dataTable-top,
      .be-dt-card .dataTable-bottom {
         padding: 10px 2px;
         display: flex;
         flex-wrap: wrap;
         align-items: center;
         justify-content: space-between;
         gap: 10px;
         width: 100%;
         font-size: .82rem;
         color: var(--be-muted);
      }

      /* Force the search box and the pagination to the right edge, regardless
         of how the library sizes its inner wrappers. */
      .be-dt-card .dataTable-top .dataTable-search,
      .be-dt-card .dataTable-bottom .dataTable-pagination {
         margin-left: auto;
      }

      .be-dt-card .dataTable-top .dataTable-search {
         text-align: right;
      }

      .be-dt-card .dataTable-input,
      .be-dt-card .dataTable-selector {
         border: 1px solid var(--be-line);
         border-radius: 8px;
         padding: 7px 10px;
         outline: none;
         font-size: .85rem;
      }

      .be-dt-card .dataTable-input:focus,
      .be-dt-card .dataTable-selector:focus {
         border-color: var(--be-primary);
         box-shadow: 0 0 0 3px rgba(37, 33, 97,.15);
      }

      .be-dt-card .dataTable-pagination a {
         display: inline-block;
         padding: 6px 11px;
         margin: 0 2px;
         border-radius: 8px;
         background: var(--be-soft);
         color: var(--be-ink);
         text-decoration: none;
         font-size: .82rem;
      }

      .be-dt-card .dataTable-pagination .active a,
      .be-dt-card .dataTable-pagination a:hover {
         background: var(--be-primary);
         color: #fff;
      }

      @media (max-width: 575px) {
         .be-hero {
            flex-direction: column;
            align-items: flex-start;
         }

         .be-stat-grid {
            grid-template-columns: repeat(2, 1fr);
         }
      }
   </style>

   <!-- ===================== Controller (vanilla JS) ===================== -->
   <script>
      (function () {
         const MAX_PER_REQUEST = <?php echo BULK_EMAIL_MAX_PER_REQUEST; ?>;
         const MIN_PER_REQUEST = 10;
         const $ = (id) => document.getElementById(id);

         let stopRequested = false;
         let sending = false;

         // ---- Selection / paging state ----
         const selectedIds = new Set();   // member ids (as strings), kept across pages & searches
         const emailedSeeded = new Set(); // ids we've auto-ticked because they were already emailed
         const clientMeta = {};           // id -> {name, email} for nicer display
         let curQuery = '', curActive = false;
         let nextPage = 1, totalMatching = 0, loadedCount = 0;
         let loading = false, reqToken = 0;

         // ---- Small utils ----
         const esc = s => String(s ?? '').replace(/[&<>"']/g,
            m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m]));
         function initials(name) {
            let s = '';
            for (const w of String(name || '').trim().split(/\s+/)) {
               if (!w) continue;
               s += w[0].toUpperCase();
               if (s.length >= 2) break;
            }
            return s || '?';
         }
         const sleep = ms => new Promise(r => setTimeout(r, ms));
         function setStat(id, v) { $(id).textContent = v; }
         function updateBadge() {
            $('selectedCountBadge').textContent = selectedIds.size + ' selected';
         }

         // ---- Row rendering (client-side from JSON) ----
         function rowHtml(c) {
            const id = String(c.id);
            clientMeta[id] = { name: c.name, email: c.email };
            // Pre-tick clients we've already emailed before (from the send log),
            // but auto-seed each id only once so a manual untick sticks.
            if (c.emailed && !emailedSeeded.has(id)) {
               emailedSeeded.add(id);
               selectedIds.add(id);
            }
            const checked = selectedIds.has(id) ? 'checked' : '';
            const bus = c.bus ? '<small>' + esc(c.bus) + '</small>' : '';
            const pill = c.active
               ? '<span class="be-pill be-pill-on">Active</span>'
               : '<span class="be-pill be-pill-off">Inactive</span>';
            const emailedTag = c.emailed
               ? '<span class="be-pill be-pill-emailed" title="Already emailed"><i class="ti ti-mail-check"></i> Emailed</span>'
               : '';
            return '<label class="be-row">'
               + '<input class="be-check client-check" type="checkbox" value="' + id + '" ' + checked + '>'
               + '<span class="be-avatar">' + esc(initials(c.name)) + '</span>'
               + '<span class="be-row-main">'
               + '<span class="be-row-name">' + esc(c.name) + ' ' + bus + '</span>'
               + '<span class="be-row-email">' + esc(c.email) + '</span>'
               + '</span>' + emailedTag + pill + '</label>';
         }

         function renderFoot() {
            const foot = $('listFoot');
            foot.classList.remove('d-none');
            if (loading) {
               foot.innerHTML = '<span class="be-spinner"></span> Loading…';
            } else if (loadedCount < totalMatching) {
               foot.textContent = 'Showing ' + loadedCount + ' of ' + totalMatching + ' — scroll for more';
            } else if (totalMatching > 0) {
               foot.textContent = 'All ' + totalMatching + ' shown';
            } else {
               foot.classList.add('d-none');
            }
         }

         // ---- Page fetch (reset = new search, else append next page) ----
         async function fetchPage(reset) {
            if (loading) return;
            if (!reset && totalMatching !== 0 && loadedCount >= totalMatching) return;
            loading = true;
            const token = ++reqToken;
            $('loadError').classList.add('d-none');

            if (reset) {
               nextPage = 1; loadedCount = 0; totalMatching = 0;
               $('clientRows').innerHTML = '';
               $('noMatch').classList.add('d-none');
               $('listFoot').classList.add('d-none');
               $('clientSkeleton').classList.remove('d-none');
            } else {
               renderFoot();
            }

            try {
               const params = new URLSearchParams({
                  action: 'load_clients', q: curQuery, active: curActive ? '1' : '0', page: nextPage
               });
               const res = await fetch('bulk_email.php?' + params.toString(),
                  { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
               if (!res.ok) throw new Error('HTTP ' + res.status);
               const data = await res.json();
               if (token !== reqToken) return; // superseded by a newer request

               $('clientSkeleton').classList.add('d-none');
               totalMatching = data.total;
               $('clientRows').insertAdjacentHTML('beforeend', (data.rows || []).map(rowHtml).join(''));
               updateBadge(); // rows may have auto-ticked already-emailed clients
               loadedCount += (data.rows || []).length;
               nextPage++;
               if (!data.rows || data.rows.length === 0) totalMatching = loadedCount; // safety: stop paging

               $('noMatch').classList.toggle('d-none', totalMatching !== 0);
               loading = false;
               renderFoot();
               maybeAutoFill();
            } catch (e) {
               if (token !== reqToken) return;
               loading = false;
               $('clientSkeleton').classList.add('d-none');
               $('loadError').classList.remove('d-none');
               $('listFoot').classList.add('d-none');
            }
         }

         // Keep filling until the list is scrollable (so infinite scroll can kick in).
         function maybeAutoFill() {
            const list = $('clientList');
            if (loadedCount < totalMatching && list.scrollHeight <= list.clientHeight + 4) {
               fetchPage(false);
            }
         }

         // ---- Debounced search + active-only filter ----
         let searchTimer = null;
         function onFilterChange() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
               curQuery = $('clientSearch').value.trim();
               curActive = $('activeOnly').checked;
               fetchPage(true);
            }, 300);
         }
         $('clientSearch').addEventListener('input', onFilterChange);
         $('activeOnly').addEventListener('change', onFilterChange);

         // ---- Infinite scroll ----
         $('clientList').addEventListener('scroll', function () {
            if (loading || loadedCount >= totalMatching) return;
            if (this.scrollTop + this.clientHeight >= this.scrollHeight - 120) fetchPage(false);
         });

         // ---- Selection (delegated — rows are created/destroyed dynamically) ----
         $('clientList').addEventListener('change', e => {
            if (!e.target.classList.contains('client-check')) return;
            if (e.target.checked) selectedIds.add(e.target.value);
            else selectedIds.delete(e.target.value);
            updateBadge();
         });

         $('clearSelection').addEventListener('click', () => {
            selectedIds.clear();
            document.querySelectorAll('.client-check').forEach(cb => cb.checked = false);
            updateBadge();
         });

         document.addEventListener('click', e => {
            if (e.target && e.target.id === 'retryLoad') { e.preventDefault(); fetchPage(true); }
         });

         // See more / See less for long subjects in the history table.
         document.addEventListener('click', e => {
            const t = e.target.closest && e.target.closest('.be-subj-toggle');
            if (!t) return;
            e.preventDefault();
            const wrap = t.closest('.be-subj');
            if (!wrap) return;
            const shortEl = wrap.querySelector('.be-subj-short');
            const fullEl = wrap.querySelector('.be-subj-full');
            const showingFull = fullEl.style.display !== 'none';
            fullEl.style.display = showingFull ? 'none' : 'inline';
            shortEl.style.display = showingFull ? 'inline' : 'none';
            t.textContent = showingFull ? 'See more' : 'See less';
         });

         // ---- Detailed results table (full-width, with simple-datatables) ----
         let resultsDT = null;
         const resultMap = {};   // id -> {name, email, status, detail}
         let resultOrder = [];   // ids in send order

         function statusBadge(s) {
            if (s === 'Sent') return '<span class="be-pill be-pill-on">Sent</span>';
            if (s === 'Failed') return '<span class="be-pill be-pill-off">Failed</span>';
            return '<span class="be-pill be-pill-wait">Remaining</span>';
         }

         // ---- jQuery DataTables + export buttons (mirrors Show Projects) ----
         const jq = window.jQuery;
         function exportButtons(title) {
            return [
               { extend: 'copyHtml5', text: '<i class="fas fa-copy"></i> Copy', titleAttr: 'Copy', title: title },
               { extend: 'excelHtml5', text: '<i class="fas fa-file-excel"></i> Excel', titleAttr: 'Excel', title: title },
               { extend: 'csvHtml5', text: '<i class="fas fa-file-csv"></i> CSV', titleAttr: 'CSV', title: title },
               { extend: 'pdfHtml5', text: '<i class="fas fa-file-pdf"></i> PDF', titleAttr: 'PDF', title: title, orientation: 'landscape', pageSize: 'A4' },
               { extend: 'print', text: '<i class="fas fa-print"></i> Print', titleAttr: 'Print', title: title },
               { extend: 'colvis', text: '<i class="fas fa-columns"></i> Columns' }
            ];
         }
         function buildDataTable(selector, title, opts) {
            if (!jq || !jq.fn || !jq.fn.DataTable) return null;
            const $t = jq(selector);
            if (!$t.length) return null;
            if (jq.fn.DataTable.isDataTable($t)) { $t.DataTable().destroy(); }
            return $t.DataTable(Object.assign({
               dom: "<'row align-items-center mb-2 gx-2'<'col-md-auto col-12'l><'col-md dt-toolbar-buttons'B><'col-md-auto col-12'f>>" +
                  "rt" +
                  "<'row mt-2 align-items-center'<'col-md-5'i><'col-md-7'p>>",
               pageLength: 10,
               lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
               autoWidth: false,
               buttons: exportButtons(title)
            }, opts || {}));
         }

         function renderResultsTable() {
            const tbody = $('resultsTableBody');
            let html = '';
            resultOrder.forEach((id, i) => {
               const r = resultMap[id] || {};
               html += '<tr>'
                  + '<td>' + (i + 1) + '</td>'
                  + '<td>' + esc(r.name || ('#' + id)) + '</td>'
                  + '<td>' + esc(r.email || '') + '</td>'
                  + '<td>' + statusBadge(r.status) + '</td>'
                  + '<td>' + esc(r.detail || '') + '</td>'
                  + '</tr>';
            });

            // Rebuild cleanly: destroy any existing instance, swap rows, re-init.
            if (resultsDT) { try { resultsDT.destroy(); } catch (e) { } resultsDT = null; }
            tbody.innerHTML = html;
            $('resultsCount').textContent = resultOrder.length + ' recipient' + (resultOrder.length === 1 ? '' : 's');

            if (resultOrder.length) {
               resultsDT = buildDataTable('#resultsTable', 'Bulk Email Results', {
                  order: [],
                  language: { search: '_INPUT_', searchPlaceholder: 'Search recipients...', lengthMenu: '_MENU_' }
               });
            }
         }

         async function sendBatch(ids, subject, message) {
            const fd = new FormData();
            fd.append('action', 'send_batch');
            fd.append('subject', subject);
            fd.append('message', message);
            fd.append('ids', JSON.stringify(ids));
            const res = await fetch('bulk_email.php', { method: 'POST', body: fd });
            const text = await res.text();
            try { return JSON.parse(text); }
            catch (e) { return { success: false, message: 'Unexpected server response.' }; }
         }

         // ---- Batch-size validation (show a message instead of silently clamping) ----
         function validateBatch() {
            const input = $('batchSize');
            const msg = $('batchSizeMsg');
            const raw = input.value.trim();
            const n = parseInt(raw, 10);
            let error = '';

            if (raw === '' || isNaN(n)) {
               error = 'Please enter a number between ' + MIN_PER_REQUEST + ' and ' + MAX_PER_REQUEST + '.';
            } else if (n < MIN_PER_REQUEST) {
               error = 'Minimum allowed is ' + MIN_PER_REQUEST + ' emails per batch.';
            } else if (n > MAX_PER_REQUEST) {
               error = 'Maximum allowed is ' + MAX_PER_REQUEST + ' emails per batch.';
            }

            if (error) {
               msg.textContent = '⚠ ' + error;
               msg.classList.remove('d-none');
               input.classList.add('be-input-error');
               return null;
            }
            msg.classList.add('d-none');
            input.classList.remove('be-input-error');
            return n;
         }
         $('batchSize').addEventListener('input', validateBatch);

         // ---- SweetAlert helpers (graceful fallback if Swal is unavailable) ----
         const hasSwal = () => typeof window.Swal !== 'undefined';
         function swalWarn(text) {
            if (hasSwal()) {
               Swal.fire({ icon: 'warning', title: 'Hold on', text: text, confirmButtonColor: '#252161' });
            } else {
               alert(text);
            }
         }
         async function swalConfirm(count, skipped) {
            const skipNote = skipped > 0
               ? '<div style="margin-top:8px;font-size:.85rem;color:#6b7785;">'
               + '<i class="ti ti-mail-check"></i> ' + skipped + ' already-emailed client(s) will be skipped.</div>'
               : '';
            if (hasSwal()) {
               const r = await Swal.fire({
                  icon: 'question',
                  title: 'Send bulk email?',
                  html: 'This email will be sent to <strong>' + count + '</strong> client(s).' + skipNote,
                  showCancelButton: true,
                  confirmButtonText: '<i class="ti ti-send"></i> Yes, send',
                  cancelButtonText: 'Cancel',
                  confirmButtonColor: '#252161',
                  cancelButtonColor: '#6b7785',
                  reverseButtons: true
               });
               return r.isConfirmed;
            }
            return confirm('Send this email to ' + count + ' client(s)?'
               + (skipped > 0 ? '\n(' + skipped + ' already-emailed client(s) will be skipped.)' : ''));
         }

         // ---- Main routine ----
         async function startSending() {
            if (sending) return;
            const subject = $('emailSubject').value.trim();
            const message = $('emailMessage').value.trim();
            const ids = Array.from(selectedIds);

            if (!subject || !message) { swalWarn('Please enter both a subject and a message.'); return; }
            if (ids.length === 0) { swalWarn('Please select at least one client.'); return; }

            // Already-emailed clients stay ticked as a "done" marker, but we do NOT
            // re-send to them. Only send to selected clients not yet emailed.
            const sendIds = ids.filter(id => !emailedSeeded.has(id));
            const skipped = ids.length - sendIds.length;
            if (sendIds.length === 0) {
               swalWarn('All selected clients have already been emailed. Select at least one new client to send.');
               return;
            }

            const batchSize = validateBatch();
            if (batchSize === null) { $('batchSize').focus(); return; }
            const pauseSec = Math.max(0, parseInt($('batchPause').value, 10) || 0);

            if (!(await swalConfirm(sendIds.length, skipped))) return;

            // Build the queue from the not-yet-emailed selected ids (names/emails
            // filled where known; the server returns the authoritative values anyway).
            const queue = sendIds.map(id => {
               const m = clientMeta[id] || {};
               return { id, name: m.name || ('#' + id), email: m.email || '' };
            });
            const total = queue.length;

            sending = true; stopRequested = false;
            $('progressCard').classList.remove('d-none');
            $('resultsCard').classList.remove('d-none');
            $('progressCard').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            $('startSendBtn').disabled = true;
            $('stopSendBtn').classList.remove('d-none');
            setStat('statTotal', total); setStat('statSent', 0);
            setStat('statFailed', 0); setStat('statRemaining', total);

            // Seed the results table — everyone starts as "Remaining".
            resultOrder = queue.map(c => c.id);
            resultOrder.forEach((id, i) => {
               resultMap[id] = { name: queue[i].name, email: queue[i].email, status: 'Remaining', detail: '' };
            });
            renderResultsTable();

            let sentCount = 0, failedCount = 0, processed = 0;

            // ---- Live SweetAlert that mirrors the Sending Progress panel ----
            const tile = (id, val, color, label, bg) =>
               '<div style="text-align:center;padding:12px 6px;border-radius:12px;background:' + bg + ';">'
               + '<div id="' + id + '" style="font-size:1.4rem;font-weight:700;line-height:1;' + (color ? 'color:' + color + ';' : '') + '">' + val + '</div>'
               + '<div style="font-size:.72rem;color:#6b7785;margin-top:4px;">' + label + '</div></div>';

            const swalMarkup =
               '<div style="text-align:left;">'
               + '<div style="height:22px;border-radius:999px;background:#f6f8fa;overflow:hidden;margin:6px 0 16px;">'
               + '<div id="swalBar" style="height:100%;width:0%;border-radius:999px;background:linear-gradient(90deg,#252161,#4b45b0);color:#fff;font-size:.7rem;font-weight:700;display:flex;align-items:center;justify-content:flex-end;padding-right:8px;white-space:nowrap;transition:width .35s ease;">0%</div>'
               + '</div>'
               + '<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:14px;">'
               + tile('swalTotal', total, '', 'Total', '#f6f8fa')
               + tile('swalSent', 0, '#1e7e34', 'Sent', 'rgba(40,167,69,.1)')
               + tile('swalFailed', 0, '#c1303f', 'Failed', 'rgba(220,53,69,.1)')
               + tile('swalRemaining', total, '#b97c00', 'Remaining', 'rgba(255,171,0,.12)')
               + '</div>'
               + '<div id="swalStatus" style="background:#f6f8fa;border-radius:10px;padding:10px 12px;font-size:.85rem;color:#6b7785;">Starting…</div>'
               + '<div style="text-align:center;margin-top:16px;">'
               + '<button id="swalStopBtn" type="button" style="background:#e2475a;color:#fff;border:none;border-radius:8px;padding:9px 18px;font-weight:600;cursor:pointer;">Stop after batch</button>'
               + '</div></div>';

            function swalUpdate(statusHtml) {
               const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
               const pct = Math.round((processed / total) * 100);
               const bar = document.getElementById('swalBar');
               if (bar) { bar.style.width = pct + '%'; bar.textContent = pct + '%'; }
               set('swalSent', sentCount);
               set('swalFailed', failedCount);
               set('swalRemaining', total - processed);
               const st = document.getElementById('swalStatus');
               if (st && statusHtml != null) st.innerHTML = statusHtml;
            }

            if (typeof window.Swal !== 'undefined') {
               Swal.fire({
                  title: 'Sending Progress',
                  width: 640,
                  html: swalMarkup,
                  allowOutsideClick: false,
                  allowEscapeKey: false,
                  showConfirmButton: false,
                  didOpen: () => {
                     const stop = document.getElementById('swalStopBtn');
                     if (stop) stop.addEventListener('click', () => {
                        stopRequested = true;
                        stop.disabled = true;
                        stop.style.opacity = '.6';
                        stop.textContent = 'Stopping…';
                     });
                  }
               });
            }

            const batches = [];
            for (let i = 0; i < queue.length; i += batchSize) batches.push(queue.slice(i, i + batchSize));

            for (let b = 0; b < batches.length; b++) {
               if (stopRequested) {
                  const m = '<i class="ti ti-player-stop"></i> Stopped after ' + processed + ' of ' + total + '.';
                  $('statusLine').innerHTML = m; swalUpdate(m);
                  break;
               }
               const batch = batches[b];
               const sendingMsg = '<i class="ti ti-loader"></i> Sending batch ' + (b + 1) + ' of ' + batches.length + ' (' + batch.length + ' emails)…';
               $('statusLine').innerHTML = sendingMsg; swalUpdate(sendingMsg);

               const resp = await sendBatch(batch.map(c => c.id), subject, message);

               if (!resp.success) {
                  batch.forEach(c => {
                     failedCount++; processed++;
                     resultMap[c.id] = { name: c.name, email: c.email, status: 'Failed', detail: resp.message || 'Send failed' };
                  });
               } else {
                  const byId = {};
                  (resp.results || []).forEach(r => byId[r.id] = r);
                  batch.forEach(c => {
                     processed++;
                     const r = byId[c.id];
                     const nm = (r && r.name) || c.name;
                     const em = (r && r.email) || c.email;
                     if (r && r.status === 'sent') {
                        sentCount++;
                        resultMap[c.id] = { name: nm, email: em, status: 'Sent', detail: 'Delivered to ' + em };
                     } else {
                        failedCount++;
                        const err = r ? r.error : 'No response';
                        resultMap[c.id] = { name: nm, email: em, status: 'Failed', detail: err };
                     }
                  });
               }

               setStat('statSent', sentCount); setStat('statFailed', failedCount);
               setStat('statRemaining', total - processed);
               const pct = Math.round((processed / total) * 100);
               const bar = $('progressBar');
               bar.style.width = pct + '%';
               bar.querySelector('span').textContent = pct + '%';

               // Refresh the detailed results table with this batch's outcomes.
               renderResultsTable();
               swalUpdate(null); // refresh the modal's counts/bar after this batch

               // Reflect this batch in the client list: tick everyone the email
               // was actually SENT to, untick the ones that failed. Purely
               // client-side (the selection Set) — no DB changes. Because rows
               // render their checked state from selectedIds, the tick survives
               // scrolling and re-searching too.
               batch.forEach(c => {
                  const id = String(c.id);
                  const wasSent = (resultMap[id] || {}).status === 'Sent';
                  if (wasSent) { selectedIds.add(id); emailedSeeded.add(id); }
                  else selectedIds.delete(id);
                  const cb = document.querySelector('.client-check[value="' + id + '"]');
                  if (cb) cb.checked = wasSent;
               });
               updateBadge();

               if (b < batches.length - 1 && !stopRequested && pauseSec > 0) {
                  for (let s = pauseSec; s > 0 && !stopRequested; s--) {
                     const m = '<i class="ti ti-clock"></i> Waiting ' + s + 's before next batch to avoid being blocked…';
                     $('statusLine').innerHTML = m; swalUpdate(m);
                     await sleep(1000);
                  }
               }
            }

            const stopped = stopRequested;
            if (!stopped) {
               $('statusLine').innerHTML = '<i class="ti ti-circle-check"></i> Done — ' + sentCount + ' sent, ' + failedCount + ' failed of ' + total + '.';
            }
            sending = false;
            $('startSendBtn').disabled = false;
            $('stopSendBtn').classList.add('d-none');

            // Completion summary in a SweetAlert.
            const summaryHtml =
               '<div style="font-size:15px;line-height:1.9;">'
               + '<div><strong>' + total + '</strong> total</div>'
               + '<div style="color:#1e7e34;"><strong>' + sentCount + '</strong> sent</div>'
               + '<div style="color:#c1303f;"><strong>' + failedCount + '</strong> failed</div>'
               + (stopped ? '<div style="color:#b97c00;"><strong>' + (total - processed) + '</strong> remaining (stopped)</div>' : '')
               + '</div>';
            if (typeof window.Swal !== 'undefined') {
               Swal.fire({
                  icon: stopped ? 'info' : (failedCount === 0 ? 'success' : 'warning'),
                  title: stopped ? 'Sending stopped' : 'Sending complete',
                  html: summaryHtml,
                  confirmButtonText: 'OK',
                  confirmButtonColor: '#252161'
               });
            }
         }

         $('startSendBtn').addEventListener('click', startSending);
         $('stopSendBtn').addEventListener('click', function () {
            stopRequested = true;
            this.disabled = true;
            this.innerHTML = '<i class="ti ti-loader"></i> Stopping…';
            setTimeout(() => { this.disabled = false; this.innerHTML = '<i class="ti ti-player-stop"></i> Stop after batch'; }, 2000);
         });

         // ---- Init ----
         updateBadge();
         fetchPage(true);   // load the first page of clients

         // Send-history DataTable (static, server-rendered) with export buttons.
         if (document.getElementById('historyTable')) {
            buildDataTable('#historyTable', 'Send History', {
               order: [],
               language: { search: '_INPUT_', searchPlaceholder: 'Search history...', lengthMenu: '_MENU_' }
            });
         }
      })();
   </script>
</body>

</html>
