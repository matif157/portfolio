<?php
require __DIR__ . '/../bootstrap.php';
use App\Core\Database;
use Throwable;

header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = Database::pdo();
    echo "CRM database diagnostic\n=======================\n";
    echo "PHP: " . PHP_VERSION . "\n";
    echo "Database: " . ($pdo->query('SELECT DATABASE()')->fetchColumn() ?: 'none') . "\n";
    echo "PDO drivers: " . implode(', ', $pdo->getAvailableDrivers()) . "\n\n";

    foreach (['roles','users','clients','communications_preferences','services','projects','invoices','payments','activities'] as $table) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        echo str_pad($table, 32) . ($stmt->fetchColumn() ? 'OK' : 'MISSING') . "\n";
    }

    $requiredRoles = ['id','role_name'];
    $requiredClients = ['id','tenant_id','client_no','first_name','middle_name','last_name','full_name','client_type','email','phone','status','notes','created_by','updated_by'];
    foreach ([['roles',$requiredRoles],['clients',$requiredClients]] as [$table,$cols]) {
        $actual=[];
        foreach ($pdo->query("DESCRIBE `$table`") as $row) $actual[]=$row['Field'];
        foreach ($cols as $col) echo "$table.$col: " . (in_array($col,$actual,true) ? 'OK' : 'MISSING') . "\n";
    }

    echo "\nResult: diagnostic completed.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "DIAGNOSTIC ERROR: " . $e->getMessage() . "\n";
}
