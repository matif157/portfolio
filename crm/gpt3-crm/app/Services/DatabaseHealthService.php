<?php
namespace App\Services;
use App\Core\Database;
final class DatabaseHealthService { public function check(): bool { Database::pdo()->query('SELECT 1'); return true; } }
