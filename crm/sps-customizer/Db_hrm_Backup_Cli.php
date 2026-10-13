<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

include "includes/db.php";
include "includes/auth.php";
include "loadEnv.php";

$user_id = $_SESSION['user_id'];

/**
 * Attendance System database connection details.
 * These mirror livemachine/config/database.php (the ZK attendance app)
 * so we can back that schema up independently of the main CRM database.
 */
$attHost = '127.0.0.1';
$attUser = 'root';
$attPass = '';
$attDatabase = 'attendance_system';
$attPort = 3306;

// $host = '127.0.0.1';
// $username = 'root';
// $password = 'CHANGE_ME_DB_PASSWORD';
// $database = 'attendance_system';

function runDatabaseBackupToFile($dbHost, $dbUser, $dbPass, $dbName, $dbPort, $outputFile)
{
   $envMysqldump = getenv('MYSQLDUMP_PATH');
   if (!$envMysqldump && isset($_ENV['MYSQLDUMP_PATH'])) {
      $envMysqldump = $_ENV['MYSQLDUMP_PATH'];
   }

   $isWindows = (PHP_OS_FAMILY === 'Windows');
   $candidates = array_filter([
      $envMysqldump,                                    // Prefer explicit path from .env
      $isWindows ? 'C:\\xampp\\mysql\\bin\\mysqldump.exe' : null, // XAMPP default (Windows)
      $isWindows ? 'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe' : null,
      $isWindows ? 'C:\\Program Files\\MySQL\\MySQL Server 5.7\\bin\\mysqldump.exe' : null,
      '/usr/bin/mysqldump',                             // Common Linux path
      '/usr/local/bin/mysqldump',                       // Alternate Linux path
      'mysqldump',                                      // Fallback to PATH
   ]);

   $mysqldump = null;
   foreach ($candidates as $candidate) {
      if ($candidate === 'mysqldump' || file_exists($candidate)) {
         $mysqldump = $candidate;
         break;
      }
   }

   if (!$mysqldump) {
      return [false, 'mysqldump not found. Set MYSQLDUMP_PATH in .env or ensure it is in PATH.'];
   }

   $cmd = sprintf(
      '"%s" --host=%s --user=%s --port=%s --password=%s --databases %s --single-transaction --routines --triggers --events --add-drop-table --default-character-set=utf8mb4',
      $mysqldump,
      escapeshellarg($dbHost),
      escapeshellarg($dbUser),
      escapeshellarg((string) $dbPort),
      escapeshellarg($dbPass),
      escapeshellarg($dbName)
   );

   $descriptors = [
      0 => ['pipe', 'r'],
      1 => ['file', $outputFile, 'w'],
      2 => ['pipe', 'w'],
   ];

   $process = proc_open($cmd, $descriptors, $pipes);
   if (!is_resource($process)) {
      return [false, 'Failed to start mysqldump.'];
   }

   fclose($pipes[0]);
   stream_set_blocking($pipes[2], true);

   $stderr = stream_get_contents($pipes[2]);
   fclose($pipes[2]);

   $exitCode = proc_close($process);
   if ($exitCode !== 0) {
      return [false, "Backup failed: " . trim($stderr)];
   }

   return [true, null];
}

function getInitials($name)
{
   $words = explode(' ', $name);
   $initials = '';
   foreach ($words as $word) {
      $initials .= strtoupper(substr($word, 0, 1));
   }
   return $initials;
}


$user_full_name = $_SESSION['fullname'];
$user_initials = getInitials($user_full_name);
$user_type = $_SESSION['user_type'];

$backupStatus = null;
$backupNotice = null;
$backupAlert = null;
$backupPassword = 'skipper@';
$passwordConfigured = true;

