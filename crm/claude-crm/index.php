<?php
// ============================================================
// SPS CRM - Entry Point
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

redirect(!empty($_SESSION['user_id']) ? 'dashboard.php' : 'login.php');
