<?php
// Script untuk membuat/update test items dengan IP yang disebutkan user
require_once 'config/database.php';

echo "<h2>🛠️ Update Test Items</h2>\n";
echo "<pre>\n";

$database = new Database();
$db = $database->getConnection();

// Test items to create/update
$test_items = [
    [
        'name' => 'SERVER ROOM',
        'ip' => '192.168.100.10',
        'description' => 'Existing device - should be ONLINE',
        'item_type_id' => 7 // Server
    ],
    [
        'name' => 'NON-EXISTENT DEVICE',
        'ip' => '192.168.100.1',
        'description' => 'Non-existing IP - should be OFFLINE',
        'item_type_id' => 6 // Pelanggan
    ]
];

echo "Creating/updating test items for monitoring verification:\n";
echo str_repeat("=", 60) . "\n\n";

foreach ($test_items as $item) {
    echo "📝 Processing: {$item['name']}\n";
    echo "   IP Address: {$item['ip']}\n";
    echo "   Description: {$item['description']}\n";
    
    // Check if item already exists
    $check_query = "SELECT id, name, ip_address FROM ftth_items WHERE name = ? OR ip_address = ?";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->execute([$item['name'], $item['ip']]);
    $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        echo "   🔄 UPDATING existing item (ID: {$existing['id']})\n";
        
        // Update existing item
        $update_query = "UPDATE ftth_items SET 
                        name = ?, 
                        ip_address = ?, 
                        description = ?, 
                        item_type_id = ?,
                        port_http = 80,
                        port_https = 443,
                        monitoring_status = 'offline',
                        updated_at = NOW()
                        WHERE id = ?";
        $update_stmt = $db->prepare($update_query);
        $success = $update_stmt->execute([
            $item['name'],
            $item['ip'],
            $item['description'],
            $item['item_type_id'],
            $existing['id']
        ]);
        
        if ($success) {
            echo "   ✅ UPDATED successfully\n";
        } else {
            echo "   ❌ UPDATE failed\n";
        }
        
    } else {
        echo "   ➕ CREATING new item\n";
        
        // Create new item
        $insert_query = "INSERT INTO ftth_items 
                        (item_type_id, name, description, latitude, longitude, ip_address, port_http, port_https, monitoring_status, status) 
                        VALUES (?, ?, ?, -6.2088, 106.8456, ?, 80, 443, 'offline', 'active')";
        $insert_stmt = $db->prepare($insert_query);
        $success = $insert_stmt->execute([
            $item['item_type_id'],
            $item['name'],
            $item['description'],
            $item['ip']
        ]);
        
        if ($success) {
            $new_id = $db->lastInsertId();
            echo "   ✅ CREATED successfully (ID: $new_id)\n";
        } else {
            echo "   ❌ CREATE failed\n";
        }
    }
    
    echo "\n";
}

// Display current items with IP addresses
echo "📋 Current items with IP addresses:\n";
echo str_repeat("-", 60) . "\n";

$list_query = "SELECT id, name, ip_address, monitoring_status, item_type_id FROM ftth_items WHERE ip_address IS NOT NULL AND ip_address != '' ORDER BY name";
$list_stmt = $db->prepare($list_query);
$list_stmt->execute();
$items = $list_stmt->fetchAll(PDO::FETCH_ASSOC);

if ($items) {
    foreach ($items as $item) {
        $type_name = '';
        switch ($item['item_type_id']) {
            case 6: $type_name = 'Pelanggan'; break;
            case 7: $type_name = 'Server'; break;
            default: $type_name = "Type {$item['item_type_id']}"; break;
        }
        
        echo sprintf("ID: %-3s | %-20s | %-15s | %-8s | %s\n", 
            $item['id'], 
            $item['name'], 
            $item['ip_address'], 
            $item['monitoring_status'],
            $type_name
        );
    }
} else {
    echo "No items with IP addresses found.\n";
}

echo "\n💡 Next steps:\n";
echo "1. Run: http://localhost/ftthnms/fix_monitoring_status.php\n";
echo "2. Check application: http://localhost/ftthnms/\n";
echo "3. Test ping functionality for both items\n";
echo "4. Verify status indicators match reality\n";

echo "\n✅ Setup completed at " . date('Y-m-d H:i:s') . "\n";
echo "</pre>";
?>