$backupAuthTtl = 15 * 60;
$backupAuthAt = $_SESSION['att_backup_auth_at'] ?? 0;
$backupAuthFresh = isset($_SESSION['att_backup_auth']) && $_SESSION['att_backup_auth'] === true && (time() - $backupAuthAt) <= $backupAuthTtl;
if (!$backupAuthFresh) {
   $_SESSION['att_backup_auth'] = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   $action = $_POST['action'] ?? '';
   if ($action === 'verify') {
      if (!$passwordConfigured) {
         $backupStatus = "Backup password is not configured. Set BACKUP_DOWNLOAD_PASSWORD in .env.";
      } else {
         $entered = $_POST['backup_password'] ?? '';
         if (hash_equals((string) $backupPassword, (string) $entered)) {
            $_SESSION['att_backup_auth'] = true;
            $_SESSION['att_backup_auth_at'] = time();
            $backupAuthFresh = true;
            $backupNotice = "Password verified. You can download the backup for the next 15 minutes.";
         } else {
            $backupAlert = [
               'icon' => 'error',
               'title' => 'Invalid password',
               'text' => 'Please try again.',
            ];
            $backupStatus = null;
         }
      }
   } elseif ($action === 'download') {
      if (!$passwordConfigured) {
         $backupStatus = "Backup password is not configured. Set BACKUP_DOWNLOAD_PASSWORD in .env.";
      } elseif (!$backupAuthFresh) {
         $backupStatus = "Password required. Please verify access before downloading.";
      } else {
         set_time_limit(0);
         $tmpFile = tempnam(sys_get_temp_dir(), 'attdump_');
         [$ok, $result] = runDatabaseBackupToFile($attHost, $attUser, $attPass, $attDatabase, $attPort, $tmpFile);
         if ($ok) {
            $timestamp = date('Ymd_His');
            $filename = $attDatabase . '_' . $timestamp . '.sql';
            header('Content-Type: application/sql');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            readfile($tmpFile);
            @unlink($tmpFile);
            exit;
         }
         @unlink($tmpFile);
         $backupAlert = [
            'icon' => 'error',
            'title' => 'Backup failed',
            'text' => $result ?: 'Unable to generate the backup.',
         ];
      }
   }
}

?>

<!DOCTYPE html>
<html lang="en" dir="">
<meta name="csrf-token" id="csrf-token" content="8c2VdHOgBJmeryjWyCs9cx1G8chmj75MpBN38dRd">

