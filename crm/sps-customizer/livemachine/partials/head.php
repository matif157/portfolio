<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../classes/Auth.php';
Auth::start();
$pageTitle = $pageTitle ?? 'Attendance';
?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf"     content="<?= htmlspecialchars(Auth::csrf(), ENT_QUOTES) ?>">
    <meta name="base-url" content="<?= htmlspecialchars(BASE_URL, ENT_QUOTES) ?>">
    <title><?= htmlspecialchars($pageTitle) ?> · Attendance</title>
    <script>try{var t=localStorage.getItem('lv-theme');if(t)document.documentElement.setAttribute('data-theme',t);}catch(e){}</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- DataTables + Buttons (CDN) -->
    <link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/3.2.0/css/buttons.dataTables.min.css">

    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?>">
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- jQuery + DataTables + Buttons (loaded before page scripts so app.js can init) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/3.2.0/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/3.2.0/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/3.2.0/js/buttons.print.min.js"></script>
</head>
<body>
