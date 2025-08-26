<?php
// Test script untuk API monitoring seperti yang digunakan aplikasi
session_start();

// Simulate admin session for testing
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['role'] = 'admin';

echo "<h2>🚀 Test Monitoring API</h2>\n";
echo "<pre>\n";

echo "Testing API endpoint: api/monitoring.php\n";
echo str_repeat("=", 60) . "\n\n";

// Test 1: Ping specific item by ID (if exists)
echo "📡 Test 1: Manual Ping Test\n";
echo str_repeat("-", 30) . "\n";

// Check if there are any items with IP addresses
require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

$query = "SELECT id, name, ip_address FROM ftth_items WHERE ip_address IS NOT NULL AND ip_address != '' LIMIT 3";
$stmt = $db->prepare($query);
$stmt->execute();
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($items)) {
    echo "❌ No items with IP addresses found in database\n";
    echo "💡 Add some items with IP addresses first\n\n";
} else {
    foreach ($items as $item) {
        echo "🔍 Testing item: {$item['name']} ({$item['ip_address']})\n";
        
        // Test ping via API
        $start_time = microtime(true);
        
        // Simulate GET request to monitoring API
        $_GET['action'] = 'ping';
        $_GET['id'] = $item['id'];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        
        ob_start();
        include 'api/monitoring.php';
        $response = ob_get_clean();
        
        $execution_time = round((microtime(true) - $start_time) * 1000, 1);
        
        echo "📊 API Response:\n";
        $decoded = json_decode($response, true);
        if ($decoded && $decoded['success']) {
            $data = $decoded['data'];
            echo "   Status: {$data['status']}\n";
            echo "   Response Time: {$data['response_time_ms']}ms\n";
            echo "   API Execution: {$execution_time}ms\n";
            if ($data['error_message']) {
                echo "   Error: {$data['error_message']}\n";
            }
        } else {
            echo "   ❌ API Error: " . ($decoded['message'] ?? 'Invalid response') . "\n";
            echo "   Raw Response: " . substr($response, 0, 200) . "\n";
        }
        echo "\n";
        
        // Clean up globals
        unset($_GET['action'], $_GET['id']);
    }
}

// Test 2: Ping all items
echo "📡 Test 2: Ping All Items\n";
echo str_repeat("-", 30) . "\n";

$_POST['action'] = 'ping_all';
$_SERVER['REQUEST_METHOD'] = 'POST';

$start_time = microtime(true);

ob_start();
include 'api/monitoring.php';
$response = ob_get_clean();

$execution_time = round((microtime(true) - $start_time) * 1000, 1);

echo "📊 Ping All Response:\n";
$decoded = json_decode($response, true);
if ($decoded && $decoded['success']) {
    echo "   Items pinged: " . count($decoded['data']) . "\n";
    echo "   Total execution: {$execution_time}ms\n";
    echo "   Average per item: " . round($execution_time / max(1, count($decoded['data'])), 1) . "ms\n\n";
    
    foreach ($decoded['data'] as $item) {
        echo "   - {$item['name']}: {$item['status']} ({$item['response_time_ms']}ms)\n";
    }
} else {
    echo "   ❌ API Error: " . ($decoded['message'] ?? 'Invalid response') . "\n";
}

unset($_POST['action']);

// Test 3: Test with specific IPs from user issue
echo "📡 Test 3: Specific IP Status Verification\n";
echo str_repeat("-", 30) . "\n";

$specific_ips = [
    '192.168.100.10' => 'EXISTS (should be online)',
    '192.168.100.1' => 'NOT EXISTS (should be offline)'
];

foreach ($specific_ips as $ip => $description) {
    echo "🔍 Testing IP: $ip ($description)\n";
    
    // Create a temporary item for testing
    $temp_query = "INSERT INTO ftth_items (item_type_id, name, ip_address, latitude, longitude) VALUES (7, 'TEST-$ip', '$ip', -6.2088, 106.8456)";
    $temp_stmt = $db->prepare($temp_query);
    $temp_stmt->execute();
    $temp_id = $db->lastInsertId();
    
    // Test ping via API
    $_GET['action'] = 'ping';
    $_GET['id'] = $temp_id;
    $_SERVER['REQUEST_METHOD'] = 'GET';
    
    ob_start();
    include 'api/monitoring.php';
    $response = ob_get_clean();
    
    echo "📊 API Response for $ip:\n";
    $decoded = json_decode($response, true);
    if ($decoded && $decoded['success']) {
        $data = $decoded['data'];
        echo "   Status: {$data['status']}\n";
        echo "   Response Time: " . ($data['response_time_ms'] ?? 'NULL') . "ms\n";
        if ($data['error_message']) {
            echo "   Error: " . substr($data['error_message'], 0, 100) . "\n";
        }
        
        // Check if status makes sense
        if ($ip === '192.168.100.10' && $data['status'] === 'online') {
            echo "   ✅ CORRECT: Existing IP shows as online\n";
        } elseif ($ip === '192.168.100.1' && $data['status'] === 'offline') {
            echo "   ✅ CORRECT: Non-existing IP shows as offline\n";
        } else {
            echo "   ❌ INCORRECT: Status doesn't match expected for this IP\n";
        }
    } else {
        echo "   ❌ API Error: " . ($decoded['message'] ?? 'Invalid response') . "\n";
    }
    
    // Clean up temporary item
    $cleanup_query = "DELETE FROM ftth_items WHERE id = ?";
    $cleanup_stmt = $db->prepare($cleanup_query);
    $cleanup_stmt->execute([$temp_id]);
    
    echo "\n";
    unset($_GET['action'], $_GET['id']);
}

echo "\n💡 Notes:\n";
echo "- Response times should match command line ping\n";
echo "- Check error.log for detailed ping debugging\n";
echo "- 192.168.100.10 should be ONLINE (device exists)\n";
echo "- 192.168.100.1 should be OFFLINE (device doesn't exist)\n";
echo "- Compare results with manual ping from command prompt\n";

echo "\n✅ Test completed at " . date('Y-m-d H:i:s') . "\n";
echo "</pre>";
?>
