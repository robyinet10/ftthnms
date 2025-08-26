<?php
// Test script untuk memverifikasi fungsi ping yang telah diperbaiki
require_once 'api/monitoring.php';

echo "<h2>🧪 Test Ping Functionality</h2>\n";
echo "<pre>\n";

// Test hosts
$test_hosts = [
    '192.168.100.10',  // SERVER ROOM dari screenshot
    '8.8.8.8',         // Google DNS
    '1.1.1.1',         // Cloudflare DNS
    'localhost',       // Local
    '192.168.1.1'      // Typical router
];

foreach ($test_hosts as $host) {
    echo "\n🔍 Testing ping to: $host\n";
    echo str_repeat("-", 50) . "\n";
    
    $start_time = microtime(true);
    $result = pingHost($host);
    $total_execution_time = round((microtime(true) - $start_time) * 1000, 1);
    
    if ($result['success']) {
        echo "✅ Status: SUCCESS\n";
        echo "⏱️  Response Time: {$result['response_time_ms']}ms\n";
        echo "🕐 Total Execution: {$total_execution_time}ms\n";
        echo "📊 Ratio: " . round($result['response_time_ms'] / $total_execution_time * 100, 1) . "%\n";
        
        // Show first few lines of ping output
        $lines = explode("\n", $result['output']);
        echo "📋 Ping Output (first 3 lines):\n";
        for ($i = 0; $i < min(3, count($lines)); $i++) {
            echo "   " . trim($lines[$i]) . "\n";
        }
    } else {
        echo "❌ Status: FAILED\n";
        echo "⏱️  Response Time: NULL\n";
        echo "🕐 Total Execution: {$total_execution_time}ms\n";
        echo "❗ Error: " . substr($result['error'], 0, 100) . "...\n";
    }
    
    echo "\n";
}

echo "\n🔧 System Information:\n";
echo "OS: " . PHP_OS . "\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n";

echo "\n💡 Manual Command Line Test:\n";
$os = strtoupper(substr(PHP_OS, 0, 3));
if ($os === 'WIN') {
    echo "Run in CMD: ping -n 1 -w 3000 192.168.100.10\n";
} else {
    echo "Run in Terminal: ping -c 1 -W 3 192.168.100.10\n";
}

echo "\n</pre>";
?>
