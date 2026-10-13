<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

include "includes/db.php";
include "includes/auth.php";

$user_id = $_SESSION['user_id'];

function getInitials($name) {
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

$message = '';
$messageType = '';
$viewMode = false;
$viewData = null;

// Handle Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $deleteId = intval($_GET['delete']);
    
    $stmt = $con->prepare("SELECT file_path FROM csv_data_store WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $deleteId, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if (file_exists($row['file_path'])) {
            unlink($row['file_path']);
        }
        
        $stmt = $con->prepare("DELETE FROM csv_data_store WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $deleteId, $user_id);
        if ($stmt->execute()) {
            $message = "File deleted successfully!";
            $messageType = "success";
        }
    }
    header("Location: csv-upload.php?msg=" . urlencode($message) . "&type=" . $messageType);
    exit;
}

if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
    $messageType = $_GET['type'] ?? 'info';
}

// Handle View Request
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $viewId = intval($_GET['view']);
    $stmt = $con->prepare("SELECT * FROM csv_data_store WHERE id = ? AND user_id = ? AND status = 'completed'");
    $stmt->bind_param("ii", $viewId, $user_id);
    $stmt->execute();
    $viewData = $stmt->get_result()->fetch_assoc();
    
    if ($viewData) {
        $viewMode = true;
    }
}

// Handle CSV Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK && !$viewMode) {
    $file = $_FILES['csv_file'];
    
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($fileExtension, ['csv', 'txt'])) {
        $message = "Invalid file type. Please upload a CSV or TXT file.";
        $messageType = "danger";
    } elseif ($file['size'] > 10 * 1024 * 1024) {
        $message = "File too large. Maximum size is 10MB.";
        $messageType = "danger";
    } else {
        $newFilename = uniqid() . '_' . time() . '.csv';
        $uploadPath = 'uploads/csv/' . $newFilename;
        
        if (!is_dir('uploads/csv/')) {
            mkdir('uploads/csv/', 0755, true);
        }
        
        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            $processResult = processAndStoreCSV($uploadPath, $file['name'], $user_id, $con);
            
            if ($processResult['success']) {
                $message = "CSV uploaded successfully! Processed " . $processResult['totalRows'] . " data rows.";
                $messageType = "success";
            } else {
                $message = "Error: " . $processResult['error'];
                $messageType = "danger";
                if (file_exists($uploadPath)) unlink($uploadPath);
            }
        } else {
            $message = "Failed to move uploaded file. Check folder permissions (uploads/csv/).";
            $messageType = "danger";
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    switch ($_FILES['csv_file']['error']) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            $message = "File too large. Maximum upload size exceeded.";
            break;
        case UPLOAD_ERR_PARTIAL:
            $message = "File upload was interrupted.";
            break;
        case UPLOAD_ERR_NO_FILE:
            $message = "No file selected. Please choose a CSV file.";
            break;
        default:
            $message = "Upload error code: " . $_FILES['csv_file']['error'];
    }
    $messageType = "danger";
}

