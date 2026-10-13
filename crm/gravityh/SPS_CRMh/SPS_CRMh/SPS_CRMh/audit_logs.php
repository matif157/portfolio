<?php
/**
 * SPS Professional Services CRM
 * Sensitive Access Audit Logs Router
 */
require_once __DIR__ . '/auth.php';
redirect('activity_logs.php?stage=stage2_sensitive');
