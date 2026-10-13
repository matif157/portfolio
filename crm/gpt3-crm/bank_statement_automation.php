<?php
require __DIR__.'/bootstrap.php';
use App\Services\BookkeepingAutomationService;
$year=(int)($_GET['year']??date('Y'));
$count=(new BookkeepingAutomationService)->runMissingCommunication($year);
header('Content-Type:text/plain');echo "Bank statement automation processed: {$count} client(s)\n";