// FIXED CSV Processing - Store count in variable before bind_param
function processAndStoreCSV($filepath, $originalName, $userId, $con) {
    $result = ['success' => false, 'totalRows' => 0, 'error' => ''];
    
    try {
        $handle = fopen($filepath, 'r');
        if (!$handle) {
            throw new Exception("Cannot open file");
        }
        
        // Read all lines first to analyze structure
        $allLines = [];
        while (($line = fgetcsv($handle, 0)) !== false) {
            $allLines[] = $line;
        }
        fclose($handle);
        
        if (empty($allLines)) {
            throw new Exception("Empty file");
        }
        
        // Find the actual header row (look for "S.No" or similar)
        $headerRowIndex = -1;
        $headers = [];
        
        for ($i = 0; $i < min(10, count($allLines)); $i++) {
            $row = $allLines[$i];
            if (isset($row[0]) && (stripos($row[0], 'S.No') !== false || 
                stripos($row[0], 'Serial') !== false || 
                is_numeric(trim($row[0])))) {
                $headerRowIndex = $i;
                $headers = $row;
                break;
            }
        }
        
        if ($headerRowIndex === -1) {
            foreach ($allLines as $i => $row) {
                if (!empty(array_filter($row))) {
                    $headerRowIndex = $i;
                    $headers = $row;
                    break;
                }
            }
        }
        
        if (empty($headers)) {
            throw new Exception("Could not find headers in file");
        }
        
        // Clean headers
        $headers = array_map(function($h, $index) {
            $h = trim($h);
            if (empty($h)) {
                return 'Column' . ($index + 1);
            }
            return preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower(str_replace(' ', '_', $h)));
        }, $headers, array_keys($headers));
        
        // Remove duplicate column names
        $seen = [];
        $headers = array_map(function($h) use (&$seen) {
            $original = $h;
            $counter = 1;
            while (in_array($h, $seen)) {
                $h = $original . '_' . $counter++;
            }
            $seen[] = $h;
            return $h;
        }, $headers);
        
        $allData = [];
        
        for ($i = $headerRowIndex + 1; $i < count($allLines); $i++) {
            $row = $allLines[$i];
            
            if (empty(array_filter($row))) continue;
            
            if (isset($row[0]) && (stripos($row[0], 'BACK TO') !== false || 
                stripos($row[0], 'Sales Tax') !== false ||
                stripos($row[0], 'HOAHOI') !== false)) continue;
            
            $rowData = [];
            $hasData = false;
            
            foreach ($headers as $index => $header) {
                $value = isset($row[$index]) ? trim($row[$index]) : '';
                $rowData[$header] = $value;
                if (!empty($value)) $hasData = true;
            }
            
            if ($hasData) {
                $allData[] = $rowData;
            }
        }
        
        if (empty($allData)) {
            throw new Exception("No valid data rows found after header");
        }
        
        // Store in database
        $jsonHeaders = json_encode($headers);
        $jsonData = json_encode($allData);
        
        // FIX: Store count in variable before passing to bind_param
        $rowCount = count($allData);
        $baseFilename = basename($filepath);
        
        $stmt = $con->prepare("INSERT INTO csv_data_store 
            (user_id, filename, original_name, file_path, csv_headers, csv_data, total_rows, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'completed')");
        
        // FIX: All parameters are now variables, not expressions
        $stmt->bind_param("isssssi", 
            $userId, 
            $baseFilename, 
            $originalName, 
            $filepath,
            $jsonHeaders,
            $jsonData,
            $rowCount
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Database error: " . $con->error);
        }
        
        $result['success'] = true;
        $result['totalRows'] = $rowCount;
        
    } catch (Exception $e) {
        $result['error'] = $e->getMessage();
    }
    
    return $result;
}

// Fetch all uploads
$allUploads = [];
$query = "SELECT id, original_name, total_rows, status, created_at FROM csv_data_store 
          WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $con->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $allUploads[] = $row;
}

// Pagination setup
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 50;
$tableData = [];
$tableHeaders = [];
$totalRows = 0;
$totalPages = 1;
$currentUpload = null;

if ($viewMode && $viewData) {
    $tableHeaders = json_decode($viewData['csv_headers'], true);
    $allRows = json_decode($viewData['csv_data'], true);
    $totalRows = count($allRows);
    $totalPages = ceil($totalRows / $perPage);
    
    $start = ($page - 1) * $perPage;
    $tableData = array_slice($allRows, $start, $perPage);
    $currentUpload = $viewData;
}
?>

<!DOCTYPE html>
<html lang="en" dir="">
<meta name="csrf-token" id="csrf-token" content="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">

