<?php
// =============================================
// FILE: test_log.php
// PURPOSE: Activity Log force test
// =============================================

require_once 'log_in.php';

echo "<h2>Activity Log Test</h2>";

// 1. Function exist karta hai?
if (function_exists('log_activity')) {
    echo "<p style='color:green;'>✅ log_activity() function mil gaya</p>";
} else {
    echo "<p style='color:red;'>❌ log_activity() function NAHI mila. functions.php include nahi hua.</p>";
    exit();
}

// 2. Table exist karti hai?
try {
    $conn->query("SELECT 1 FROM activity_logs LIMIT 1");
    echo "<p style='color:green;'>✅ activity_logs table maujood hai</p>";
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ activity_logs table NAHI mili: " . $e->getMessage() . "</p>";
    exit();
}

// 3. Force ek log insert karo
log_activity($conn, 'Test', 'Manual Test', 'TEST-001', null, ['message' => 'This is a test log']);

echo "<p style='color:green;'>✅ Test log insert attempt kiya</p>";

// 4. Check karo ke insert hua ya nahi
$stmt = $conn->query("SELECT * FROM activity_logs ORDER BY id DESC LIMIT 5");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Last 5 Logs:</h3>";
echo "<pre>";
print_r($logs);
echo "</pre>";

if (count($logs) > 0) {
    echo "<p style='color:green; font-size:18px;'>🎉 Activity Log WORKING hai!</p>";
} else {
    echo "<p style='color:red; font-size:18px;'>❌ Abhi bhi koi log nahi aaya. Database permission ya connection issue ho sakta hai.</p>";
}
?>