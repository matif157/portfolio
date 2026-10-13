
<?php

// Set your working directory as the default root path
// $rootPath = 'C:\xampp\htdocs\HRMCRM1.6';
// $rootName = 'HRMCRM1.6';

// Alternative paths (commented out)
// $rootPath = getenv('USERPROFILE') . '\\Documents';
// $rootName = 'Documents';
// $rootPath = 'C:\\Users\\ocm\\Desktop\\New folder (2)';
// $rootName = 'New folder (2)';
// linux
$rootPath = '/var/www/html/syedpro_new';
$rootName = 'syedpro_new';

include "includes/db.php";
include "includes/auth.php";



if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('ACCESS_PASSWORD', 'skipper@');   
define('SESSION_KEY', 'skipper_auth');
define('SESSION_LIFETIME', 15 * 60);       

 


$now = time();
if (
    isset($_SESSION[SESSION_KEY], $_SESSION['skipper_expires']) &&
    $_SESSION[SESSION_KEY] === true &&
    $_SESSION['skipper_expires'] >= $now
) {
    
    $_SESSION['skipper_expires'] = $now + SESSION_LIFETIME;
} else {
     
    unset($_SESSION[SESSION_KEY], $_SESSION['skipper_expires']);

    
    
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
// Initialize session for various features
if (!isset($_SESSION['file_tags'])) {
    $_SESSION['file_tags'] = [];
}
if (!isset($_SESSION['recent_files'])) {
    $_SESSION['recent_files'] = [];
}
if (!isset($_SESSION['activity_log'])) {
    $_SESSION['activity_log'] = [];
}
if (!isset($_SESSION['shared_files'])) {
    $_SESSION['shared_files'] = [];
}
if (!isset($_SESSION['file_versions'])) {
    $_SESSION['file_versions'] = [];
}

function set_message(string $type, string $text)
{
    $_SESSION['message'] = ['type' => $type, 'text' => $text];
}

// Normalize path to use the correct directory separator for the current platform
function normalize_path(string $path): string
{
    return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}

// Convert a path to use forward slashes (for URLs and JavaScript)
function to_forward_slashes(string $path): string
{
    return str_replace('\\', '/', $path);
}

function get_valid_path(string $path): string
{
    global $rootPath;
    $realRoot = realpath($rootPath);
    if ($realRoot === false || !is_dir($realRoot)) {
        set_message('error', 'Root directory not found.');
        return $rootPath;
    }

    $userPath = normalize_path(trim((string) $path));
    if ($userPath === '') {
        return $realRoot;
    }

    $startsWithSeparator = substr($userPath, 0, 1) === DIRECTORY_SEPARATOR;
    $isAbsolutePath = preg_match('/^[A-Za-z]:[\/\\\\]/', $userPath) || $startsWithSeparator;
    $candidatePath = $isAbsolutePath ? $userPath : ($realRoot . DIRECTORY_SEPARATOR . ltrim($userPath, DIRECTORY_SEPARATOR));
    $candidatePath = normalize_path($candidatePath);

    if (file_exists($candidatePath)) {
        $resolved = realpath($candidatePath);
        if ($resolved === false) {
            return $realRoot;
        }
        $candidatePath = normalize_path($resolved);
    } else {
        $parentPath = dirname($candidatePath);
        $resolvedParent = realpath($parentPath);
        if ($resolvedParent === false) {
            return $realRoot;
        }
        $candidatePath = normalize_path($resolvedParent . DIRECTORY_SEPARATOR . basename($candidatePath));
    }

    $normalizedRoot = normalize_path($realRoot);
    $rootForCheck = DIRECTORY_SEPARATOR === '\\' ? strtolower($normalizedRoot) : $normalizedRoot;
    $candidateForCheck = DIRECTORY_SEPARATOR === '\\' ? strtolower($candidatePath) : $candidatePath;

    if (strpos($candidateForCheck, $rootForCheck) !== 0) {
        return $realRoot;
    }
    return $candidatePath;
}

function log_activity(string $action, string $item, string $path)
{
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'user' => $_SESSION['username'] ?? 'Unknown',
        'action' => $action,
        'item' => $item,
        'path' => $path
    ];
    $_SESSION['activity_log'][] = $logEntry;

    // Keep only last 100 entries in session
    if (count($_SESSION['activity_log']) > 100) {
        $_SESSION['activity_log'] = array_slice($_SESSION['activity_log'], -100);
    }

    // Create the err_editor directory if it doesn't exist
    $logDir = __DIR__ . '/includes/err_editor';

    if (!is_dir($logDir)) {
        if (!mkdir($logDir, 0775, true)) {
            // If we can't create the directory, just log to session
            return;
        }
    }

    // Create log filename with current date format: YYYY-MM-DD.log
    $logFile = $logDir . '/' . date('Y-m-d') . '.log';

    // Format the log entry as a readable line
    $logLine = sprintf(
        "[%s] User: %s | Action: %s | Item: %s | Path: %s\n",
        $logEntry['timestamp'],
        $logEntry['user'],
        $logEntry['action'],
        $logEntry['item'],
        $logEntry['path']
    );

    // Append to the log file
    file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
}

function add_to_recent(string $filePath)
{
    $realPath = realpath($filePath);
    if ($realPath === false)
        return;

    // Remove if already exists
    $_SESSION['recent_files'] = array_filter($_SESSION['recent_files'], function ($item) use ($realPath) {
        return $item !== $realPath;
    });

    // Add to beginning
    array_unshift($_SESSION['recent_files'], $realPath);

    // Keep only last 20 entries
    if (count($_SESSION['recent_files']) > 20) {
        $_SESSION['recent_files'] = array_pop($_SESSION['recent_files']);
    }
}

function get_storage_usage(): array
{
    global $rootPath;
    $total = disk_total_space($rootPath);
    $free = disk_free_space($rootPath);
    $used = $total - $free;

    return [
        'total' => $total,
        'used' => $used,
        'free' => $free,
        'percent' => round(($used / $total) * 100, 2)
    ];
}

function create_file_version(string $filePath): bool
{
    // Ensure we're working with an absolute path
    $filePath = get_valid_path($filePath);

    if (!is_file($filePath))
        return false;

    $versionDir = dirname($filePath) . DIRECTORY_SEPARATOR . '.versions';
    if (!is_dir($versionDir)) {
        mkdir($versionDir, 0755, true);
    }

    $fileName = basename($filePath);
    $timestamp = date('Y-m-d_H-i-s');
    $versionFile = $versionDir . DIRECTORY_SEPARATOR . $fileName . '_' . $timestamp;

    if (copy($filePath, $versionFile)) {
        if (!isset($_SESSION['file_versions'][$filePath])) {
            $_SESSION['file_versions'][$filePath] = [];
        }
        $_SESSION['file_versions'][$filePath][] = $versionFile;
        return true;
    }
    return false;
}

$currentDirPath = $_GET['path'] ?? '';
$currentDirAbsPath = get_valid_path($currentDirPath);

// Calculate relative path with consistent separator handling
$relativePath = str_replace(
    [normalize_path(realpath($rootPath)), '\\'],
    ['', '/'],
    normalize_path($currentDirAbsPath)
);
$relativePath = ltrim($relativePath, '/');

// Sort options
$sortBy = $_GET['sort'] ?? 'name';
$sortOrder = $_GET['order'] ?? 'asc';
$_SESSION['sort'] = $sortBy;
$_SESSION['order'] = $sortOrder;

// Search functionality
$searchQuery = $_GET['search'] ?? '';
$allItems = [];
$searchResults = [];

if ($searchQuery) {
    $searchItems = @scandir($currentDirAbsPath) ?: [];
    foreach ($searchItems as $item) {
        if ($item === '.' || $item === '..')
            continue;
        $fullPath = $currentDirAbsPath . DIRECTORY_SEPARATOR . $item;
        if (!is_readable($fullPath))
            continue;

        if (stripos($item, $searchQuery) !== false) {
            $data = [
                'name' => $item,
                'time' => date('M d, Y h:i A', filemtime($fullPath)),
                'rel' => trim($relativePath . '/' . $item, '/'),
                'type' => is_dir($fullPath) ? 'folder' : 'file',
                'size' => is_file($fullPath) ? filesize($fullPath) : 0,
                'path' => $fullPath
            ];
            $searchResults[] = $data;
        }
    }
    usort($searchResults, function ($a, $b) use ($sortBy, $sortOrder) {
        $result = 0;
        switch ($sortBy) {
            case 'name':
                $result = strcasecmp($a['name'], $b['name']);
                break;
            case 'size':
                $result = $a['size'] - $b['size'];
                break;
            case 'date':
                $result = strtotime($a['time']) - strtotime($b['time']);
                break;
        }
        return $sortOrder === 'desc' ? -$result : $result;
    });
}

function delete_recursive(string $path): bool
{
    if (is_file($path) || is_link($path)) {
        return @unlink($path);
    }
    if (!is_dir($path)) {
        return false;
    }
    $items = scandir($path);
    if ($items === false) {
        return false;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        if (!delete_recursive($path . DIRECTORY_SEPARATOR . $item)) {
            return false;
        }
    }
    return @rmdir($path);
}

// Delete
if (isset($_GET['del'])) {
    $itemRelPath = trim($relativePath . '/' . $_GET['del'], '/');
    $itemToDelete = get_valid_path($itemRelPath);

    $isDir = is_dir($itemToDelete);
    $isFile = is_file($itemToDelete);

    if ($isFile && unlink($itemToDelete)) {
        log_activity('delete', $_GET['del'], $relativePath);
        set_message('success', 'File deleted successfully.');
    } elseif ($isDir && delete_recursive($itemToDelete)) {
        log_activity('delete', $_GET['del'], $relativePath);
        set_message('success', 'Folder deleted successfully.');
    } elseif ($isDir) {
        set_message('error', 'Failed to delete folder.');
    } else {
        set_message('error', 'Failed to delete item.');
    }

    $redirectPath = $relativePath;
    header('Location: ?path=' . urlencode($redirectPath) . '&search=' . urlencode($searchQuery));
    exit;
}

// Rename
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rename_item'])) {
    $oldName = $_POST['rename_item'];
    $newName = preg_replace('/[^a-zA-Z0-9 _.-]/', '_', trim($_POST['new_name']));

    if (empty($newName)) {
        set_message('error', 'Invalid name.');
    } else {
        $oldPath = get_valid_path($currentDirAbsPath . DIRECTORY_SEPARATOR . $oldName);
        $newPath = get_valid_path($currentDirAbsPath . DIRECTORY_SEPARATOR . $newName);

        if (file_exists($newPath)) {
            set_message('error', 'An item with this name already exists.');
        } elseif (rename($oldPath, $newPath)) {
            log_activity('rename', "$oldName to $newName", $relativePath);
            set_message('success', "Item renamed successfully.");
        } else {
            set_message('error', 'Failed to rename item.');
        }
    }
    header('Location: ?path=' . urlencode($relativePath) . '&search=' . urlencode($searchQuery));
    exit;
}

// Move/Copy
function resolve_target_path(string $targetPath, string $currentDirAbsPath): string
{
    global $rootPath;
    $targetPath = trim($targetPath);
    if ($targetPath === '' || $targetPath === '.') {
        return get_valid_path($currentDirAbsPath);
    }
    if ($targetPath === '..') {
        return get_valid_path(dirname($currentDirAbsPath));
    }
    if ($targetPath === '/' || $targetPath === '\\') {
        return get_valid_path('');
    }

    $normalizedTarget = normalize_path($targetPath);
    $realRoot = realpath($rootPath) ?: $rootPath;
    $startsWithSeparator = substr($normalizedTarget, 0, 1) === DIRECTORY_SEPARATOR;
    $isAbsolutePath = preg_match('/^[A-Za-z]:[\/\\\\]/', $normalizedTarget) || $startsWithSeparator;

    if ($isAbsolutePath) {
        return get_valid_path($normalizedTarget);
    }

    // Support both current-folder-relative and root-relative destination values.
    $fromCurrent = normalize_path($currentDirAbsPath . DIRECTORY_SEPARATOR . ltrim($normalizedTarget, DIRECTORY_SEPARATOR));
    $fromRoot = normalize_path($realRoot . DIRECTORY_SEPARATOR . ltrim($normalizedTarget, DIRECTORY_SEPARATOR));

    if (is_dir($fromCurrent)) {
        return get_valid_path($fromCurrent);
    }
    if (is_dir($fromRoot)) {
        return get_valid_path($fromRoot);
    }
    return get_valid_path($fromCurrent);
}