<head>
   <title><?php echo $viewMode ? 'View CSV Data' : 'CSV Upload Module'; ?> - Syed Professional Services</title>
   <?php include "includes/links.php"; ?>
   
   <style>
        .upload-area {
            border: 3px dashed #dee2e6;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            transition: all 0.3s ease;
            background: #f8f9fa;
            cursor: pointer;
            position: relative;
        }
        
        .upload-area:hover, .upload-area.dragover {
            border-color: #0d6efd;
            background: #e7f1ff;
        }
        
        .upload-area i {
            font-size: 48px;
            color: #6c757d;
            margin-bottom: 15px;
        }
        
        .file-input { 
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        
        .preview-table {
            max-height: 400px;
            overflow: auto;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            background: white;
        }
        
        .preview-table table {
            font-size: 12px;
            margin-bottom: 0;
        }
        
        .preview-table th {
            background: #f8f9fa;
            position: sticky;
            top: 0;
            white-space: nowrap;
            font-weight: 600;
        }
        
        .data-table {
            font-size: 13px;
        }
        
        .data-table th {
            background: #343a40;
            color: white;
            position: sticky;
            top: 0;
            z-index: 10;
            white-space: nowrap;
            font-size: 12px;
        }
        
        .data-table td {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .csv-card {
            transition: all 0.2s;
            border-left: 4px solid #0d6efd;
        }
        
        .csv-card:hover {
            transform: translateX(5px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .status-badge {
            font-size: 0.75rem;
        }
        
        .progress { 
            height: 30px; 
            display: none;
            margin-top: 15px;
        }
        
        .table-responsive { 
            max-height: 600px; 
            overflow: auto;
            border-radius: 5px;
        }
        
        .pagination { justify-content: center; }
        
        .action-btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        
        .row-number {
            font-weight: bold;
            color: #6c757d;
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
         
         <!-- Header -->
         <div class="card card-body mb-4">
            <div class="page-block">
               <div class="row align-items-center">
                  <div class="col-auto">
                     <div class="page-header-title">
                        <h4 class="m-b-10">
                            <?php if ($viewMode): ?>
                                <i class="ti ti-file-text me-2"></i><?php echo htmlspecialchars($currentUpload['original_name']); ?>
                            <?php else: ?>
                                <i class="ti ti-upload me-2"></i>CSV Upload Module
                            <?php endif; ?>
                        </h4>
                     </div>
                     <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                        <?php if ($viewMode): ?>
                            <li class="breadcrumb-item"><a href="csv-upload.php">CSV Upload</a></li>
                            <li class="breadcrumb-item active">View Data</li>
                        <?php else: ?>
                            <li class="breadcrumb-item active">Upload CSV</li>
                        <?php endif; ?>
                     </ul>
                  </div>
                  <div class="col text-end">
                     <?php if ($viewMode): ?>
                        <button class="btn btn-success btn-sm me-2" onclick="exportCurrentData()">
                            <i class="ti ti-download me-1"></i>Export CSV
                        </button>
                        <a href="csv-upload.php" class="btn btn-secondary btn-sm">
                            <i class="ti ti-arrow-left me-1"></i>Back to Uploads
                        </a>
                     <?php else: ?>
                        <span class="text-muted me-3"><?php echo count($allUploads); ?> file(s) uploaded</span>
                        <button class="btn btn-primary" onclick="triggerFileInput()">
                            <i class="ti ti-plus me-2"></i>Upload New CSV
                        </button>
                     <?php endif; ?>
                  </div>
               </div>
            </div>
         </div>
         
         <?php if ($message): ?>
         <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <i class="ti ti-<?php echo $messageType === 'success' ? 'check' : 'alert-circle'; ?> me-2"></i>
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
         </div>
         <?php endif; ?>
         
         <?php if (!$viewMode): ?>
         <!-- UPLOAD MODE -->
         
         <div class="row">
            <div class="col-lg-8">
               <div class="card">
                  <div class="card-header bg-primary text-white">
                     <h5 class="mb-0"><i class="ti ti-cloud-upload me-2"></i>Upload Sales Tax CSV</h5>
                  </div>
                  <div class="card-body">
                     <form id="uploadForm" method="POST" enctype="multipart/form-data" action="">
                        <div class="upload-area" id="dropZone">
                           <i class="ti ti-file-upload"></i>
                           <h4>Drop CSV File Here</h4>
                           <p class="text-muted mb-2">or click anywhere in this box to browse</p>
                           <small class="text-muted d-block mb-2">Supports complex spreadsheets with multiple headers</small>
                           <span class="badge bg-secondary">Max 10MB</span>
                           <input type="file" name="csv_file" id="csvFile" class="file-input" accept=".csv,.txt" onchange="handleFileSelect(this)">
                        </div>
                        
                        <div class="progress" id="progressBar">
                           <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" style="width: 100%">
                              <i class="ti ti-loader me-2"></i>Processing file...
                           </div>
                        </div>
                     </form>
                     
                     <!-- Preview Section -->
                     <div id="filePreview" style="display: none;" class="mt-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h6 class="mb-1">Preview: <span id="fileName" class="text-primary fw-bold"></span></h6>
                                <small class="text-muted" id="fileInfo"></small>
                            </div>
                            <span class="badge bg-info fs-6" id="rowCountBadge">0 rows</span>
                        </div>
                        
                        <div class="preview-table mb-3">
                           <table class="table table-sm table-bordered" id="previewTable">
                              <thead class="table-dark"></thead>
                              <tbody></tbody>
                           </table>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">
                                <i class="ti ti-x me-1"></i>Cancel
                            </button>
                            <button type="submit" form="uploadForm" class="btn btn-success btn-lg" id="uploadBtn">
                                <i class="ti ti-database-import me-2"></i>Save to Database
                            </button>
                        </div>
                     </div>
                  </div>
               </div>
               
               <!-- Instructions -->
               <div class="card mt-3 border-info">
                  <div class="card-header bg-info text-white">
                     <h5 class="mb-0"><i class="ti ti-info-circle me-2"></i>How to Use</h5>
                  </div>
                  <div class="card-body">
                     <div class="row">
                        <div class="col-md-6">
                            <h6 class="fw-bold"><i class="ti ti-check text-success me-2"></i>Supported Formats:</h6>
                            <ul class="small text-muted">
                                <li>Standard CSV files</li>
                                <li>Excel-exported CSV</li>
                                <li>Files with multiple header rows</li>
                                <li>Tab, semicolon, or pipe delimited</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold"><i class="ti ti-check text-success me-2"></i>Features:</h6>
                            <ul class="small text-muted">
                                <li>Auto-detects header row (looks for S.No)</li>
                                <li>Handles empty columns automatically</li>
                                <li>Stores in single table (csv_data_store)</li>
                                <li>View, paginate, and export anytime</li>
                            </ul>
                        </div>
                     </div>
                  </div>
               </div>
            </div>
            
            <!-- Uploads List -->
            <div class="col-lg-4">
               <div class="card">
                  <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                     <h5 class="mb-0"><i class="ti ti-history me-2"></i>Recent Uploads</h5>
                     <span class="badge bg-light text-dark"><?php echo count($allUploads); ?></span>
                  </div>
                  <div class="card-body p-0" style="max-height: 600px; overflow-y: auto;">
                     <?php if (empty($allUploads)): ?>
                        <div class="text-center p-5 text-muted">
                            <i class="ti ti-inbox fs-1 mb-3 d-block text-secondary"></i>
                            <p>No uploads yet</p>
                            <small>Upload your first CSV file to see it here</small>
                        </div>
                     <?php else: ?>
                        <div class="list-group list-group-flush">
                           <?php foreach ($allUploads as $upload): ?>
                           <div class="list-group-item csv-card p-3">
                              <div class="d-flex justify-content-between align-items-start mb-2">
                                 <div class="text-truncate" style="max-width: 180px;" title="<?php echo htmlspecialchars($upload['original_name']); ?>">
                                    <i class="ti ti-file-text text-primary me-2"></i>
                                    <strong><?php echo htmlspecialchars($upload['original_name']); ?></strong>
                                 </div>
                                 <small class="text-muted"><?php echo date('M d', strtotime($upload['created_at'])); ?></small>
                              </div>
                              
                              <div class="d-flex justify-content-between align-items-center mb-2">
                                 <span class="badge bg-<?php 
                                    echo $upload['status'] === 'completed' ? 'success' : 
                                         ($upload['status'] === 'failed' ? 'danger' : 'warning'); 
                                 ?> bg-opacity-75">
                                    <?php echo ucfirst($upload['status']); ?>
                                 </span>
                                 <small class="text-muted">
                                    <i class="ti ti-rows me-1"></i><?php echo number_format($upload['total_rows']); ?> rows
                                 </small>
                              </div>
                              
                              <div class="btn-group w-100 btn-group-sm">
                                 <?php if ($upload['status'] === 'completed'): ?>
                                 <a href="?view=<?php echo $upload['id']; ?>" class="btn btn-outline-primary">
                                    <i class="ti ti-eye me-1"></i>View
                                 </a>
                                 <?php endif; ?>
                                 <a href="?delete=<?php echo $upload['id']; ?>" class="btn btn-outline-danger" 
                                    onclick="return confirm('Permanently delete this file?')">
                                    <i class="ti ti-trash me-1"></i>Delete
                                 </a>
                              </div>
                           </div>
                           <?php endforeach; ?>
                        </div>
                     <?php endif; ?>
                  </div>
               </div>
            </div>
         </div>
         
         <?php else: ?>
         <!-- VIEW MODE -->
         
         <div class="card shadow-sm">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center py-3">
               <div>
                   <h4 class="mb-1"><i class="ti ti-table me-2"></i><?php echo htmlspecialchars($currentUpload['original_name']); ?></h4>
                   <small class="opacity-75">
                       <i class="ti ti-calendar me-1"></i>Uploaded <?php echo date('F d, Y \a\t h:i A', strtotime($currentUpload['created_at'])); ?>
                   </small>
               </div>
               <div class="text-end">
                   <h2 class="mb-0"><?php echo number_format($totalRows); ?></h2>
                   <small>Total Records</small>
               </div>
            </div>
            
            <div class="card-body p-0">
               <div class="table-responsive">
                  <table class="table table-striped table-hover data-table mb-0" id="dataTable">
                     <thead>
                        <tr>
                           <th style="width: 60px; background: #212529;">#</th>
                           <?php foreach ($tableHeaders as $header): ?>
                           <th title="<?php echo htmlspecialchars($header); ?>">
                               <?php echo htmlspecialchars(str_replace('_', ' ', $header)); ?>
                           </th>
                           <?php endforeach; ?>
                        </tr>
                     </thead>
                     <tbody>
                        <?php 
                        $rowNum = ($page - 1) * $perPage;
                        foreach ($tableData as $row): 
                        $rowNum++;
                        ?>
                        <tr>
                           <td class="row-number text-center"><?php echo $rowNum; ?></td>
                           <?php foreach ($tableHeaders as $header): ?>
                           <td title="<?php echo htmlspecialchars($row[$header] ?? ''); ?>">
                               <?php 
                               $val = $row[$header] ?? '';
                               echo htmlspecialchars(strlen($val) > 50 ? substr($val, 0, 50) . '...' : $val); 
                               ?>
                           </td>
                           <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                     </tbody>
                  </table>
               </div>
            </div>
            
            <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-light py-3">
               <nav class="mb-2">
                  <ul class="pagination pagination-sm justify-content-center mb-0">
                     <?php if ($page > 1): ?>
                     <li class="page-item">
                        <a class="page-link" href="?view=<?php echo $currentUpload['id']; ?>&page=1">« First</a>
                     </li>
                     <li class="page-item">
                        <a class="page-link" href="?view=<?php echo $currentUpload['id']; ?>&page=<?php echo $page-1; ?>">‹ Prev</a>
                     </li>
                     <?php endif; ?>
                     
                     <?php 
                     $startPage = max(1, $page - 2);
                     $endPage = min($totalPages, $page + 2);
                     
                     if ($startPage > 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                     
                     for ($i = $startPage; $i <= $endPage; $i++): 
                     ?>
                     <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?view=<?php echo $currentUpload['id']; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                     </li>
                     <?php endfor; 
                     
                     if ($endPage < $totalPages) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                     ?>
                     
                     <?php if ($page < $totalPages): ?>
                     <li class="page-item">
                        <a class="page-link" href="?view=<?php echo $currentUpload['id']; ?>&page=<?php echo $page+1; ?>">Next ›</a>
                     </li>
                     <li class="page-item">
                        <a class="page-link" href="?view=<?php echo $currentUpload['id']; ?>&page=<?php echo $totalPages; ?>">Last »</a>
                     </li>
                     <?php endif; ?>
                  </ul>
               </nav>
               <div class="text-center text-muted small">
                   Page <?php echo $page; ?> of <?php echo $totalPages; ?> | 
                   Showing <?php echo (($page - 1) * $perPage) + 1; ?> - <?php echo min($page * $perPage, $totalRows); ?> of <?php echo number_format($totalRows); ?> rows
               </div>
            </div>
            <?php endif; ?>
         </div>
         
         <?php endif; ?>
         
      </div>
   </div>
   
   <?php include "includes/footer.php"; ?>
   <?php include "includes/scripts.php"; ?>
   
   <script>
   function triggerFileInput() {
       document.getElementById('csvFile').click();
   }
   
   function handleFileSelect(input) {
       if (input.files && input.files[0]) {
           const file = input.files[0];
           
           if (!file.name.match(/\.(csv|txt)$/i)) {
               alert('Please select a CSV or TXT file');
               input.value = '';
               return;
           }
           
           if (file.size > 10 * 1024 * 1024) {
               alert('File too large. Maximum size is 10MB.');
               input.value = '';
               return;
           }
           
           document.getElementById('fileName').textContent = file.name;
           document.getElementById('fileInfo').textContent = (file.size / 1024).toFixed(2) + ' KB';
           document.getElementById('dropZone').style.display = 'none';
           document.getElementById('filePreview').style.display = 'block';
           
           const reader = new FileReader();
           reader.onload = function(e) {
               const text = e.target.result;
               const lines = text.split('\n').filter(line => line.trim());
               
               if (lines.length === 0) {
                   alert('File is empty');
                   resetForm();
                   return;
               }
               
               const delimiter = lines[0].includes('\t') ? '\t' : 
                                lines[0].includes(';') ? ';' : ',';
               
               let headerLine = 0;
               for (let i = 0; i < Math.min(10, lines.length); i++) {
                   const cols = lines[i].split(delimiter);
                   if (cols[0] && (cols[0].includes('S.No') || !isNaN(parseInt(cols[0])))) {
                       headerLine = i;
                       break;
                   }
               }
               
               const headers = lines[headerLine].split(delimiter).map(h => h.trim());
               const dataRows = lines.slice(headerLine + 1).filter(l => l.trim());
               
               document.getElementById('rowCountBadge').textContent = dataRows.length + ' data rows found';
               
               let theadHtml = '<tr><th style="width:40px">#</th>';
               headers.forEach(h => {
                   theadHtml += `<th>${escapeHtml(h || 'Column')}</th>`;
               });
               theadHtml += '</tr>';
               
               let tbodyHtml = '';
               dataRows.slice(0, 5).forEach((row, idx) => {
                   const cols = row.split(delimiter);
                   tbodyHtml += `<tr><td class="text-muted">${idx + 1}</td>`;
                   cols.forEach(cell => {
                       tbodyHtml += `<td>${escapeHtml(cell.trim())}</td>`;
                   });
                   for (let i = cols.length; i < headers.length; i++) {
                       tbodyHtml += `<td></td>`;
                   }
                   tbodyHtml += '</tr>';
               });
               
               if (dataRows.length > 5) {
                   tbodyHtml += `<tr><td colspan="${headers.length + 1}" class="text-center text-muted py-2">
                       <i class="ti ti-dots me-2"></i>... and ${dataRows.length - 5} more rows
                   </td></tr>`;
               }
               
               document.querySelector('#previewTable thead').innerHTML = theadHtml;
               document.querySelector('#previewTable tbody').innerHTML = tbodyHtml;
           };
           reader.readAsText(file);
       }
   }
   
   function resetForm() {
       document.getElementById('uploadForm').reset();
       document.getElementById('dropZone').style.display = 'block';
       document.getElementById('filePreview').style.display = 'none';
       document.getElementById('progressBar').style.display = 'none';
   }
   
   document.getElementById('uploadForm').addEventListener('submit', function(e) {
       const btn = document.getElementById('uploadBtn');
       btn.disabled = true;
       btn.innerHTML = '<i class="ti ti-loader ti-spin me-2"></i>Saving...';
       document.getElementById('progressBar').style.display = 'block';
   });
   
   const dropZone = document.getElementById('dropZone');
   
   ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
       dropZone.addEventListener(eventName, preventDefaults, false);
   });
   
   function preventDefaults(e) {
       e.preventDefault();
       e.stopPropagation();
   }
   
   ['dragenter', 'dragover'].forEach(eventName => {
       dropZone.addEventListener(eventName, highlight, false);
   });
   
   ['dragleave', 'drop'].forEach(eventName => {
       dropZone.addEventListener(eventName, unhighlight, false);
   });
   
   function highlight(e) {
       dropZone.classList.add('dragover');
   }
   
   function unhighlight(e) {
       dropZone.classList.remove('dragover');
   }
   
   dropZone.addEventListener('drop', handleDrop, false);
   
   function handleDrop(e) {
       const dt = e.dataTransfer;
       const files = dt.files;
       document.getElementById('csvFile').files = files;
       handleFileSelect(document.getElementById('csvFile'));
   }
   
   function escapeHtml(text) {
       const div = document.createElement('div');
       div.textContent = text;
       return div.innerHTML;
   }
   
   <?php if ($viewMode): ?>
   function exportCurrentData() {
       const table = document.getElementById('dataTable');
       let csv = [];
       
       const headers = [];
       table.querySelectorAll('thead th').forEach(th => {
           headers.push('"' + (th.textContent || '').replace(/"/g, '""') + '"');
       });
       csv.push(headers.join(','));
       
       table.querySelectorAll('tbody tr').forEach(tr => {
           const row = [];
           tr.querySelectorAll('td').forEach(td => {
               row.push('"' + (td.textContent || '').replace(/"/g, '""') + '"');
           });
           csv.push(row.join(','));
       });
       
       const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
       const url = window.URL.createObjectURL(blob);
       const a = document.createElement('a');
       a.href = url;
       a.download = '<?php echo preg_replace('/[^a-zA-Z0-9]/', '_', $currentUpload['original_name']); ?>_export.csv';
       document.body.appendChild(a);
       a.click();
       document.body.removeChild(a);
       window.URL.revokeObjectURL(url);
   }
   <?php endif; ?>
   </script>
   
</body>
</html>