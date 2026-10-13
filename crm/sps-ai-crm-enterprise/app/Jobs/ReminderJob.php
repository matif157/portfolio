<?php
namespace App\Jobs;
use App\Support\Database;
final class ReminderJob{public function handle():int{return (int)Database::pdo()->query("SELECT COUNT(*) FROM reminders WHERE start_at BETWEEN NOW() AND DATE_ADD(NOW(),INTERVAL 1 DAY)")->fetchColumn();}}
