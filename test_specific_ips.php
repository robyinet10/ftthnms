<?php
// Test script untuk menguji IP spesifik yang disebutkan user
require_once 'api/monitoring.php';

echo "<h2>🎯 Test Specific IP Addresses</h2>\n";
echo "<pre>\n";

echo "Testing real-world scenario:\n";
echo "- IP 192.168.100.10 (EXISTS - should be ONLINE)\n";
echo "- IP 192.168.100.1  (NOT EXISTS - should be OFFLINE)\n";
echo str_repeat("=", 70) . "\n\n";

$test_cases = [
    [
        'ip' => '192.168.100.10',
        'description' => 'SERVER ROOM (EXISTS)',
        'expected' => 'online'
    ],
    [
        'ip' => '192.168.100.1',
        'description' => 'NON-EXISTENT IP',
        'expected' => 'offline'
    ],
    [
        'ip' => '8.8.8.8',
        'description' => 'Google DNS (CONTROL)',
        'expected' => 'online'
    ],
    [
        'ip' => '192.168.99.99',
        'description' => 'FAKE IP (CONTROL)',
        'expected' => 'offline'
    ]
];

foreach ($test_cases as $index => $test) {
    echo "📡 Test " . ($index + 1) . ": {$test['description']}\n";
    echo "   IP: {$test['ip']}\n";
    echo "   Expected: " . strtoupper($test['expected']) . "\n";
    echo str_repeat("-", 50) . "\n";
    
    // Test manual command first
    $os = strtoupper(substr(PHP_OS, 0, 3));
    if ($os === 'WIN') {
        $manual_command = "ping -n 1 -w 3000 {$test['ip']}";
    } else {
        $manual_command = "ping -c 1 -W 3 {$test['ip']}";
    }
    
    echo "🔨 Manual Command: $manual_command\n";
    
    $manual_output = [];
    $manual_return = 0;
    exec($manual_command, $manual_output, $manual_return);
    $manual_text = implode("\n", $manual_output);
    
    echo "📋 Manual Result:\n";
    echo "   Return Code: $manual_return\n";
    echo "   First Line: " . (isset($manual_output[0]) ? $manual_output[0] : 'No output') . "\n";
    
    // Determine manual status
    if ($manual_return === 0) {
        if (strpos($manual_text, 'Reply from') !== false) {
            $manual_status = '✅ SUCCESS';
        } elseif (strpos($manual_text, 'Request timed out') !== false) {
            $manual_status = '⏱️ TIMEOUT';
        } else {
            $manual_status = '❓ UNKNOWN SUCCESS';
        }
    } else {
        $manual_status = '❌ FAILED';
    }
    echo "   Manual Status: $manual_status\n\n";
    
    // Test via our function
    echo "🔧 Our Function Test:\n";
    $start_time = microtime(true);
    $result = pingHost($test['ip']);
    $execution_time = round((microtime(true) - $start_time) * 1000, 1);
    
    echo "   Success: " . ($result['success'] ? 'TRUE' : 'FALSE') . "\n";
    echo "   Response Time: " . ($result['response_time_ms'] ?? 'NULL') . "ms\n";
    echo "   Execution Time: {$execution_time}ms\n";
    
    if ($result['success']) {
        echo "   ✅ Status: SUCCESS\n";
        $actual_status = 'online';
    } else {
        echo "   ❌ Error: " . substr($result['error'], 0, 100) . "...\n";
        
        // Determine status based on error
        $error_lower = strtolower($result['error']);
        if (strpos($error_lower, 'request timed out') !== false || 
            strpos($error_lower, 'timeout') !== false) {
            $actual_status = 'warning';
        } else {
            $actual_status = 'offline';
        }
    }
    
    echo "   📊 Determined Status: " . strtoupper($actual_status) . "\n";
    echo "   🎯 Expected Status: " . strtoupper($test['expected']) . "\n";
    
    // Check if result matches expectation
    if ($actual_status === $test['expected']) {
        echo "   ✅ RESULT: CORRECT\n";
    } else {
        echo "   ❌ RESULT: INCORRECT (Expected: {$test['expected']}, Got: $actual_status)\n";
    }
    
    echo "\n" . str_repeat("=", 70) . "\n\n";
}

echo "💡 NOTES:\n";
echo "- Check error.log for detailed ping debugging\n";
echo "- 192.168.100.10 should show ONLINE with low response time\n";
echo "- 192.168.100.1 should show OFFLINE or TIMEOUT\n";
echo "- If results are wrong, check network connectivity\n";

echo "\n🕒 Test completed at: " . date('Y-m-d H:i:s') . "\n";
echo "</pre>";
?>
