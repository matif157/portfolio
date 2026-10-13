<?php
/**
 * GRAVITY CRM - Index / Entry Point
 * Phase 1: Foundation
 * 
 * Yahan se user ko dashboard ya login page par redirect kiya jata hai.
 */

require_once __DIR__ . '/functions.php';

if (current_user()) {
    redirect('dashboard.php');
} else {
    redirect('login.php');
}