function get_nonconflicting_path(string $path): string
{
    if (!file_exists($path)) {
        return $path;
    }

    $dir = dirname($path);
    $base = basename($path);
    $dotPos = strrpos($base, '.');

    if ($dotPos === false || $dotPos === 0) {
        $name = $base;
        $ext = '';
    } else {
        $name = substr($base, 0, $dotPos);
        $ext = substr($base, $dotPos);
    }

    $i = 1;
    do {
        $suffix = $i === 1 ? ' - Copy' : " - Copy ($i)";
        $candidate = $dir . DIRECTORY_SEPARATOR . $name . $suffix . $ext;
        $i++;
    } while (file_exists($candidate));

    return $candidate;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['move_copy'])) {
    $action = $_POST['action']; // 'move' or 'copy'
    $items = $_POST['items'] ?? []; // Array of items to move/copy
    $targetPath = $_POST['target_path'] ?? '';

    if (!is_array($items)) {
        $items = explode(',', (string) $items);
    }
    $normalizedItems = [];
    foreach ($items as $item) {
        foreach (explode(',', (string) $item) as $part) {
            $part = trim($part);
            if ($part !== '') {
                $normalizedItems[] = $part;
            }
        }
    }
    $items = array_values(array_unique($normalizedItems));

    if (empty($items) || empty($targetPath)) {
        set_message('error', 'Please select items and a target path.');
    } else {
        $targetAbsPath = resolve_target_path($targetPath, $currentDirAbsPath);
        if (!is_dir($targetAbsPath)) {
            set_message('error', 'Target folder does not exist.');
            header('Location: ?path=' . urlencode($relativePath) . '&search=' . urlencode($searchQuery));
            exit;
        }
        $success = 0;
        $failed = 0;

        foreach ($items as $item) {
            $sourcePath = get_valid_path($currentDirAbsPath . DIRECTORY_SEPARATOR . $item);
            $destPath = $targetAbsPath . DIRECTORY_SEPARATOR . $item;
            if (!file_exists($sourcePath)) {
                $failed++;
                continue;
            }

            $samePath = normalize_path($sourcePath) === normalize_path($destPath);
            if ($action === 'copy') {
                if ($samePath || file_exists($destPath)) {
                    $destPath = get_nonconflicting_path($destPath);
                }
            } else {
                if ($samePath) {
                    $failed++;
                    continue;
                }
                if (file_exists($destPath)) {
                    $destPath = get_nonconflicting_path($destPath);
                }
            }

            if (is_dir($sourcePath)) {
                if (strpos(normalize_path($destPath), normalize_path($sourcePath) . DIRECTORY_SEPARATOR) === 0) {
                    $failed++;
                    continue;
                }
                if ($action === 'move') {
                    if (rename($sourcePath, $destPath)) {
                        $success++;
                        log_activity('move', $item, "$relativePath → $targetPath");
                    } else {
                        $failed++;
                    }
                } else { // copy
                    if (copy_recursive($sourcePath, $destPath)) {
                        $success++;
                        log_activity('copy', $item, "$relativePath → $targetPath");
                    } else {
                        $failed++;
                    }
                }
            } else {
                if ($action === 'move') {
                    if (rename($sourcePath, $destPath)) {
                        $success++;
                        log_activity('move', $item, "$relativePath → $targetPath");
                    } else {
                        $failed++;
                    }
                } else { // copy
                    if (copy($sourcePath, $destPath)) {
                        $success++;
                        log_activity('copy', $item, "$relativePath → $targetPath");
                    } else {
                        $failed++;
                    }
                }
            }
        }

        if ($failed > 0) {
            set_message('warning', "$success items processed successfully, $failed failed.");
        } else {
            set_message('success', "All $success items processed successfully.");
        }
    }
    header('Location: ?path=' . urlencode($relativePath) . '&search=' . urlencode($searchQuery));
    exit;
}

// Helper function for recursive directory copy
function copy_recursive($src, $dst)
{
    // Ensure we're working with absolute paths
    $src = get_valid_path($src);
    $dst = get_valid_path($dst);

    if (is_dir($src)) {
        if (!mkdir($dst, 0755, true)) {
            return false;
        }
        $files = scandir($src);
        foreach ($files as $file) {
            if ($file != "." && $file != "..") {
                if (!copy_recursive("$src/$file", "$dst/$file")) {
                    return false;
                }
            }
        }
        return true;
    } elseif (file_exists($src)) {
        return copy($src, $dst);
    }
    return false;
}

// Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['upload_file'])) {
    $uploaded = 0;
    $failed = 0;

    $names = $_FILES['upload_file']['name'];
    $tmpNames = $_FILES['upload_file']['tmp_name'];
    $errors = $_FILES['upload_file']['error'];

    if (!is_array($names)) {
        $names = [$names];
        $tmpNames = [$tmpNames];
        $errors = [$errors];
    }

    foreach ($names as $idx => $rawName) {
        if (($errors[$idx] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $failed++;
            continue;
        }
        $fileName = basename((string) $rawName);
        if ($fileName === '') {
            $failed++;
            continue;
        }
        $target = get_valid_path($currentDirAbsPath . DIRECTORY_SEPARATOR . $fileName);
        if (move_uploaded_file($tmpNames[$idx], $target)) {
            $uploaded++;
            log_activity('upload', $fileName, $relativePath);
            add_to_recent($target);
        } else {
            $failed++;
        }
    }

    if ($uploaded > 0 && $failed === 0) {
        set_message('success', "$uploaded file(s) uploaded successfully.");
    } elseif ($uploaded > 0) {
        set_message('warning', "$uploaded file(s) uploaded, $failed failed.");
    } else {
        set_message('error', 'Upload failed.');
    }
    header('Location: ?path=' . urlencode($relativePath) . '&search=' . urlencode($searchQuery));
    exit;
}

// New File
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_file_name'])) {
    $fileName = preg_replace('/[^a-zA-Z0-9 _.\-]/', '_', trim($_POST['new_file_name']));
    if (empty($fileName)) {
        set_message('error', 'Invalid file name.');
    } else {
        $newFilePath = get_valid_path($currentDirAbsPath . DIRECTORY_SEPARATOR . $fileName);
        if (file_exists($newFilePath)) {
            set_message('error', 'File already exists.');
        } elseif (file_put_contents($newFilePath, '') !== false) {
            log_activity('create_file', $fileName, $relativePath);
            set_message('success', "File '$fileName' created.");
        } else {
            set_message('error', 'Failed to create file.');
        }
    }
    header('Location: ?path=' . urlencode($relativePath) . '&search=' . urlencode($searchQuery));
    exit;
}

// New Folder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_folder_name'])) {
    $folderName = preg_replace('/[^a-zA-Z0-9 _.-]/', '_', trim($_POST['new_folder_name']));
    if (empty($folderName)) {
        set_message('error', 'Invalid folder name.');
    } else {
        $newPath = get_valid_path($currentDirAbsPath . DIRECTORY_SEPARATOR . $folderName);
        if (!file_exists($newPath)) {
            if (mkdir($newPath, 0775, true)) {
                log_activity('create_folder', $folderName, $relativePath);
                set_message('success', "Folder '$folderName' created.");
            } else {
                set_message('error', 'Failed to create folder.');
            }
        } else {
            set_message('error', 'Folder already exists.');
        }
    }
    header('Location: ?path=' . urlencode($relativePath) . '&search=' . urlencode($searchQuery));
    exit;
}

// View file
if (isset($_GET['view'])) {

    ob_clean();

    $fileRelPath = $_GET['view'];

    // Security: prevent directory traversal
    if (strpos($fileRelPath, '..') !== false || strpos($fileRelPath, '\\') === 0 || strpos($fileRelPath, '/') === 0) {
        http_response_code(403);
        exit('Invalid path');
    }

    // Build absolute path properly
    $fileToView = realpath($rootPath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $fileRelPath));
    $realRoot = realpath($rootPath);

    // Security: ensure file is within root
    if ($fileToView === false || strpos($fileToView, $realRoot) !== 0) {
        http_response_code(404);
        exit('File not found');
    }

    if (is_file($fileToView) && is_readable($fileToView)) {
        add_to_recent($fileToView);

        $mime = mime_content_type($fileToView) ?: 'text/plain';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . basename($fileToView) . '"');
        header('Content-Length: ' . filesize($fileToView));
        header('Cache-Control: no-cache');

        readfile($fileToView);
        exit;
    } else {
        http_response_code(404);
        exit('File not found or not readable');
    }
}


// Download as zip
if (isset($_GET['download_zip'])) {
    $items = explode(',', $_GET['items'] ?? '');
    if (empty($items)) {
        set_message('error', 'No items selected for download.');
        header('Location: ?path=' . urlencode($relativePath));
        exit;
    }

    $zipFile = tempnam(sys_get_temp_dir(), 'zip_');
    $zip = new ZipArchive();

    if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        foreach ($items as $item) {
            $itemPath = get_valid_path($currentDirAbsPath . DIRECTORY_SEPARATOR . $item);
            if (is_file($itemPath)) {
                $zip->addFile($itemPath, $item);
            } elseif (is_dir($itemPath)) {
                add_folder_to_zip($zip, $itemPath, $item);
            }
        }
        $zip->close();

        log_activity('download_zip', count($items) . ' items', $relativePath);

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="download.zip"');
        header('Content-Length: ' . filesize($zipFile));
        readfile($zipFile);
        unlink($zipFile);
        exit;
    } else {
        set_message('error', 'Failed to create zip file.');
        header('Location: ?path=' . urlencode($relativePath));
        exit;
    }
}

function add_folder_to_zip($zip, $folder, $relativePath)
{
    // Ensure we're working with absolute paths
    $folder = get_valid_path($folder);

    if (is_dir($folder)) {
        $files = scandir($folder);
        foreach ($files as $file) {
            if ($file != "." && $file != "..") {
                $filePath = $folder . DIRECTORY_SEPARATOR . $file;
                $newRelativePath = $relativePath . DIRECTORY_SEPARATOR . $file;
                if (is_dir($filePath)) {
                    add_folder_to_zip($zip, $filePath, $newRelativePath);
                } else {
                    $zip->addFile($filePath, $newRelativePath);
                }
            }
        }
    }
}

// Save edited text file
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_text_file'])) {
    $filePath = get_valid_path($_POST['file_path']);
    $content = $_POST['file_content'];

    // create_file_version($filePath);

    if (file_put_contents($filePath, $content) !== false) {
        log_activity('edit', basename($filePath), dirname($filePath));
        set_message('success', 'File saved successfully.');
    } else {
        set_message('error', 'Failed to save file.');
    }
    header('Location: ?path=' . urlencode($relativePath));
    exit;
}

// Share file
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['share_file'])) {
    $filePath = get_valid_path($_POST['file_path']);
    $expiryHours = intval($_POST['expiry_hours']);
    $shareId = bin2hex(random_bytes(8));

    $expiryTime = time() + ($expiryHours * 3600);

    $_SESSION['shared_files'][$shareId] = [
        'path' => $filePath,
        'expiry' => $expiryTime,
        'created' => time()
    ];

    $shareUrl = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    $shareUrl = preg_replace('/\?.*/', '', $shareUrl) . "?share=$shareId";

    log_activity('share', basename($filePath), $relativePath);
    set_message('success', "File shared. Link: $shareUrl");
    header('Location: ?path=' . urlencode($relativePath));
    exit;
}

// Access shared file
if (isset($_GET['share'])) {
    $shareId = $_GET['share'];
    if (isset($_SESSION['shared_files'][$shareId])) {
        $shareData = $_SESSION['shared_files'][$shareId];
        if ($shareData['expiry'] > time()) {
            $filePath = $shareData['path'];
            if (is_file($filePath)) {
                header('Content-Type: ' . mime_content_type($filePath));
                header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
                header('Content-Length: ' . filesize($filePath));
                readfile($filePath);
                exit;
            }
        } else {
            // Expired share, remove it
            unset($_SESSION['shared_files'][$shareId]);
        }
    }
    set_message('error', 'Invalid or expired share link.');
    header('Location: ?path=');
    exit;
}

