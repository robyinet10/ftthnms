<?php
// Script untuk memperbaiki status monitoring semua item
require_once 'config/database.php';
require_once 'api/monitoring.php';

// Simulate admin session
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

echo "<h2>🔧 Fix Monitoring Status</h2>\n";
echo "<pre>\n";

$database = new Database();
$db = $database->getConnection();

// Get all items with IP addresses
$query = "SELECT id, name, ip_address, monitoring_status FROM ftth_items WHERE ip_address IS NOT NULL AND ip_address != ''";
$stmt = $db->prepare($query);
$stmt->execute();
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($items)) {
    echo "❌ No items with IP addresses found in database\n";
    echo "</pre>";
    exit;
}

echo "🔍 Found " . count($items) . " items with IP addresses\n";
echo str_repeat("=", 60) . "\n\n";

foreach ($items as $item) {
    echo "📡 Processing: {$item['name']} ({$item['ip_address']})\n";
    echo "   Current Status: {$item['monitoring_status']}\n";
    
    // Ping the item
    $start_time = microtime(true);
    $result = pingItem($db, $item['id']);
    $execution_time = round((microtime(true) - $start_time) * 1000, 1);
    
    echo "   Ping Result:\n";
    echo "     Status: {$result['status']}\n";
    echo "     Response Time: " . ($result['response_time_ms'] ?? 'NULL') . "ms\n";
    echo "     Execution Time: {$execution_time}ms\n";
    
    if ($result['error_message']) {
        echo "     Error: " . substr($result['error_message'], 0, 100) . "...\n";
    }
    
    // Check if status changed
    if ($item['monitoring_status'] !== $result['status']) {
        echo "   🔄 Status CHANGED: {$item['monitoring_status']} → {$result['status']}\n";
    } else {
        echo "   ✅ Status UNCHANGED: {$result['status']}\n";
    }
    
    // Verify by manual ping command
    $os = strtoupper(substr(PHP_OS, 0, 3));
    if ($os === 'WIN') {
        $manual_command = "ping -n 1 -w 3000 {$item['ip_address']}";
    } else {
        $manual_command = "ping -c 1 -W 3 {$item['ip_address']}";
    }
    
    $manual_output = [];
    $manual_return = 0;
    exec($manual_command, $manual_output, $manual_return);
    
    echo "   🔨 Manual Verification:\n";
    echo "     Command: $manual_command\n";
    echo "     Return Code: $manual_return\n";
    echo "     Output: " . (isset($manual_output[0]) ? $manual_output[0] : 'No output') . "\n";
    
    // Compare manual vs our result
    $manual_success = ($manual_return === 0 && strpos(implode("\n", $manual_output), 'Reply from') !== false);
    $our_success = ($result['status'] === 'online');
    
    if ($manual_success === $our_success) {
        echo "   ✅ MATCH: Manual and our result agree\n";
    } else {
        echo "   ❌ MISMATCH: Manual=" . ($manual_success ? 'SUCCESS' : 'FAIL') . 
             ", Our=" . ($our_success ? 'SUCCESS' : 'FAIL') . "\n";
    }
    
    echo "\n" . str_repeat("-", 60) . "\n\n";
}

echo "🏁 Summary:\n";
echo "- Check database for updated status\n";
echo "- Review error.log for detailed ping information\n";
echo "- Test in application to verify UI updates\n";

echo "\n✅ Fix completed at " . date('Y-m-d H:i:s') . "\n";
echo "</pre>";
?>