<head>
   <title>Syed Professional Services</title>
   <?php include "includes/links.php"; ?>
   <style>
      :root {
         --bk-ink: #231f2b;
         --bk-muted: rgba(255, 255, 255, 0.78);
         --bk-accent: #e95420;
         --bk-accent-2: #c6411c;
         --bk-bg-1: #0a3d2b;
         --bk-bg-2: #114a3c;
         --bk-card: rgba(255, 255, 255, 0.08);
         --bk-soft: rgba(255, 255, 255, 0.12);
         --bk-border: rgba(255, 255, 255, 0.2);
         --bk-glow: rgba(233, 84, 32, 0.35);
         --bk-text: #ffffff;
      }

      .backup-shell {
         background:
            radial-gradient(circle at 10% 15%, rgba(233, 84, 32, 0.35), transparent 45%),
            radial-gradient(circle at 85% 10%, rgba(66, 255, 183, 0.28), transparent 40%),
            linear-gradient(150deg, var(--bk-bg-1) 0%, #07271b 45%, var(--bk-bg-2) 100%);
         border-radius: 16px;
         padding: 22px 24px;
         color: var(--bk-text);
         position: relative;
         overflow: hidden;
         min-width: 0;
         overflow-wrap: break-word;
         word-break: normal;
         box-shadow:
            0 22px 40px rgba(4, 24, 16, 0.55),
            0 0 0 1px rgba(255, 255, 255, 0.06) inset;
      }

      .backup-shell::after {
         content: "";
         position: absolute;
         inset: 0;
         background:
            linear-gradient(180deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.02));
      }

      .backup-shell * {
         position: relative;
         z-index: 1;
         min-width: 0;
      }

      .backup-hero {
         display: grid;
         gap: 18px;
         grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr);
         align-items: stretch;
         min-width: 0;
      }

      .backup-title {
         font-weight: 700;
         font-size: clamp(22px, 2.1vw, 28px);
         margin-bottom: 6px;
         letter-spacing: 0.2px;
      }

      .backup-subtitle {
         color: rgba(255, 255, 255, 0.75);
         font-size: 14px;
         line-height: 1.6;
         margin-bottom: 14px;
         overflow-wrap: break-word;
         word-break: normal;
      }

      .backup-panel {
         background: var(--bk-card);
         border: 1px solid rgba(255, 255, 255, 0.16);
         border-radius: 14px;
         padding: 16px;
         color: var(--bk-text);
         min-width: 0;
         box-shadow:
            0 16px 30px rgba(4, 24, 16, 0.45),
            0 0 0 1px rgba(255, 255, 255, 0.08) inset,
            0 12px 28px var(--bk-glow);
         backdrop-filter: blur(12px);
      }

      .backup-meta {
         display: grid;
         gap: 10px;
         font-size: 13px;
         color: var(--bk-ink);
         grid-template-columns: repeat(2, minmax(0, 1fr));
         min-width: 0;
      }

      .meta-row {
         display: flex;
         justify-content: space-between;
         gap: 12px;
         padding: 10px 12px;
         border-radius: 10px;
         background: var(--bk-soft);
         border: 1px solid rgba(255, 255, 255, 0.2);
         align-items: center;
         flex-wrap: wrap;
         min-width: 0;
      }

      .meta-label {
         display: inline-flex;
         align-items: center;
         gap: 10px;
         color: rgba(255, 255, 255, 0.78);
         font-weight: 600;
         max-width: 100%;
         overflow-wrap: break-word;
         word-break: normal;
         hyphens: auto;
      }

      .meta-row span,
      .meta-row strong {
         max-width: 100%;
         overflow-wrap: break-word;
         word-break: normal;
      }

      .meta-row strong {
         overflow-wrap: anywhere;
         word-break: break-word;
      }

      .meta-label i {
         width: 28px;
         height: 28px;
         display: inline-flex;
         align-items: center;
         justify-content: center;
         border-radius: 8px;
         background: rgba(255, 255, 255, 0.12);
         border: 1px solid rgba(255, 255, 255, 0.2);
         box-shadow:
            0 6px 12px rgba(4, 24, 16, 0.25),
            0 0 0 1px rgba(255, 255, 255, 0.08) inset;
         color: #95ffc8;
      }

      .backup-status {
         margin-top: 14px;
         color: #8b2a2a;
         background: rgba(251, 233, 233, 0.92);
         border: 1px solid rgba(139, 42, 42, 0.35);
         padding: 10px 12px;
         border-radius: 10px;
         overflow-wrap: break-word;
         word-break: normal;
      }

      .backup-notice {
         margin-top: 14px;
         color: #0e2b1b;
         background: rgba(229, 255, 237, 0.95);
         border: 1px solid rgba(32, 233, 84, 0.55);
         padding: 10px 12px;
         border-radius: 10px;
         font-size: 13px;
         overflow-wrap: break-word;
         word-break: normal;
      }

      .backup-note {
         margin-top: 14px;
         color: rgba(255, 255, 255, 0.75);
         font-size: 13px;
         overflow-wrap: break-word;
         word-break: normal;
      }

      .backup-auth-panel {
         background: rgba(255, 255, 255, 0.1);
         border: 1px solid rgba(255, 255, 255, 0.2);
         border-radius: 12px;
         padding: 14px;
         display: grid;
         gap: 12px;
         box-shadow:
            0 14px 24px rgba(4, 24, 16, 0.4),
            0 0 0 1px rgba(255, 255, 255, 0.1) inset;
         backdrop-filter: blur(10px);
      }

      .backup-form-row {
         display: flex;
         gap: 10px;
         align-items: center;
         flex-wrap: wrap;
      }

      .backup-form-row .form-control {
         min-width: 220px;
         box-shadow: rgba(0, 0, 0, 0.35) 0px -50px 36px -28px inset;
         border: 1px solid rgba(255, 255, 255, 0.35);
      }

      .backup-form-row .btn {
         font-weight: 600;
         border-radius: 10px;
         border: 1px solid rgba(255, 255, 255, 0.2);
         box-shadow:
            0 10px 18px rgba(4, 24, 16, 0.3),
            0 6px 12px rgba(32, 233, 84, 0.35);
      }

      .backup-form-row .text-white-50 {
         flex: 1 1 220px;
         min-width: 200px;
         line-height: 1.5;
      }

      .backup-panel .meta-row strong {
         color: #95ffc8;
      }

      .backup-panel .meta-row {
         background: rgba(255, 255, 255, 0.08);
      }

      .backup-kpi-row {
         display: flex;
         gap: 24px;
         overflow-x: auto;
         padding-bottom: 6px;
      }

      .backup-kpi-col {
         flex: 0 0 220px;
         min-width: 0;
      }

      .backup-kpi-row .card {
         border: 1px solid rgba(255, 255, 255, 0.2);
         box-shadow:
            0 14px 24px rgba(4, 24, 16, 0.45),
            0 0 0 1px rgba(255, 255, 255, 0.08) inset;
         backdrop-filter: blur(10px);
      }

      .backup-kpi-row .card-body {
         color: var(--bk-text);
         min-width: 0;
      }

      .backup-kpi-row h3,
      .backup-kpi-row h6,
      .backup-kpi-row p {
         overflow-wrap: break-word;
         word-break: normal;
      }

      /* Long, underscore-joined DB names (e.g. attendance_system) have no
         natural break point and were clipping the card edge. Let them break
         anywhere and shrink to fit the fixed-width KPI column. */
      .backup-kpi-row h3.kpi-schema {
         overflow-wrap: anywhere;
         word-break: break-word;
         font-size: clamp(1rem, 1.4vw, 1.4rem);
         line-height: 1.2;
      }

      .backup-kpi-row .theme-avtar {
         background: rgba(255, 255, 255, 0.14) !important;
         color: #95ffc8 !important;
         border: 1px solid rgba(255, 255, 255, 0.18);
         box-shadow:
            0 8px 14px rgba(4, 24, 16, 0.35),
            0 0 0 1px rgba(255, 255, 255, 0.08) inset;
      }

      @media (max-width: 991px) {
         .backup-hero {
            grid-template-columns: 1fr;
         }

         .backup-meta {
            grid-template-columns: 1fr;
         }
      }

      @media (max-width: 767px) {
         .backup-shell {
            padding: 18px 16px;
         }

         .backup-form-row {
            align-items: stretch;
         }

         .backup-form-row .form-control {
            min-width: 0;
            width: 100%;
         }

         .backup-form-row .btn {
            width: 100%;
            justify-content: center;
         }

         .backup-form-row .text-white-50 {
            flex: 1 1 100%;
            min-width: 0;
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

   <?php include "includes/sidebar.php"; ?>
   <?php include "includes/header.php"; ?>
   <div class="dash-container">
      <div class="dash-content">
         <div class="card card-body">
            <div class="page-block">
               <div class="row align-items-center">
                  <div class="col-auto">
                     <div class="page-header-title">
                        <h4 class="m-b-10">Attendance System Backup</h4>
                     </div>
                     <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                        <li class="breadcrumb-item">Attendance Backup</li>
                     </ul>
                  </div>

               </div>
            </div>
         </div>

         <div class="card card-body">
            <div class="row mt-3 backup-kpi-row">
               <div class="backup-kpi-col">
                  <div class="card">
                     <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                           <div class="theme-avtar bg-primary">
                              <i class="ti ti-database"></i>
                           </div>
                           <p class="text-muted text-sm mt-4 mb-2">Active Schema</p>
                           <h6 class="mb-2">Database</h6>
                           <h3 class="mb-0 kpi-schema"><?php echo htmlspecialchars($attDatabase); ?></h3>
                        </div>
                     </div>
                  </div>
               </div>
               <div class="backup-kpi-col">
                  <div class="card">
                     <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                           <div class="theme-avtar bg-info">
                              <i class="ti ti-server"></i>
                           </div>
                           <p class="text-muted text-sm mt-4 mb-2">Connection Target</p>
                           <h6 class="mb-2">DB Host</h6>
                           <h3 class="mb-0"><?php echo htmlspecialchars($attHost); ?></h3>
                        </div>
                     </div>
                  </div>
               </div>
               <div class="backup-kpi-col">
                  <div class="card">
                     <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                           <div class="theme-avtar bg-info">
                              <i class="ti ti-server"></i>
                           </div>
                           <p class="text-muted text-sm mt-4 mb-2">Connection Target</p>
                           <h6 class="mb-2">Port</h6>
                           <h3 class="mb-0">
                              <?php echo htmlspecialchars($attPort); ?>
                           </h3>
                        </div>
                     </div>
                  </div>
               </div>
               <div class="backup-kpi-col">
                  <div class="card">
                     <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                           <div class="theme-avtar bg-warning">
                              <i class="ti ti-brand-php"></i>
                           </div>
                           <p class="text-muted text-sm mt-4 mb-2">Runtime</p>
                           <h6 class="mb-2">PHP Version</h6>
                           <h3 class="mb-0"><?php echo htmlspecialchars(PHP_VERSION); ?></h3>
                        </div>
                     </div>
                  </div>
               </div>
               <div class="backup-kpi-col">
                  <div class="card">
                     <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                           <div class="theme-avtar bg-success">
                              <i class="ti ti-shield-check"></i>
                           </div>
                           <p class="text-muted text-sm mt-4 mb-2">Access State</p>
                           <h6 class="mb-2">Backup Access</h6>
                           <h3 class="mb-0"><?php echo $backupAuthFresh ? 'Verified' : 'Locked'; ?></h3>
                        </div>
                     </div>
                  </div>
               </div>
               <div class="backup-kpi-col">
                  <div class="card">
                     <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                           <div class="theme-avtar bg-secondary">
                              <i class="ti ti-clock"></i>
                           </div>
                           <p class="text-muted text-sm mt-4 mb-2"><?php echo htmlspecialchars(date('Y-m-d')); ?></p>
                           <h6 class="mb-2">Server Time</h6>
                           <h3 class="mb-0"><?php echo htmlspecialchars(date('H:i')); ?></h3>
                        </div>
                     </div>
                  </div>
               </div>

            </div>
            <div class="backup-shell">
               <div class="backup-hero">
                  <div>
                     <div class="backup-title">Attendance System Backup Console</div>
                     <div class="backup-subtitle">
                        Generate a full SQL backup of the <strong><?php echo htmlspecialchars($attDatabase); ?></strong>
                        database used by the live attendance machine. The file is streamed directly to your browser
                        and saved in your PC's Downloads folder.
                     </div>
                     <div class="backup-auth-panel">
                        <?php if ($backupAuthFresh) { ?>
                           <form method="post" class="backup-form-row">
                              <input type="hidden" name="action" value="download">
                              <button type="submit" class="btn btn-light">Download Backup</button>
                              <span class="text-white-50">Access verified. Session expires in
                                 <?php echo (int) ceil(max(0, $backupAuthTtl - (time() - $backupAuthAt)) / 60); ?>
                                 minutes.</span>
                           </form>
                        <?php } else { ?>
                           <form method="post" class="backup-form-row">
                              <input type="hidden" name="action" value="verify">
                              <input type="password" name="backup_password" class="form-control"
                                 placeholder="Enter backup password" autocomplete="current-password" required>
                              <button type="submit" class="btn btn-light">Verify Password</button>
                              <span
                                 class="text-white-50"><?php echo $passwordConfigured ? 'Password required before download.' : 'Password not configured yet.'; ?></span>
                           </form>
                        <?php } ?>
                     </div>
                     <?php if ($backupNotice) { ?>
                        <div class="backup-notice"><?php echo $backupNotice; ?></div>
                     <?php } ?>
                     <?php if ($backupStatus) { ?>
                        <div class="backup-status"><?php echo $backupStatus; ?></div>
                     <?php } ?>
                     <div class="backup-note">If the database is large, the download may take a few minutes.</div>
                     <div class="backup-kpi-row" style="margin-top: 16px;">
                        <div class="backup-kpi-col">
                           <div class="card">
                              <div class="card-body d-flex align-items-center justify-content-between">
                                 <div>
                                    <div class="theme-avtar bg-primary">
                                       <i class="ti ti-database"></i>
                                    </div>
                                    <p class="text-muted text-sm mt-4 mb-2">Scope</p>
                                    <h6 class="mb-2">Backup Scope</h6>
                                    <h3 class="mb-0">Full database</h3>
                                 </div>
                              </div>
                           </div>
                        </div>
                        <div class="backup-kpi-col">
                           <div class="card">
                              <div class="card-body d-flex align-items-center justify-content-between">
                                 <div>
                                    <div class="theme-avtar bg-info">
                                       <i class="ti ti-file-description"></i>
                                    </div>
                                    <p class="text-muted text-sm mt-4 mb-2">Format</p>
                                    <h6 class="mb-2">File Type</h6>
                                    <h3 class="mb-0">.sql (UTF-8)</h3>
                                 </div>
                              </div>
                           </div>
                        </div>

                     </div>
                  </div>
                  <div class="backup-panel">
                     <div class="backup-title" style="font-size: 18px;">Developer Snapshot</div>
                     <div class="backup-meta">
                        <div class="meta-row">
                           <span class="meta-label"><i class="ti ti-database"></i>Database</span>
                           <strong><?php echo htmlspecialchars($attDatabase); ?></strong>
                        </div>
                        <div class="meta-row">
                           <span class="meta-label"><i class="ti ti-server"></i>DB Host</span>
                           <strong><?php echo htmlspecialchars($attHost . ':' . $attPort); ?></strong>
                        </div>
                        <div class="meta-row">
                           <span class="meta-label"><i class="ti ti-brand-php"></i>PHP Version</span>
                           <strong><?php echo htmlspecialchars(PHP_VERSION); ?></strong>
                        </div>
                        <div class="meta-row">
                           <span class="meta-label"><i class="ti ti-clock"></i>Server Time</span>
                           <strong><?php echo htmlspecialchars(date('Y-m-d H:i:s')); ?></strong>
                        </div>
                        <div class="meta-row">
                           <span class="meta-label"><i class="ti ti-world"></i>Timezone</span>
                           <strong><?php echo htmlspecialchars(date_default_timezone_get()); ?></strong>
                        </div>
                        <div class="meta-row">
                           <span class="meta-label"><i class="ti ti-cloud-download"></i>Delivery</span>
                           <strong>Direct download</strong>
                        </div>
                        <div class="meta-row">
                           <span class="meta-label"><i class="ti ti-archive"></i>Retention</span>
                           <strong>No server storage</strong>
                        </div>
                        <div class="meta-row">
                           <span class="meta-label"><i class="ti ti-calendar-week"></i>Recommended</span>
                           <strong>Weekly backup</strong>
                        </div>
                        <div class="meta-row">
                           <span class="meta-label"><i class="ti ti-shield-lock"></i>Security</span>
                           <strong>Password gate</strong>
                        </div>
                        <div class="meta-row">
                           <span class="meta-label"><i class="ti ti-adjustments-alt"></i>Options</span>
                           <strong>Routines, triggers, events</strong>
                        </div>
                        <div class="meta-row">
                           <span class="meta-label"><i class="ti ti-key"></i>Config</span>
                           <strong><?php echo $passwordConfigured ? 'Password set' : 'Set BACKUP_DOWNLOAD_PASSWORD'; ?></strong>
                        </div>
                        <div class="meta-row">
                           <span class="meta-label"><i class="ti ti-database-export"></i>Backup Scope</span>
                           <strong>Full database</strong>
                        </div>
                        <div class="meta-row">
                           <span class="meta-label"><i class="ti ti-file-type-sql"></i>File Type</span>
                           <strong>.sql (UTF-8)</strong>
                        </div>
                        <div class="meta-row">
                           <span class="meta-label"><i class="ti ti-switch"></i>Dump Strategy</span>
                           <strong>Single transaction</strong>
                        </div>
                     </div>
                  </div>
               </div>
            </div>
         </div>

      </div>
   </div>
   <?php include "includes/footer.php"; ?>
   <?php include "includes/scripts.php"; ?>
   <?php if ($backupAlert) { ?>
      <script>
         document.addEventListener('DOMContentLoaded', function() {
            if (window.Swal) {
               Swal.fire(<?php echo json_encode($backupAlert); ?>);
            }
         });
      </script>
   <?php } ?>
</body>

</html>
