<?php
/**
 * SPS Professional Services CRM
 * Entry Point / Router
 */
require_once __DIR__ . '/auth.php';

if (is_logged_in()) {
    redirect('dashboard.php');
} else {
    redirect('login.php');
}
