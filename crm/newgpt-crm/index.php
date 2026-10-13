<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
if (logged_in()) {
    redirect('dashboard.php');
}
redirect('login.php');
?>