// Tag file
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tag_file'])) {
    $filePath = get_valid_path($_POST['file_path']);
    $tags = explode(',', $_POST['tags']);
    $tags = array_map('trim', $tags);
    $tags = array_filter($tags);

    $_SESSION['file_tags'][$filePath] = $tags;
    log_activity('tag', basename($filePath), $relativePath);
    set_message('success', 'Tags saved successfully.');
    header('Location: ?path=' . urlencode($relativePath));
    exit;
}

function formatBytes(int $bytes, int $precision = 2): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++)
        $bytes /= 1024;
    return round($bytes, $precision) . ' ' . $units[$i];
}

function getFileIcon(string $filename): array
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    $fileTypes = [
        // Code files - Blue
        'php' => ['icon' => 'fa-brands fa-php', 'color' => '#4F5D95', 'bg' => 'rgba(79, 93, 149, 0.1)'],
        'html' => ['icon' => 'fa-brands fa-html5', 'color' => '#E34C26', 'bg' => 'rgba(227, 76, 38, 0.1)'],
        'htm' => ['icon' => 'fa-brands fa-html5', 'color' => '#E34C26', 'bg' => 'rgba(227, 76, 38, 0.1)'],
        'css' => ['icon' => 'fa-brands fa-css3-alt', 'color' => '#264DE4', 'bg' => 'rgba(38, 77, 228, 0.1)'],
        'scss' => ['icon' => 'fa-brands fa-sass', 'color' => '#CC6699', 'bg' => 'rgba(204, 102, 153, 0.1)'],
        'sass' => ['icon' => 'fa-brands fa-sass', 'color' => '#CC6699', 'bg' => 'rgba(204, 102, 153, 0.1)'],
        'less' => ['icon' => 'fa-brands fa-less', 'color' => '#1D365D', 'bg' => 'rgba(29, 54, 93, 0.1)'],
        'js' => ['icon' => 'fa-brands fa-js', 'color' => '#F7DF1E', 'bg' => 'rgba(247, 223, 30, 0.1)'],
        'jsx' => ['icon' => 'fa-brands fa-react', 'color' => '#61DAFB', 'bg' => 'rgba(97, 218, 251, 0.1)'],
        'ts' => ['icon' => 'fa-brands fa-js', 'color' => '#3178C6', 'bg' => 'rgba(49, 120, 198, 0.1)'],
        'tsx' => ['icon' => 'fa-brands fa-react', 'color' => '#61DAFB', 'bg' => 'rgba(97, 218, 251, 0.1)'],
        'vue' => ['icon' => 'fa-brands fa-vuejs', 'color' => '#4FC08D', 'bg' => 'rgba(79, 192, 141, 0.1)'],
        'json' => ['icon' => 'fa-file-code', 'color' => '#F7DF1E', 'bg' => 'rgba(247, 223, 30, 0.1)'],
        'xml' => ['icon' => 'fa-file-code', 'color' => '#F29B00', 'bg' => 'rgba(242, 155, 0, 0.1)'],
        'sql' => ['icon' => 'fa-database', 'color' => '#F29111', 'bg' => 'rgba(242, 145, 17, 0.1)'],
        'py' => ['icon' => 'fa-brands fa-python', 'color' => '#3776AB', 'bg' => 'rgba(55, 118, 171, 0.1)'],
        'java' => ['icon' => 'fa-brands fa-java', 'color' => '#007396', 'bg' => 'rgba(0, 115, 150, 0.1)'],
        'cpp' => ['icon' => 'fa-file-code', 'color' => '#00599C', 'bg' => 'rgba(0, 89, 156, 0.1)'],
        'c' => ['icon' => 'fa-file-code', 'color' => '#A8B9CC', 'bg' => 'rgba(168, 185, 204, 0.1)'],
        'h' => ['icon' => 'fa-file-code', 'color' => '#A8B9CC', 'bg' => 'rgba(168, 185, 204, 0.1)'],
        'cs' => ['icon' => 'fa-brands fa-microsoft', 'color' => '#512BD4', 'bg' => 'rgba(81, 43, 212, 0.1)'],
        'go' => ['icon' => 'fa-brands fa-golang', 'color' => '#00ADD8', 'bg' => 'rgba(0, 173, 216, 0.1)'],
        'rs' => ['icon' => 'fa-brands fa-rust', 'color' => '#DEA584', 'bg' => 'rgba(222, 165, 132, 0.1)'],
        'rb' => ['icon' => 'fa-brands fa-ruby', 'color' => '#CC342D', 'bg' => 'rgba(204, 52, 45, 0.1)'],
        'swift' => ['icon' => 'fa-brands fa-swift', 'color' => '#F05138', 'bg' => 'rgba(240, 81, 56, 0.1)'],
        'kt' => ['icon' => 'fa-brands fa-android', 'color' => '#7F52FF', 'bg' => 'rgba(127, 82, 255, 0.1)'],
        'dart' => ['icon' => 'fa-brands fa-flutter', 'color' => '#0175C2', 'bg' => 'rgba(1, 117, 194, 0.1)'],

        // Documents - Orange/Red
        'pdf' => ['icon' => 'fa-file-pdf', 'color' => '#F40F02', 'bg' => 'rgba(244, 15, 2, 0.1)'],
        'doc' => ['icon' => 'fa-file-word', 'color' => '#2B579A', 'bg' => 'rgba(43, 87, 154, 0.1)'],
        'docx' => ['icon' => 'fa-file-word', 'color' => '#2B579A', 'bg' => 'rgba(43, 87, 154, 0.1)'],
        'xls' => ['icon' => 'fa-file-excel', 'color' => '#217346', 'bg' => 'rgba(33, 115, 70, 0.1)'],
        'xlsx' => ['icon' => 'fa-file-excel', 'color' => '#217346', 'bg' => 'rgba(33, 115, 70, 0.1)'],
        'ppt' => ['icon' => 'fa-file-powerpoint', 'color' => '#D24726', 'bg' => 'rgba(210, 71, 38, 0.1)'],
        'pptx' => ['icon' => 'fa-file-powerpoint', 'color' => '#D24726', 'bg' => 'rgba(210, 71, 38, 0.1)'],
        'txt' => ['icon' => 'fa-file-alt', 'color' => '#6C757D', 'bg' => 'rgba(108, 117, 125, 0.1)'],
        'md' => ['icon' => 'fa-brands fa-markdown', 'color' => '#000000', 'bg' => 'rgba(0, 0, 0, 0.1)'],
        'rtf' => ['icon' => 'fa-file-alt', 'color' => '#6C757D', 'bg' => 'rgba(108, 117, 125, 0.1)'],

        // Images - Purple/Pink
        'jpg' => ['icon' => 'fa-file-image', 'color' => '#8E44AD', 'bg' => 'rgba(142, 68, 173, 0.1)'],
        'jpeg' => ['icon' => 'fa-file-image', 'color' => '#8E44AD', 'bg' => 'rgba(142, 68, 173, 0.1)'],
        'png' => ['icon' => 'fa-file-image', 'color' => '#9B59B6', 'bg' => 'rgba(155, 89, 182, 0.1)'],
        'gif' => ['icon' => 'fa-file-image', 'color' => '#E91E63', 'bg' => 'rgba(233, 30, 99, 0.1)'],
        'svg' => ['icon' => 'fa-file-image', 'color' => '#FF9800', 'bg' => 'rgba(255, 152, 0, 0.1)'],
        'bmp' => ['icon' => 'fa-file-image', 'color' => '#795548', 'bg' => 'rgba(121, 85, 72, 0.1)'],
        'webp' => ['icon' => 'fa-file-image', 'color' => '#9C27B0', 'bg' => 'rgba(156, 39, 176, 0.1)'],
        'ico' => ['icon' => 'fa-file-image', 'color' => '#607D8B', 'bg' => 'rgba(96, 125, 139, 0.1)'],
        'psd' => ['icon' => 'fa-file-image', 'color' => '#001E36', 'bg' => 'rgba(0, 30, 54, 0.1)'],
        'ai' => ['icon' => 'fa-file-image', 'color' => '#FF9A00', 'bg' => 'rgba(255, 154, 0, 0.1)'],

        // Archives - Brown/Yellow
        'zip' => ['icon' => 'fa-file-archive', 'color' => '#FBC02D', 'bg' => 'rgba(251, 192, 45, 0.1)'],
        'rar' => ['icon' => 'fa-file-archive', 'color' => '#D32F2F', 'bg' => 'rgba(211, 47, 47, 0.1)'],
        '7z' => ['icon' => 'fa-file-archive', 'color' => '#FBC02D', 'bg' => 'rgba(251, 192, 45, 0.1)'],
        'tar' => ['icon' => 'fa-file-archive', 'color' => '#795548', 'bg' => 'rgba(121, 85, 72, 0.1)'],
        'gz' => ['icon' => 'fa-file-archive', 'color' => '#795548', 'bg' => 'rgba(121, 85, 72, 0.1)'],
        'bz2' => ['icon' => 'fa-file-archive', 'color' => '#795548', 'bg' => 'rgba(121, 85, 72, 0.1)'],

        // Video - Red
        'mp4' => ['icon' => 'fa-file-video', 'color' => '#E74C3C', 'bg' => 'rgba(231, 76, 60, 0.1)'],
        'avi' => ['icon' => 'fa-file-video', 'color' => '#C0392B', 'bg' => 'rgba(192, 57, 43, 0.1)'],
        'mov' => ['icon' => 'fa-file-video', 'color' => '#E74C3C', 'bg' => 'rgba(231, 76, 60, 0.1)'],
        'wmv' => ['icon' => 'fa-file-video', 'color' => '#9B59B6', 'bg' => 'rgba(155, 89, 182, 0.1)'],
        'flv' => ['icon' => 'fa-file-video', 'color' => '#E74C3C', 'bg' => 'rgba(231, 76, 60, 0.1)'],
        'mkv' => ['icon' => 'fa-file-video', 'color' => '#16A085', 'bg' => 'rgba(22, 160, 133, 0.1)'],
        'webm' => ['icon' => 'fa-file-video', 'color' => '#3498DB', 'bg' => 'rgba(52, 152, 219, 0.1)'],

        // Audio - Green/Cyan
        'mp3' => ['icon' => 'fa-file-audio', 'color' => '#1ABC9C', 'bg' => 'rgba(26, 188, 156, 0.1)'],
        'wav' => ['icon' => 'fa-file-audio', 'color' => '#16A085', 'bg' => 'rgba(22, 160, 133, 0.1)'],
        'flac' => ['icon' => 'fa-file-audio', 'color' => '#27AE60', 'bg' => 'rgba(39, 174, 96, 0.1)'],
        'aac' => ['icon' => 'fa-file-audio', 'color' => '#F39C12', 'bg' => 'rgba(243, 156, 18, 0.1)'],
        'ogg' => ['icon' => 'fa-file-audio', 'color' => '#F39C12', 'bg' => 'rgba(243, 156, 18, 0.1)'],
        'wma' => ['icon' => 'fa-file-audio', 'color' => '#8E44AD', 'bg' => 'rgba(142, 68, 173, 0.1)'],
        'm4a' => ['icon' => 'fa-file-audio', 'color' => '#3498DB', 'bg' => 'rgba(52, 152, 219, 0.1)'],

        // Config/Settings - Gray
        'ini' => ['icon' => 'fa-cogs', 'color' => '#7F8C8D', 'bg' => 'rgba(127, 140, 141, 0.1)'],
        'conf' => ['icon' => 'fa-cogs', 'color' => '#7F8C8D', 'bg' => 'rgba(127, 140, 141, 0.1)'],
        'config' => ['icon' => 'fa-cogs', 'color' => '#7F8C8D', 'bg' => 'rgba(127, 140, 141, 0.1)'],
        'env' => ['icon' => 'fa-shield-alt', 'color' => '#F1C40F', 'bg' => 'rgba(241, 196, 15, 0.1)'],
        'yaml' => ['icon' => 'fa-file-code', 'color' => '#CB171E', 'bg' => 'rgba(203, 23, 30, 0.1)'],
        'yml' => ['icon' => 'fa-file-code', 'color' => '#CB171E', 'bg' => 'rgba(203, 23, 30, 0.1)'],
        'toml' => ['icon' => 'fa-file-code', 'color' => '#9C4221', 'bg' => 'rgba(156, 66, 33, 0.1)'],
        'lock' => ['icon' => 'fa-lock', 'color' => '#F1C40F', 'bg' => 'rgba(241, 196, 15, 0.1)'],

        // Logs - Dark Gray
        'log' => ['icon' => 'fa-file-alt', 'color' => '#34495E', 'bg' => 'rgba(52, 73, 94, 0.1)'],

        // Git - Orange
        'gitignore' => ['icon' => 'fa-brands fa-git-alt', 'color' => '#F05032', 'bg' => 'rgba(240, 80, 50, 0.1)'],
        'gitattributes' => ['icon' => 'fa-brands fa-git-alt', 'color' => '#F05032', 'bg' => 'rgba(240, 80, 50, 0.1)'],

        // Fonts - Teal
        'ttf' => ['icon' => 'fa-font', 'color' => '#00897B', 'bg' => 'rgba(0, 137, 123, 0.1)'],
        'otf' => ['icon' => 'fa-font', 'color' => '#00897B', 'bg' => 'rgba(0, 137, 123, 0.1)'],
        'woff' => ['icon' => 'fa-font', 'color' => '#00897B', 'bg' => 'rgba(0, 137, 123, 0.1)'],
        'woff2' => ['icon' => 'fa-font', 'color' => '#00897B', 'bg' => 'rgba(0, 137, 123, 0.1)'],

        // Executables - Dark
        'exe' => ['icon' => 'fa-cog', 'color' => '#2C3E50', 'bg' => 'rgba(44, 62, 80, 0.1)'],
        'msi' => ['icon' => 'fa-cog', 'color' => '#2C3E50', 'bg' => 'rgba(44, 62, 80, 0.1)'],
        'dmg' => ['icon' => 'fa-apple', 'color' => '#555555', 'bg' => 'rgba(85, 85, 85, 0.1)'],
        'pkg' => ['icon' => 'fa-box', 'color' => '#8E44AD', 'bg' => 'rgba(142, 68, 173, 0.1)'],
        'deb' => ['icon' => 'fa-brands fa-ubuntu', 'color' => '#E95420', 'bg' => 'rgba(233, 84, 32, 0.1)'],
        'rpm' => ['icon' => 'fa-brands fa-redhat', 'color' => '#EE0000', 'bg' => 'rgba(238, 0, 0, 0.1)'],
        'sh' => ['icon' => 'fa-terminal', 'color' => '#4EAA25', 'bg' => 'rgba(78, 170, 37, 0.1)'],
        'bat' => ['icon' => 'fa-terminal', 'color' => '#2C3E50', 'bg' => 'rgba(44, 62, 80, 0.1)'],
        'cmd' => ['icon' => 'fa-terminal', 'color' => '#2C3E50', 'bg' => 'rgba(44, 62, 80, 0.1)'],
    ];

    return $fileTypes[$ext] ?? ['icon' => 'fa-file', 'color' => '#95A5A6', 'bg' => 'rgba(149, 165, 166, 0.1)'];
}

function buildBreadcrumbs(string $relativePath): string
{
    global $rootName;
    $html = '<li class="breadcrumb-item"><a href="?path=" style="color:white"><i class="fa fa-home"></i> ' . htmlspecialchars($rootName) . '</a></li>';
    $parts = $relativePath ? explode('/', $relativePath) : [];
    $built = '';
    foreach ($parts as $part) {
        if (!$part)
            continue;
        $built .= ($built ? '/' : '') . $part;
        $html .= '<li class="breadcrumb-item"><a href="?path=' . urlencode($built) . '" style="color:white">' . htmlspecialchars($part) . '</a></li>';
    }
    return $html;
}


$items = @scandir($currentDirAbsPath) ?: [];
$folders = $files = [];

foreach ($items as $item) {
    if ($item === '.' || $item === '..')
        continue;
    $fullPath = $currentDirAbsPath . DIRECTORY_SEPARATOR . $item;
    if (!is_readable($fullPath))
        continue;

    $data = [
        'name' => $item,
        'time' => date('M d, Y h:i A', filemtime($fullPath)),
        'rel' => trim($relativePath . '/' . $item, '/'),
        'path' => $fullPath,
        'tags' => $_SESSION['file_tags'][$fullPath] ?? []
    ];

    if (is_dir($fullPath)) {
        $folders[] = $data;
    } else {
        $data['size'] = filesize($fullPath);
        $data['ext'] = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        $files[] = $data;
    }
}

// Sort items
$sortFunction = function ($a, $b) use ($sortBy, $sortOrder) {
    $result = 0;
    switch ($sortBy) {
        case 'name':
            $result = strcasecmp($a['name'], $b['name']);
            break;
        case 'size':
            $result = ($a['size'] ?? 0) - ($b['size'] ?? 0);
            break;
        case 'date':
            $result = strtotime($a['time']) - strtotime($b['time']);
            break;
    }
    return $sortOrder === 'desc' ? -$result : $result;
};

usort($folders, $sortFunction);
usort($files, $sortFunction);

function build_directory_tree(string $basePath, string $relativePrefix = '', int $depth = 0, int $maxDepth = 6): array
{
    if ($depth > $maxDepth || !is_dir($basePath) || !is_readable($basePath)) {
        return [];
    }
    $result = [];
    $items = @scandir($basePath) ?: [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $fullPath = $basePath . DIRECTORY_SEPARATOR . $item;
        if (!is_dir($fullPath) || !is_readable($fullPath)) {
            continue;
        }
        $childRelative = trim($relativePrefix . '/' . $item, '/');
        $result[] = [
            'name' => $item,
            'path' => $childRelative,
            'type' => 'folder',
            'children' => build_directory_tree($fullPath, $childRelative, $depth + 1, $maxDepth)
        ];
    }

    usort($result, function ($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });
    return $result;
}

function get_files_and_includes($rootPath)
{
    $rootPath = get_valid_path($rootPath);
    if (!is_dir($rootPath)) {
        return [];
    }

    global $rootName;
    return [[
        'name' => $rootName,
        'path' => '',
        'type' => 'folder',
        'children' => build_directory_tree($rootPath)
    ]];
}




$directoryTree = get_files_and_includes(realpath($rootPath));
$storageUsage = get_storage_usage();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta name="csrf-token" content="<?= bin2hex(random_bytes(16)) ?>">
    <title>Skipper Code Editor</title>
    <?php include "includes/links.php"; ?>

    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css " rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css " rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.css " rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/theme/monokai.min.css " rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/brands.min.css" rel="stylesheet">

    <style>
        :root {
            --primary: #E95420;
            /* Ubuntu Orange */
            --primary-dark: #C34113;
            /* Darker Orange */
            --primary-gradient: linear-gradient(135deg,
                    #E95420 0%,
                    #772953 100%);

            --accent: #772953;
            /* Aubergine */
            --accent-dark: #5E2750;
            /* Dark Aubergine */

            --background: #F7F7F7;
            /* Light Ubuntu background */
            --surface: #FFFFFF;

            --text-dark: #2C2C2C;
            --text-muted: #6B6B6B;
            --text-light: #FFFFFF;

            --border-color: #E0E0E0;

            --card-shadow: 0 10px 30px rgba(233, 84, 32, 0.2);
            --hover-lift: translateY(-5px);
            --card-height: 200px;
        }

        body {
            background-color: gray !important;
        }

        .page-header {
            background: var(--primary-gradient);
            color: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--card-shadow);
            position: relative;
            overflow: hidden;
        }

        .page-header h4 {
            font-weight: 700;
            margin: 0;
            font-size: 1.5rem;
        }

        .storage-usage {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 8px;
            padding: 0.75rem;
            margin-top: 1rem;
        }

        .storage-bar {
            height: 8px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 4px;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .storage-used {
            height: 100%;
            background: white;
            border-radius: 4px;
        }

        .search-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 10px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        .search-input {
            border: 2px solid rgba(0, 0, 0, 0.05) !important;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            transition: all 0.3s;
            background: white;
        }

        .search-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(111, 217, 67, 0.1);
            outline: none;
        }

        .search-results-count {
            color: var(--primary);
            font-weight: 600;
            font-size: 0.95rem;
        }

        .grid-view {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 1.2rem;
            margin-top: 1rem;
        }

        .item-card {
            background: white;
            border-radius: 12px;
            padding: 1.2rem 0.8rem;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
            position: relative;
            height: var(--card-height);
            display: flex;
            flex-direction: column;
            align-items: center;
            /* ADD THIS */
            justify-content: flex-start;
            /* CHANGE THIS */
            border: 1px solid rgba(111, 217, 67, 0.1);
            overflow: hidden;
        }

        .item-card:hover {
            transform: var(--hover-lift);
            box-shadow: 0 12px 25px rgba(111, 217, 67, 0.15);
            border-color: var(--primary);
        }

        .item-card.selected {
            border-color: var(--primary);
            background-color: rgba(111, 217, 67, 0.05);
        }

        .item-checkbox {
            position: absolute;
            top: 10px;
            left: 10px;
            opacity: 0;
            transition: opacity 0.3s;
            z-index: 10;
        }

        .item-card:hover .item-checkbox,
        .item-card.selected .item-checkbox,
        .selection-mode .item-checkbox {
            opacity: 1;
        }


        /* File Type Colors and Icons */
        .item-icon {
            font-size: 3.2rem;
            margin-bottom: 0.6rem;
            transition: transform 0.3s;
            background: var(--file-bg, transparent);
            padding: 0.5rem;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 80px;
            height: 80px;
        }

        .item-card:hover .item-icon {
            transform: scale(1.1);
        }

        /* Folder specific */
        .folder-icon {
            color: #E95420 !important;
            background: rgba(233, 84, 32, 0.1) !important;
        }

        /* Code files */
        .code-icon {
            color: #3498DB;
            background: rgba(52, 152, 219, 0.1);
        }

        /* Image files */
        .image-icon {
            color: #9B59B6;
            background: rgba(155, 89, 182, 0.1);
        }

        /* Document files */
        .doc-icon {
            color: #2B579A;
            background: rgba(43, 87, 154, 0.1);
        }

        /* Archive files */
        .archive-icon {
            color: #FBC02D;
            background: rgba(251, 192, 45, 0.1);
        }

        /* Audio files */
        .audio-icon {
            color: #1ABC9C;
            background: rgba(26, 188, 156, 0.1);
        }

        /* Video files */
        .video-icon {
            color: #E74C3C;
            background: rgba(231, 76, 60, 0.1);
        }

        /* Config files */
        .config-icon {
            color: #7F8C8D;
            background: rgba(127, 140, 141, 0.1);
        }

        /* Default file */
        .default-icon {
            color: #95A5A6;
            background: rgba(149, 165, 166, 0.1);
        }

        /* Mobile adjustments */
        @media (max-width: 768px) {
            .item-icon {
                width: 60px;
                height: 60px;
                font-size: 2.5rem;
            }
        }

        @media (max-width: 576px) {
            .item-icon {
                width: 50px;
                height: 50px;
                font-size: 2rem;
            }
        }



        .item-name {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text-dark);
            margin: 0.4rem 0;
            line-height: 1.3;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            word-break: break-word;
            max-height: 2.6em;
        }

        .item-meta {
            color: #636e72;
            font-size: 0.8rem;
            margin-top: auto;
            padding-top: 0.5rem;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }

        .item-tags {
            margin-top: 0.3rem;
        }

        .tag {
            display: inline-block;
            background: rgba(111, 217, 67, 0.1);
            color: var(--primary);
            border-radius: 12px;
            padding: 0.1rem 0.5rem;
            font-size: 0.7rem;
            margin: 0.1rem;
        }

        .item-actions {
            position: absolute;
            top: 8px;
            right: 8px;
            opacity: 0;
            transition: opacity 0.3s, transform 0.3s;
            background: rgba(255, 255, 255, 0.95);
            padding: 6px;
            border-radius: 8px;
            backdrop-filter: blur(5px);
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.1);
            transform: translateY(-5px);
            z-index: 10;
        }

        .item-card:hover .item-actions,
        .selection-mode .item-actions {
            opacity: 1;
            transform: translateY(0);
        }

        .item-link {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 1;
        }

        .selection-mode .item-link {
            display: none;
        }

        .btn-primary-custom {
            background: var(--primary-gradient);
            border: none;
            border-radius: 8px;
            padding: 0.6rem 1.2rem;
            font-weight: 600;
            color: white;
            box-shadow: 0 4px 12px rgba(111, 217, 67, 0.3);
            transition: all 0.3s;
            font-size: 0.9rem;
        }

        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(111, 217, 67, 0.4);
            color: white;
        }

        .btn-clear-search {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
        }

        .btn-clear-search:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .no-results {
            grid-column: 1 / -1;
            text-align: center;
            padding: 3rem 1.5rem;
            color: #636e72;
            font-size: 1rem;
        }

        .breadcrumb {
            background: rgba(255, 255, 255, 0.15);
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-top: 1rem !important;
            font-size: 0.85rem;
        }

        .breadcrumb-item a {
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
        }

        .breadcrumb-item a:hover {
            color: white;
            text-decoration: underline;
        }

        .breadcrumb-item+.breadcrumb-item::before {
            color: rgba(255, 255, 255, 0.7);
        }

        .dropzone {
            border: 2px dashed var(--primary);
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            background: rgba(111, 217, 67, 0.05);
            transition: all 0.3s;
            margin-bottom: 1.5rem;
            display: none;
        }

        .dropzone.active {
            display: block;
            background: rgba(111, 217, 67, 0.1);
            border-color: var(--primary-dark);
        }

        .dropzone.dragover {
            background: rgba(111, 217, 67, 0.2);
            transform: scale(1.02);
        }

        .progress-container {
            margin-top: 1rem;
            display: none;
        }

        .progress {
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            background: var(--primary-gradient);
            width: 0%;
            transition: width 0.3s;
        }

        .sidebar-menu {
            position: fixed;
            right: -300px;
            top: 0;
            width: 300px;
            height: 100%;
            background: white;
            box-shadow: -5px 0 15px rgba(0, 0, 0, 0.1);
            z-index: 1050;
            transition: right 0.3s;
            overflow-y: auto;
        }

        .sidebar-menu.active {
            right: 0;
        }

        .sidebar-header {
            background: var(--primary-gradient);
            color: white;
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .sidebar-content {
            padding: 1rem;
        }

        .sidebar-section {
            margin-bottom: 1.5rem;
        }

        .sidebar-section h5 {
            font-size: 0.9rem;
            text-transform: uppercase;
            color: #636e72;
            margin-bottom: 0.75rem;
        }

        .recent-file {
            display: flex;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid #f1f2f6;
        }

        .recent-file-icon {
            font-size: 1.2rem;
            margin-right: 0.75rem;
            color: var(--primary);
            width: 1.5rem;
            text-align: center;
        }

        .recent-file-info {
            flex-grow: 1;
        }

        .recent-file-name {
            font-weight: 500;
            font-size: 0.9rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .recent-file-path {
            font-size: 0.75rem;
            color: #636e72;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .activity-log-item {
            padding: 0.5rem 0;
            border-bottom: 1px solid #f1f2f6;
            font-size: 0.85rem;
        }

        .activity-log-time {
            color: #636e72;
            font-size: 0.75rem;
        }

        .directory-tree {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 0.5rem;
        }

        .tree-item {
            padding: 0.25rem 0.5rem;
            cursor: pointer;
            border-radius: 4px;
            transition: background-color 0.2s;
        }

        .tree-item:hover {
            background-color: rgba(111, 217, 67, 0.1);
        }

        .tree-item.selected {
            background-color: rgba(111, 217, 67, 0.2);
            font-weight: 500;
        }

        .tree-children {
            padding-left: 1.5rem;
        }

        .tree-toggle {
            width: 1rem;
            display: inline-block;
        }

        /* Modal Styles */
        .modal-header {
            background: var(--primary-gradient);
            color: white;
            border-radius: 12px 12px 0 0;
        }

        .modal-content {
            border: none;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }

        .btn-close {
            filter: brightness(0) invert(1);
        }

        /* Text Editor Styles */
        .CodeMirror {
            height: 500px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
        }

        .editor-toolbar {
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            padding: 0.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .editor-info {
            font-size: 0.8rem;
            color: #6c757d;
        }

        /* Mobile Optimizations */
        @media (max-width: 768px) {
            :root {
                --card-height: 180px;
            }

            .grid-view {
                grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
                gap: 1rem;
            }

            .item-card {
                padding: 1rem 0.6rem;
                border-radius: 10px;
            }

            .item-icon {
                font-size: 2.8rem;
            }

            .item-name {
                font-size: 0.85rem;
            }

            .item-meta {
                font-size: 0.75rem;
            }

            .page-header {
                padding: 1rem;
                border-radius: 10px;
            }

            .page-header h4 {
                font-size: 1.25rem;
            }

            .search-section {
                padding: 1rem;
                border-radius: 8px;
            }

            .btn-primary-custom {
                padding: 0.5rem 1rem;
                font-size: 0.85rem;
            }

            .dash-content {
                padding: 1rem !important;
            }

            .page-header .row {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 0.75rem;
            }

            .page-header .col-auto {
                align-self: flex-end;
            }

            .sidebar-menu {
                width: 100%;
                right: -100%;
            }

            .CodeMirror {
                height: 400px;
            }
        }

        @media (max-width: 576px) {
            .grid-view {
                grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
                gap: 0.75rem;
            }

            .item-card {
                height: 160px;
                padding: 0.8rem 0.4rem;
            }

            .item-icon {
                font-size: 2.4rem;
                margin-bottom: 0.4rem;
            }

            .item-name {
                font-size: 0.8rem;
                -webkit-line-clamp: 2;
                max-height: 2.2em;
            }

            .search-section .row {
                flex-direction: column;
                gap: 0.75rem;
            }

            .search-section .col-md-8,
            .search-section .col-md-4 {
                width: 100%;
            }

            .action-buttons {
                text-align: center;
            }

            .action-buttons .btn {
                margin-bottom: 0.5rem;
                display: inline-block;
            }

            .CodeMirror {
                height: 350px;
            }
        }

        @media (max-width: 400px) {
            .grid-view {
                grid-template-columns: repeat(2, 1fr);
            }

            .item-card {
                height: 150px;
            }

            .item-icon {
                font-size: 2rem;
            }

            .breadcrumb {
                font-size: 0.75rem;
                padding: 0.5rem;
            }

            .CodeMirror {
                height: 300px;
            }
        }

        /* Touch device optimizations */
        @media (hover: none) and (pointer: coarse) {
            .item-actions {
                opacity: 1 !important;
                transform: translateY(0) !important;
                background: rgba(255, 255, 255, 0.85);
            }

            .item-card:hover {
                transform: none;
            }
        }

        /* High contrast mode support */
        @media (prefers-contrast: high) {
            .item-card {
                border: 2px solid var(--primary);
            }

            .item-actions {
                border: 1px solid #000;
            }
        }

        /* Reduced motion preference */
        @media (prefers-reduced-motion: reduce) {

            .item-card,
            .item-icon,
            .btn-primary-custom,
            .item-actions {
                transition: none !important;
                transform: none !important;
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
            <div class="page-header position-relative">
                <div class="row align-items-center">
                    <div class="col">
                        <h4><i class="fa fa-code me-2"></i>Skipper Code Editor</h4>
                        <ul class="breadcrumb">
                            <?= buildBreadcrumbs($relativePath) ?>
                        </ul>
                        <div class="storage-usage  small p-2 rounded mb-2" style="font-family: monospace;">
                            <strong>PHP File Path Information</strong>
                            <hr class="my-1">
                            <div><strong>Current File (__FILE__):</strong> <?= __FILE__ ?></div>
                            <div><strong>Current Directory (__DIR__):</strong> <?= __DIR__ ?></div>
                            <div><strong>Working Directory (realpath):</strong> <?= realpath('.') ?></div>
                            <div><strong>Root Path:</strong> <?= $rootPath ?></div>
                            <div><strong>Current Directory:</strong> <?= $currentDirAbsPath ?></div>
                        </div>
                        <div class="storage-usage">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Storage Usage</span>
                                <span><?= formatBytes($storageUsage['used']) ?> /
                                    <?= formatBytes($storageUsage['total']) ?> (<?= $storageUsage['percent'] ?>%)</span>
                            </div>
                            <div class="storage-bar">
                                <div class="storage-used" style="width: <?= $storageUsage['percent'] ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Drag and Drop Zone -->
            <div class="dropzone" id="dropzone">
                <i class="fa fa-cloud-upload-alt fa-3x mb-3 text-primary"></i>
                <h5>Drag & Drop Files Here</h5>
                <p class="text-muted">or click to browse</p>
                <input type="file" id="fileInput" multiple style="display: none;">
                <div class="progress-container" id="progressContainer">
                    <div class="progress">
                        <div class="progress-bar" id="progressBar"></div>
                    </div>
                    <div class="text-center mt-2" id="progressText">Uploading...</div>
                </div>
            </div>

            <!-- Search Section -->
            <div class="search-section">
                <form method="GET" class="row g-2 g-md-3 align-items-center">
                    <input type="hidden" name="path" value="<?= htmlspecialchars($relativePath) ?>">
                    <div class="col-12 col-md-8">
                        <div class="input-group">
                            <span class="input-group-text bg-transparent border-end-0">
                                <i class="fa fa-search text-muted"></i>
                            </span>
                            <input type="text" class="form-control search-input border-start-0" name="search"
                                value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Search files and folders..."
                                id="searchInput" autocomplete="off">
                            <?php if ($searchQuery): ?>
                                <button type="button" class="btn btn-clear-search" onclick="clearSearch()">
                                    <i class="fa fa-times"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="row g-2">
                            <div class="col-6">
                                <select class="form-select" name="sort" id="sortSelect">
                                    <option value="name" <?= $sortBy === 'name' ? 'selected' : '' ?>>Name</option>
                                    <option value="size" <?= $sortBy === 'size' ? 'selected' : '' ?>>Size</option>
                                    <option value="date" <?= $sortBy === 'date' ? 'selected' : '' ?>>Date</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <select class="form-select" name="order" id="orderSelect">
                                    <option value="asc" <?= $sortOrder === 'asc' ? 'selected' : '' ?>>Asc</option>
                                    <option value="desc" <?= $sortOrder === 'desc' ? 'selected' : '' ?>>Desc</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </form>

                <?php if ($searchQuery && count($searchResults) > 0): ?>
                    <div class="mt-3 text-center text-md-end">
                        <span class="search-results-count"><i class="fa fa-search me-1"></i><?= count($searchResults) ?>
                            results for "<?= htmlspecialchars($searchQuery) ?>"</span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="row mb-3 action-buttons">
                <div class="col-12 col-md-6">
                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#uploadModal">
                            <i class="fa fa-cloud-upload-alt me-1"></i>Upload
                        </button>
                        <button class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#newFileModal">
                            <i class="fa fa-file-circle-plus me-1"></i>New File
                        </button>
                        <button class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#newFolderModal">
                            <i class="fa fa-folder-plus me-1"></i>New Folder
                        </button>
                        <button class="btn btn-primary-custom" id="toggleDropzone">
                            <i class="fa fa-hand-pointer me-1"></i>Drag & Drop
                        </button>
                    </div>
                </div>
                <div class="col-12 col-md-6 text-end">
                    <div class="d-flex flex-wrap gap-2 justify-content-end">
                        <button class="btn btn-primary-custom" id="selectToggle">
                            <i class="fa fa-check-square me-1"></i>Select
                        </button>
                        <button class="btn btn-primary-custom" id="moveBtn" disabled data-bs-toggle="modal"
                            data-bs-target="#moveCopyModal">
                            <i class="fa fa-arrows-alt me-1"></i>Move
                        </button>
                        <button class="btn btn-primary-custom" id="copyBtn" disabled data-bs-toggle="modal"
                            data-bs-target="#moveCopyModal">
                            <i class="fa fa-copy me-1"></i>Copy
                        </button>
                        <button class="btn btn-primary-custom" id="downloadBtn" disabled>
                            <i class="fa fa-download me-1"></i>Download
                        </button>
                        <button class="btn btn-primary-custom" id="deleteBtn" disabled>
                            <i class="fa fa-trash me-1"></i>Delete
                        </button>
                        <button class="btn btn-primary-custom" id="sidebarToggle">
                            <i class="fa fa-bars me-1"></i>Menu
                        </button>
                    </div>
                </div>
            </div>

            <!-- Content Display -->
            <?php if ($searchQuery && count($searchResults) > 0): ?>
                <!-- Search Results -->
                <div id="gridView" class="grid-view">
                    <?php foreach ($searchResults as $item): ?>
                        <div class="item-card" data-name="<?= htmlspecialchars($item['name']) ?>"
                            data-path="<?= htmlspecialchars($item['rel']) ?>">
                            <div class="item-checkbox">
                                <input type="checkbox" class="form-check-input item-select"
                                    value="<?= htmlspecialchars($item['name']) ?>">
                            </div>
                            <div class="item-actions">
                                <?php if ($item['type'] === 'file'): ?>
                                    <a href="?view=<?= urlencode($item['name']) ?>&path=<?= urlencode($relativePath) ?>&search=<?= urlencode($searchQuery) ?>"
                                        class="text-info me-2" target="_blank" title="View"><i class="fa fa-eye fa-sm"></i></a>
                                <?php endif; ?>
                                <a href="?del=<?= urlencode($item['name']) ?>&path=<?= urlencode($relativePath) ?>&search=<?= urlencode($searchQuery) ?>"
                                    class="text-danger delete-btn" data-name="<?= htmlspecialchars($item['name']) ?>"
                                    title="Delete">
                                    <i class="fa fa-trash fa-sm"></i>
                                </a>
                            </div>
                            <?php if ($item['type'] === 'folder'): ?>
                                <a href="?path=<?= urlencode($item['rel']) ?>&search=<?= urlencode($searchQuery) ?>"
                                    class="item-link"></a>
                            <?php else: ?>
                                <a href="?view=<?= urlencode($item['name']) ?>&path=<?= urlencode($relativePath) ?>&search=<?= urlencode($searchQuery) ?>"
                                    target="_blank" class="item-link"></a>
                            <?php endif; ?>
                            <?php if ($item['type'] === 'folder'): ?>
                                <i class="fa fa-folder item-icon folder-icon"></i>
                            <?php else: ?>
                                <?php $fileInfo = getFileIcon($item['name']); ?>
                                <i class="fa <?= $fileInfo['icon'] ?> item-icon"
                                    style="color: <?= $fileInfo['color'] ?>; background: <?= $fileInfo['bg'] ?>"></i>
                            <?php endif; ?>
                            <div class="item-name" title="<?= htmlspecialchars($item['name']) ?>">
                                <?= htmlspecialchars($item['name']) ?>
                            </div>
                            <div class="item-meta">
                                <?= $item['type'] === 'folder' ? 'Folder' : formatBytes($item['size']) ?> <br>
                                <small><?= $item['time'] ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($searchQuery): ?>
                <!-- No Results -->
                <div class="no-results">
                    <i class="fa fa-search fa-3x mb-3 text-muted"></i>
                    <h5>No results found</h5>
                    <p class="text-muted">"<?= htmlspecialchars($searchQuery) ?>"</p>
                    <a href="?path=<?= urlencode($relativePath) ?>" class="btn btn-primary-custom mt-2">Browse All Files</a>
                </div>
            <?php else: ?>
                <!-- Normal Directory View -->
                <div id="gridView" class="grid-view">
                    <?php if ($relativePath !== ''): ?>
                        <div class="item-card parent-folder">
                            <a href="?path=<?= urlencode(dirname($relativePath) === '.' ? '' : dirname($relativePath)) ?>"
                                class="item-link"></a>
                            <i class="fa fa-level-up-alt item-icon folder-icon" style="font-size:3.5rem;"></i>
                            <div class="item-name">Go Up</div>
                            <div class="item-meta">Parent Folder</div>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($folders as $folder): ?>
                        <div class="item-card" data-name="<?= htmlspecialchars($folder['name']) ?>"
                            data-path="<?= htmlspecialchars($folder['rel']) ?>">
                            <div class="item-checkbox">
                                <input type="checkbox" class="form-check-input item-select"
                                    value="<?= htmlspecialchars($folder['name']) ?>">
                            </div>
                            <div class="item-actions">
                                <a href="#" class="text-info me-2 rename-btn"
                                    data-name="<?= htmlspecialchars($folder['name']) ?>" title="Rename"><i
                                        class="fa fa-edit fa-sm"></i></a>
                                <a href="?del=<?= urlencode($folder['name']) ?>&path=<?= urlencode($relativePath) ?>"
                                    class="text-danger delete-btn" data-name="<?= htmlspecialchars($folder['name']) ?>"
                                    title="Delete">
                                    <i class="fa fa-trash fa-sm"></i>
                                </a>
                            </div>
                            <a href="?path=<?= urlencode($folder['rel']) ?>" class="item-link"></a>
                            <i class="fa fa-folder item-icon folder-icon"></i>
                            <div class="item-name" title="<?= htmlspecialchars($folder['name']) ?>">
                                <?= htmlspecialchars($folder['name']) ?>
                            </div>
                            <div class="item-meta">Folder<br><small><?= $folder['time'] ?></small></div>
                        </div>
                    <?php endforeach; ?>

                    <?php foreach ($files as $file): ?>
                        <div class="item-card" data-name="<?= htmlspecialchars($file['name']) ?>"
                            data-path="<?= htmlspecialchars($file['rel']) ?>">
                            <div class="item-checkbox">
                                <input type="checkbox" class="form-check-input item-select"
                                    value="<?= htmlspecialchars($file['name']) ?>">
                            </div>
                            <div class="item-actions">
                                <a href="?view=<?= urlencode($file['name']) ?>&path=<?= urlencode($relativePath) ?>"
                                    class="text-info me-2" target="_blank" title="View"><i class="fa fa-eye fa-sm"></i></a>
                                <a href="#" class="text-info me-2 rename-btn" data-name="<?= htmlspecialchars($file['name']) ?>"
                                    title="Rename"><i class="fa fa-edit fa-sm"></i></a>
                                <a href="#" class="text-info me-2 share-btn" data-path="<?= htmlspecialchars($file['rel']) ?>"
                                    title="Share"><i class="fa fa-share fa-sm"></i></a>
                                <a href="#" class="text-info me-2 tag-btn" data-path="<?= htmlspecialchars($file['rel']) ?>"
                                    title="Tags"><i class="fa fa-tags fa-sm"></i></a>
                                <?php if (in_array($file['ext'], ['txt', 'log', 'md', 'json', 'xml', 'csv', 'html', 'css', 'js', 'php', 'py', 'java', 'cpp', 'c', 'h', 'sql', 'sh', 'bat', 'yml', 'yaml', 'ini', 'conf', 'config', 'env', 'gitignore', 'htaccess'])): ?>
                                    <a href="#" class="text-info me-2 edit-btn" data-path="<?= htmlspecialchars($file['rel']) ?>"
                                        title="Edit"><i class="fa fa-code fa-sm"></i></a>
                                <?php endif; ?>
                                <a href="?del=<?= urlencode($file['name']) ?>&path=<?= urlencode($relativePath) ?>"
                                    class="text-danger delete-btn" data-name="<?= htmlspecialchars($file['name']) ?>"
                                    title="Delete">
                                    <i class="fa fa-trash fa-sm"></i>
                                </a>
                            </div>
                            <a href="?view=<?= urlencode($file['name']) ?>&path=<?= urlencode($relativePath) ?>" target="_blank"
                                class="item-link"></a>
                            <?php $fileInfo = getFileIcon($file['name']); ?>
                            <i class="fa <?= $fileInfo['icon'] ?> item-icon"
                                style="color: <?= $fileInfo['color'] ?>; background: <?= $fileInfo['bg'] ?>"></i>
                            <div class="item-name" title="<?= htmlspecialchars($file['name']) ?>">
                                <?= htmlspecialchars($file['name']) ?>
                            </div>
                            <div class="item-meta">
                                <?= formatBytes($file['size']) ?><br>
                                <small><?= $file['time'] ?></small>
                            </div>
                            <?php if (!empty($file['tags'])): ?>
                                <div class="item-tags">
                                    <?php foreach ($file['tags'] as $tag): ?>
                                        <span class="tag"><?= htmlspecialchars($tag) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Sidebar Menu -->
    <div class="sidebar-menu" id="sidebarMenu">
        <div class="sidebar-header">
            <h5 class="mb-0">Code Editor Menu</h5>
            <button class="btn btn-sm btn-light" id="closeSidebar">
                <i class="fa fa-times"></i>
            </button>
        </div>
        <div class="sidebar-content">
            <div class="sidebar-section">
                <h5>Recent Files</h5>
                <?php if (empty($_SESSION['recent_files'])): ?>
                    <p class="text-muted">No recent files</p>
                <?php else: ?>
                    <?php foreach (array_slice($_SESSION['recent_files'], 0, 5) as $filePath): ?>
                        <div class="recent-file">
                            <div class="recent-file-icon">
                                <?php $fileInfo = getFileIcon(basename($filePath)); ?>
                                <i class="fa <?= $fileInfo['icon'] ?>" style="color: <?= $fileInfo['color'] ?>"></i>
                            </div>
                            <div class="recent-file-info">
                                <div class="recent-file-name"><?= htmlspecialchars(basename($filePath)) ?></div>
                                <div class="recent-file-path">
                                    <?= htmlspecialchars(to_forward_slashes(str_replace(realpath($rootPath), '', $filePath))) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="sidebar-section">
                <h5>Activity Log</h5>
                <?php if (empty($_SESSION['activity_log'])): ?>
                    <p class="text-muted">No recent activity</p>
                <?php else: ?>
                    <?php foreach (array_slice(array_reverse($_SESSION['activity_log']), 0, 10) as $logEntry): ?>
                        <div class="activity-log-item">
                            <div><?= htmlspecialchars($logEntry['action']) ?>: <?= htmlspecialchars($logEntry['item']) ?></div>
                            <div class="activity-log-time"><?= $logEntry['timestamp'] ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="sidebar-section">
                <h5>Storage Usage</h5>
                <div class="mb-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Used</span>
                        <span><?= $storageUsage['percent'] ?>%</span>
                    </div>
                    <div class="progress mt-1">
                        <div class="progress-bar"
                            style="width: <?= $storageUsage['percent'] ?>%; background: var(--primary-gradient)"></div>
                    </div>
                </div>
                <div class="text-muted small">
                    <?= formatBytes($storageUsage['used']) ?> of <?= formatBytes($storageUsage['total']) ?>
                </div>
            </div>
        </div>
    </div>

    <?php include "includes/footer.php"; ?>
    <?php include "includes/scripts.php"; ?>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11 "></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.js "></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/javascript/javascript.min.js "></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/php/php.min.js "></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/python/python.min.js "></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/xml/xml.min.js "></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/css/css.min.js "></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/htmlmixed/htmlmixed.min.js "></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/sql/sql.min.js "></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/yaml/yaml.min.js "></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/shell/shell.min.js "></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/clike/clike.min.js "></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/edit/closebrackets.min.js "></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/edit/matchbrackets.min.js "></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/comment/comment.min.js "></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/fold/foldcode.min.js "></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/fold/foldgutter.min.js "></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/fold/brace-fold.min.js "></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/fold/xml-fold.min.js "></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/fold/comment-fold.min.js "></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/fold/markdown-fold.min.js "></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/fold/indent-fold.min.js "></script>
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/addon/fold/foldgutter.min.css">

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Sort change handlers
            const sortSelect = document.getElementById('sortSelect');
            const orderSelect = document.getElementById('orderSelect');

            sortSelect.addEventListener('change', function () {
                window.location.href = updateUrlParameter('sort', this.value);
            });

            orderSelect.addEventListener('change', function () {
                window.location.href = updateUrlParameter('order', this.value);
            });

            // Helper function to update URL parameters
            function updateUrlParameter(param, value) {
                const url = new URL(window.location);
                url.searchParams.set(param, value);
                return url.toString();
            }

            // Real-time search
            const searchInput = document.getElementById('searchInput');
            let searchTimeout;
            searchInput.addEventListener('input', function () {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    if (this.value.trim()) {
                        window.location.href = '?path=<?= urlencode($relativePath) ?>&search=' + encodeURIComponent(this.value.trim());
                    } else {
                        window.location.href = '?path=<?= urlencode($relativePath) ?>';
                    }
                }, 500);
            });

            function clearSearch() {
                document.getElementById('searchInput').value = '';
                window.location.href = '?path=<?= urlencode($relativePath) ?>';
            }
            window.clearSearch = clearSearch;

            // Drag and Drop
            const dropzone = document.getElementById('dropzone');
            const fileInput = document.getElementById('fileInput');
            const toggleDropzone = document.getElementById('toggleDropzone');

            toggleDropzone.addEventListener('click', function () {
                dropzone.classList.toggle('active');
                if (dropzone.classList.contains('active')) {
                    this.innerHTML = '<i class="fa fa-times me-1"></i>Close';
                } else {
                    this.innerHTML = '<i class="fa fa-hand-pointer me-1"></i>Drag & Drop';
                }
            });

            dropzone.addEventListener('click', function () {
                fileInput.click();
            });

            dropzone.addEventListener('dragover', function (e) {
                e.preventDefault();
                this.classList.add('dragover');
            });

            dropzone.addEventListener('dragleave', function () {
                this.classList.remove('dragover');
            });

            dropzone.addEventListener('drop', function (e) {
                e.preventDefault();
                this.classList.remove('dragover');

                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    uploadFiles(files);
                }
            });

            fileInput.addEventListener('change', function () {
                if (this.files.length > 0) {
                    uploadFiles(this.files);
                }
            });

            function uploadFiles(files) {
                const progressContainer = document.getElementById('progressContainer');
                const progressBar = document.getElementById('progressBar');
                const progressText = document.getElementById('progressText');

                progressContainer.style.display = 'block';
                progressBar.style.width = '0%';
                progressText.textContent = 'Uploading...';

                const formData = new FormData();
                for (let i = 0; i < files.length; i++) {
                    formData.append('upload_file[]', files[i]);
                }
                formData.append('current_path', '<?= $relativePath ?>');

                const xhr = new XMLHttpRequest();

                xhr.upload.addEventListener('progress', function (e) {
                    if (e.lengthComputable) {
                        const percent = Math.round((e.loaded / e.total) * 100);
                        progressBar.style.width = percent + '%';
                        progressText.textContent = `Uploading... ${percent}%`;
                    }
                });

                xhr.addEventListener('load', function () {
                    if (xhr.status === 200) {
                        progressText.textContent = 'Upload complete!';
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        progressText.textContent = 'Upload failed!';
                    }
                });

                xhr.addEventListener('error', function () {
                    progressText.textContent = 'Upload error!';
                });

                xhr.open('POST', '?path=<?= urlencode($relativePath) ?>', true);
                xhr.send(formData);
            }

            // Selection functionality
            const selectToggle = document.getElementById('selectToggle');
            const itemSelects = document.querySelectorAll('.item-select');
            const moveBtn = document.getElementById('moveBtn');
            const copyBtn = document.getElementById('copyBtn');
            const downloadBtn = document.getElementById('downloadBtn');
            const deleteBtn = document.getElementById('deleteBtn');
            let selectionMode = false;

            selectToggle.addEventListener('click', function () {
                selectionMode = !selectionMode;

                if (selectionMode) {
                    this.innerHTML = '<i class="fa fa-times me-1"></i>Cancel';
                    this.classList.add('btn-danger');
                    this.classList.remove('btn-primary-custom');

                    // Add selection mode class to body
                    document.body.classList.add('selection-mode');
                } else {
                    this.innerHTML = '<i class="fa fa-check-square me-1"></i>Select';
                    this.classList.remove('btn-danger');
                    this.classList.add('btn-primary-custom');

                    // Remove selection mode class from body
                    document.body.classList.remove('selection-mode');

                    // Clear selection
                    document.querySelectorAll('.item-select').forEach(el => {
                        el.checked = false;
                    });
                    document.querySelectorAll('.item-card').forEach(el => {
                        el.classList.remove('selected');
                    });
                    updateActionButtons();
                }
            });

            // Item selection
            document.addEventListener('change', function (e) {
                if (e.target.classList.contains('item-select')) {
                    const itemCard = e.target.closest('.item-card');
                    if (e.target.checked) {
                        itemCard.classList.add('selected');
                    } else {
                        itemCard.classList.remove('selected');
                    }
                    updateActionButtons();
                }
            });

            function updateActionButtons() {
                const selectedItems = document.querySelectorAll('.item-select:checked');
                const hasSelection = selectedItems.length > 0;

                moveBtn.disabled = !hasSelection;
                copyBtn.disabled = !hasSelection;
                downloadBtn.disabled = !hasSelection;
                deleteBtn.disabled = !hasSelection;
            }

            // Move/Copy functionality
            moveBtn.addEventListener('click', function () {
                document.getElementById('moveCopyAction').value = 'move';
                document.getElementById('moveCopyModalLabel').textContent = 'Move Items';
                document.getElementById('moveCopySubmitBtn').textContent = 'Move';
                document.getElementById('targetPath').value = '.';
                updateSelectedItemsList();
            });

            copyBtn.addEventListener('click', function () {
                document.getElementById('moveCopyAction').value = 'copy';
                document.getElementById('moveCopyModalLabel').textContent = 'Copy Items';
                document.getElementById('moveCopySubmitBtn').textContent = 'Copy';
                document.getElementById('targetPath').value = '.';
                updateSelectedItemsList();
            });

            function updateSelectedItemsList() {
                const selectedItems = [];
                document.querySelectorAll('.item-select:checked').forEach(el => {
                    selectedItems.push(el.value);
                });

                const selectedItemsContainer = document.getElementById('selectedItems');
                selectedItemsContainer.innerHTML = selectedItems.map(item =>
                    `<div class="badge bg-secondary me-1 mb-1">${item}</div>`
                ).join('');

                // Update hidden inputs with selected items
                const moveCopyForm = document.getElementById('moveCopyForm');
                moveCopyForm.querySelectorAll('input[name="items[]"]').forEach(el => el.remove());
                selectedItems.forEach(item => {
                    const itemsInput = document.createElement('input');
                    itemsInput.type = 'hidden';
                    itemsInput.name = 'items[]';
                    itemsInput.value = item;
                    moveCopyForm.appendChild(itemsInput);
                });
            }

            // Download functionality
            downloadBtn.addEventListener('click', function () {
                const selectedItems = [];
                document.querySelectorAll('.item-select:checked').forEach(el => {
                    selectedItems.push(el.value);
                });

                if (selectedItems.length > 0) {
                    const url = '?download_zip=1&path=<?= urlencode($relativePath) ?>&items=' + encodeURIComponent(selectedItems.join(','));
                    window.location.href = url;
                }
            });

            // Delete multiple items
            deleteBtn.addEventListener('click', function () {
                const selectedItems = [];
                document.querySelectorAll('.item-select:checked').forEach(el => {
                    selectedItems.push(el.value);
                });

                if (selectedItems.length > 0) {
                    Swal.fire({
                        title: 'Delete ' + selectedItems.length + ' items?',
                        text: "This action cannot be undone!",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
                        confirmButtonText: 'Delete All',
                        cancelButtonText: 'Cancel',
                        reverseButtons: true
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Delete items one by one
                            let count = 0;
                            selectedItems.forEach(item => {
                                const url = '?del=' + encodeURIComponent(item) + '&path=<?= urlencode($relativePath) ?>';
                                fetch(url).then(() => {
                                    count++;
                                    if (count === selectedItems.length) {
                                        window.location.reload();
                                    }
                                });
                            });
                        }
                    });
                }
            });

            // Sidebar toggle
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebarMenu = document.getElementById('sidebarMenu');
            const closeSidebar = document.getElementById('closeSidebar');

            sidebarToggle.addEventListener('click', function () {
                sidebarMenu.classList.add('active');
            });

            closeSidebar.addEventListener('click', function () {
                sidebarMenu.classList.remove('active');
            });

            // Toast notifications
            <?php if (isset($_SESSION['message'])): ?>
                Swal.fire({
                    icon: '<?= $_SESSION['message']['type'] ?>',
                    title: '<?= ucfirst($_SESSION['message']['type']) ?>',
                    text: '<?= addslashes($_SESSION['message']['text']) ?>',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 4000,
                    timerProgressBar: true,
                });
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>

            // Delete confirmation
            document.querySelectorAll('.delete-btn').forEach(btn => {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const url = this.href;
                    const name = this.dataset.name;

                    Swal.fire({
                        title: 'Delete "' + name + '"?',
                        text: "This action cannot be undone!",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',
                        confirmButtonText: 'Delete',
                        cancelButtonText: 'Cancel',
                        reverseButtons: true
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = url;
                        }
                    });
                });
            });

            // Rename functionality
            document.querySelectorAll('.rename-btn').forEach(btn => {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const oldName = this.dataset.name;

                    Swal.fire({
                        title: 'Rename Item',
                        input: 'text',
                        inputLabel: 'New name',
                        inputValue: oldName,
                        showCancelButton: true,
                        confirmButtonText: 'Rename',
                        cancelButtonText: 'Cancel',
                        inputValidator: (value) => {
                            if (!value) {
                                return 'Name is required';
                            }
                        }
                    }).then((result) => {
                        if (result.isConfirmed && result.value !== oldName) {
                            const form = document.createElement('form');
                            form.method = 'POST';
                            form.action = '?path=<?= urlencode($relativePath) ?>';

                            const renameInput = document.createElement('input');
                            renameInput.type = 'hidden';
                            renameInput.name = 'rename_item';
                            renameInput.value = oldName;

                            const newNameInput = document.createElement('input');
                            newNameInput.type = 'hidden';
                            newNameInput.name = 'new_name';
                            newNameInput.value = result.value;

                            form.appendChild(renameInput);
                            form.appendChild(newNameInput);
                            document.body.appendChild(form);
                            form.submit();
                        }
                    });
                });
            });

            // Share functionality
            document.querySelectorAll('.share-btn').forEach(btn => {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const filePath = this.dataset.path;

                    Swal.fire({
                        title: 'Share File',
                        html: `
                            <form id="shareForm">
                                <div class="mb-3">
                                    <label for="expiryHours" class="form-label">Link expires in (hours)</label>
                                    <input type="number" class="form-control" id="expiryHours" value="24" min="1" max="168">
                                </div>
                            </form>
                        `,
                        showCancelButton: true,
                        confirmButtonText: 'Create Share Link',
                        cancelButtonText: 'Cancel',
                        preConfirm: () => {
                            const expiryHours = document.getElementById('expiryHours').value;
                            if (!expiryHours || expiryHours < 1) {
                                Swal.showValidationMessage('Please enter a valid expiry time');
                                return false;
                            }
                            return { expiryHours };
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            const form = document.createElement('form');
                            form.method = 'POST';
                            form.action = '?path=<?= urlencode($relativePath) ?>';

                            const shareInput = document.createElement('input');
                            shareInput.type = 'hidden';
                            shareInput.name = 'share_file';
                            shareInput.value = '1';

                            const filePathInput = document.createElement('input');
                            filePathInput.type = 'hidden';
                            filePathInput.name = 'file_path';
                            filePathInput.value = filePath;

                            const expiryInput = document.createElement('input');
                            expiryInput.type = 'hidden';
                            expiryInput.name = 'expiry_hours';
                            expiryInput.value = result.value.expiryHours;

                            form.appendChild(shareInput);
                            form.appendChild(filePathInput);
                            form.appendChild(expiryInput);
                            document.body.appendChild(form);
                            form.submit();
                        }
                    });
                });
            });

            // Tag functionality
            document.querySelectorAll('.tag-btn').forEach(btn => {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const filePath = this.dataset.path;

                    // Get existing tags
                    let existingTags = [];
                    <?php foreach ($_SESSION['file_tags'] as $path => $tags): ?>
                        if (filePath === '<?= $path ?>') {
                            existingTags = <?= json_encode($tags) ?>;
                        }
                    <?php endforeach; ?>

                    Swal.fire({
                        title: 'File Tags',
                        html: `
                            <form id="tagForm">
                                <div class="mb-3">
                                    <label for="tags" class="form-label">Tags (comma separated)</label>
                                    <input type="text" class="form-control" id="tags" value="${existingTags.join(', ')}">
                                    <div class="form-text">Enter tags separated by commas</div>
                                </div>
                            </form>
                        `,
                        showCancelButton: true,
                        confirmButtonText: 'Save Tags',
                        cancelButtonText: 'Cancel',
                        preConfirm: () => {
                            const tags = document.getElementById('tags').value;
                            return { tags };
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            const form = document.createElement('form');
                            form.method = 'POST';
                            form.action = '?path=<?= urlencode($relativePath) ?>';

                            const tagInput = document.createElement('input');
                            tagInput.type = 'hidden';
                            tagInput.name = 'tag_file';
                            tagInput.value = '1';

                            const filePathInput = document.createElement('input');
                            filePathInput.type = 'hidden';
                            filePathInput.name = 'file_path';
                            filePathInput.value = filePath;

                            const tagsInput = document.createElement('input');
                            tagsInput.type = 'hidden';
                            tagsInput.name = 'tags';
                            tagsInput.value = result.value.tags;

                            form.appendChild(tagInput);
                            form.appendChild(filePathInput);
                            form.appendChild(tagsInput);
                            document.body.appendChild(form);
                            form.submit();
                        }
                    });
                });
            });

            // Edit functionality
            document.querySelectorAll('.edit-btn').forEach(btn => {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const filePath = this.dataset.path;

                    // Show loading
                    Swal.fire({
                        title: 'Loading file...',
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Normalize the file path for the fetch request
                    const normalizedPath = filePath.replace(/\\/g, '/');

                    fetch('?view=' + encodeURIComponent(normalizedPath) + '&_t=' + Date.now())
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Network response was not ok');
                            }
                            return response.text();
                        })
                        .then(content => {
                            Swal.close();

                            const editorModal = document.createElement('div');
                            editorModal.className = 'modal fade';
                            editorModal.id = 'editorModal';
                            editorModal.setAttribute('tabindex', '-1');
                            editorModal.innerHTML = `
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Edit File: ${filePath.split('\\').pop().split('/').pop()}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body p-0">
                                <div class="editor-toolbar">
                                    <div class="editor-info">
                                        <span id="editorPath">${filePath}</span>
                                        <span class="ms-3" id="editorPosition">Line 1, Column 1</span>
                                    </div>
                                    <div>
                                        <button type="button" class="btn btn-sm btn-outline-secondary me-2" id="toggleWordWrap">
                                            <i class="fa fa-text-width"></i> Word Wrap
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary me-2" id="toggleLineNumbers">
                                            <i class="fa fa-list-ol"></i> Line Numbers
                                        </button>
                                    </div>
                                </div>
                                <form id="editorForm">
                                    <textarea id="fileContent" class="form-control" rows="20">${content.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</textarea>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-primary-custom" id="saveFileBtn">Save</button>
                            </div>
                        </div>
                    </div>
                `;

                            document.body.appendChild(editorModal);
                            const modal = new bootstrap.Modal(document.getElementById('editorModal'));
                            modal.show();

                            document.getElementById('editorModal').addEventListener('shown.bs.modal', function () {
                                editor.refresh();
                            }, { once: true });

                            const editor = CodeMirror.fromTextArea(document.getElementById('fileContent'), {
                                lineNumbers: true,
                                lineWrapping: false,
                                mode: getModeFromPath(filePath),
                                theme: 'monokai',
                                autoCloseBrackets: true,
                                matchBrackets: true,
                                foldGutter: true,
                                gutters: ["CodeMirror-linenumbers", "CodeMirror-foldgutter"]
                            });

                            // Update cursor position
                            editor.on('cursorActivity', function () {
                                const cursor = editor.getCursor();
                                document.getElementById('editorPosition').textContent =
                                    `Line ${cursor.line + 1}, Column ${cursor.ch + 1}`;
                            });

                            // Toggle word wrap
                            document.getElementById('toggleWordWrap').addEventListener('click', function () {
                                editor.setOption('lineWrapping', !editor.getOption('lineWrapping'));
                                this.classList.toggle('active');
                            });

                            // Toggle line numbers
                            document.getElementById('toggleLineNumbers').addEventListener('click', function () {
                                editor.setOption('lineNumbers', !editor.getOption('lineNumbers'));
                                this.classList.toggle('active');
                            });

                            // Keyboard shortcuts
                            editor.setOption("extraKeys", {
                                "Ctrl-S": function () {
                                    document.getElementById('saveFileBtn').click();
                                },
                                "Cmd-S": function () {
                                    document.getElementById('saveFileBtn').click();
                                },
                                "Ctrl-/": function () {
                                    editor.toggleComment();
                                },
                                "Cmd-/": function () {
                                    editor.toggleComment();
                                },
                                "F11": function () {
                                    editor.setOption("fullScreen", !editor.getOption("fullScreen"));
                                },
                                "Esc": function () {
                                    if (editor.getOption("fullScreen")) {
                                        editor.setOption("fullScreen", false);
                                    }
                                }
                            });

                            document.getElementById('saveFileBtn').addEventListener('click', function () {
                                const form = document.createElement('form');
                                form.method = 'POST';
                                form.action = '?path=<?= urlencode($relativePath) ?>';

                                const saveInput = document.createElement('input');
                                saveInput.type = 'hidden';
                                saveInput.name = 'save_text_file';
                                saveInput.value = '1';

                                const filePathInput = document.createElement('input');
                                filePathInput.type = 'hidden';
                                filePathInput.name = 'file_path';
                                filePathInput.value = filePath;

                                const contentInput = document.createElement('input');
                                contentInput.type = 'hidden';
                                contentInput.name = 'file_content';
                                contentInput.value = editor.getValue();

                                form.appendChild(saveInput);
                                form.appendChild(filePathInput);
                                form.appendChild(contentInput);
                                document.body.appendChild(form);
                                form.submit();
                            });

                            document.getElementById('editorModal').addEventListener('hidden.bs.modal', function () {
                                document.body.removeChild(editorModal);
                            });
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'Failed to load file content: ' + error.message
                            });
                        });
                });
            });

            function getModeFromPath(path) {
                const ext = path.split('.').pop().toLowerCase();
                const modeMap = {
                    'js': 'javascript',
                    'json': 'javascript',
                    'php': 'php',
                    'py': 'python',
                    'xml': 'xml',
                    'html': 'htmlmixed',
                    'htm': 'htmlmixed',
                    'css': 'css',
                    'sql': 'sql',
                    'yml': 'yaml',
                    'yaml': 'yaml',
                    'sh': 'shell',
                    'bash': 'shell',
                    'bat': 'shell',
                    'java': 'text/x-java',
                    'cpp': 'text/x-c++src',
                    'c': 'text/x-csrc',
                    'h': 'text/x-csrc'
                };
                return modeMap[ext] || 'text/plain';
            }

            function buildDirectoryTree(tree, container, level = 0) {
                tree.forEach(item => {
                    const children = Array.isArray(item.children) ? item.children : [];
                    const treeItem = document.createElement('div');
                    treeItem.className = 'tree-item';
                    treeItem.style.paddingLeft = (level * 20) + 'px';
                    treeItem.dataset.path = item.path;

                    const toggle = document.createElement('span');
                    toggle.className = 'tree-toggle';
                    toggle.innerHTML = children.length > 0 ? '<i class="fa fa-chevron-right"></i>' : '';

                    const icon = document.createElement('i');
                    icon.className = 'fa fa-folder me-2';

                    const name = document.createElement('span');
                    name.textContent = item.name;

                    treeItem.appendChild(toggle);
                    treeItem.appendChild(icon);
                    treeItem.appendChild(name);

                    container.appendChild(treeItem);

                    if (children.length > 0) {
                        const childrenContainer = document.createElement('div');
                        childrenContainer.className = 'tree-children d-none';
                        buildDirectoryTree(children, childrenContainer, level + 1);
                        container.appendChild(childrenContainer);

                        treeItem.addEventListener('click', function () {
                            childrenContainer.classList.toggle('d-none');
                            const icon = toggle.querySelector('i');
                            if (icon) {
                                icon.classList.toggle('fa-chevron-right');
                                icon.classList.toggle('fa-chevron-down');
                            }
                        });
                    }

                    treeItem.addEventListener('click', function (e) {
                        e.stopPropagation();
                        document.querySelectorAll('.tree-item').forEach(el => {
                            el.classList.remove('selected');
                        });
                        this.classList.add('selected');
                        document.getElementById('targetPath').value = this.dataset.path || '/';
                    });
                });
            }

            const directoryTreeContainer = document.getElementById('directoryTree');
            if (directoryTreeContainer) {
                const tree = <?= json_encode($directoryTree) ?>;
                buildDirectoryTree(tree, directoryTreeContainer);
            }

            if ('ontouchstart' in window) {
                document.querySelectorAll('.item-card').forEach(card => {
                    let tapTimer;

                    card.addEventListener('touchstart', function (e) {
                        tapTimer = setTimeout(() => {

                        }, 500);
                    }, { passive: true });

                    card.addEventListener('touchend', function (e) {
                        clearTimeout(tapTimer);
                    }, { passive: true });

                    card.addEventListener('touchmove', function (e) {
                        clearTimeout(tapTimer);
                    }, { passive: true });
                });
            }

            document.addEventListener('keydown', function (e) {
                // Ctrl+A to select all items
                if ((e.ctrlKey || e.metaKey) && e.key === 'a' && selectionMode) {
                    e.preventDefault();
                    document.querySelectorAll('.item-select').forEach(el => {
                        el.checked = true;
                        el.closest('.item-card').classList.add('selected');
                    });
                    updateActionButtons();
                }

                // Delete key to delete selected items
                if (e.key === 'Delete' && selectionMode) {
                    deleteBtn.click();
                }

                // Escape key to exit selection mode
                if (e.key === 'Escape' && selectionMode) {
                    selectToggle.click();
                }

                // Ctrl+F to focus search
                if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                    e.preventDefault();
                    searchInput.focus();
                }

                // Ctrl+U to toggle dropzone
                if ((e.ctrlKey || e.metaKey) && e.key === 'u') {
                    e.preventDefault();
                    toggleDropzone.click();
                }
            });
        });

        function handleResize() {
            const cards = document.querySelectorAll('.item-card');
            const isMobile = window.innerWidth < 768;

            cards.forEach(card => {
                if (isMobile) {
                    card.style.minHeight = '160px';
                } else {
                    card.style.minHeight = '';
                }
            });
        }

        window.addEventListener('resize', handleResize);
        handleResize();
    </script>

    <!-- Upload Modal -->
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <form action="" method="post" enctype="multipart/form-data">
                    <div class="modal-header text-white"
                        style="background: var(--primary-gradient); border-radius: 12px 12px 0 0;">
                        <h5 class="modal-title"><i class="fa fa-cloud-upload-alt me-2"></i>Upload File</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body pt-4">
                        <input type="file" name="upload_file[]" class="form-control form-control-lg" multiple required>
                        <small class="text-muted mt-2 d-block">Maximum file size depends on server settings.</small>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary-custom">Upload Now</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- New File Modal -->
    <div class="modal fade" id="newFileModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <form action="" method="post">
                    <div class="modal-header text-white"
                        style="background: var(--primary-gradient); border-radius: 12px 12px 0 0;">
                        <h5 class="modal-title"><i class="fa fa-file-circle-plus me-2"></i>Create New File</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body pt-4">
                        <input type="text" name="new_file_name" class="form-control form-control-lg"
                            placeholder="Enter file name (e.g. notes.txt)" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary-custom">Create File</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- New Folder Modal -->
    <div class="modal fade" id="newFolderModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <form action="" method="post">
                    <div class="modal-header text-white"
                        style="background: var(--primary-gradient); border-radius: 12px 12px 0 0;">
                        <h5 class="modal-title"><i class="fa fa-folder-plus me-2"></i>Create New Folder</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body pt-4">
                        <input type="text" name="new_folder_name" class="form-control form-control-lg"
                            placeholder="Enter folder name" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary-custom">Create Folder</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Move/Copy Modal -->
    <div class="modal fade" id="moveCopyModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <form action="" method="post" id="moveCopyForm">
                    <div class="modal-header text-white"
                        style="background: var(--primary-gradient); border-radius: 12px 12px 0 0;">
                        <h5 class="modal-title" id="moveCopyModalLabel"><i class="fa fa-arrows-alt me-2"></i>Move Items
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body pt-4">
                        <input type="hidden" name="move_copy" value="1">
                        <input type="hidden" name="action" id="moveCopyAction" value="move">
                        <input type="hidden" name="current_path" value="<?= $relativePath ?>">

                        <div class="mb-3">
                            <label for="targetPath" class="form-label">Target Path</label>
                            <input type="text" class="form-control" id="targetPath" name="target_path"
                                placeholder="Use folder name, sub/folder, ., or .." required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Select Destination</label>
                            <div class="directory-tree" id="directoryTree">
                                <!-- Directory tree will be populated by JavaScript -->
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Selected Items</label>
                            <div id="selectedItems" class="border rounded p-2"
                                style="max-height: 150px; overflow-y: auto;">
                                <!-- Selected items will be populated by JavaScript -->
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary-custom" id="moveCopySubmitBtn">Move</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>

</html>

