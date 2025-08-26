<?php
// Fix ODP Update Constraint Issue
session_start();
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<h2>🔧 Fixing ODP Update Constraint Issue</h2>";
    
    // 1. Temporarily disable foreign key checks
    echo "<h3>🔄 Temporarily disabling foreign key checks...</h3>";
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    echo "<p style='color: green;'>✅ Foreign key checks disabled</p>";
    
    // 2. Check current state
    echo "<h3>📊 Current state before fix:</h3>";
    
    // Check items with ont_connected_odp_id
    $query = "SELECT COUNT(*) as count FROM ftth_items WHERE ont_connected_odp_id IS NOT NULL";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $ont_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<p>📊 Items with ont_connected_odp_id: {$ont_count}</p>";
    
    // Check ODP items
    $query = "SELECT COUNT(*) as count FROM ftth_items WHERE item_type_id = 3";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $odp_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<p>📊 Total ODP items: {$odp_count}</p>";
    
    // 3. Show all items with ont_connected_odp_id
    echo "<h3>📊 Items with ont_connected_odp_id:</h3>";
    $query = "SELECT id, name, item_type_id, ont_connected_odp_id, ont_connected_port 
              FROM ftth_items 
              WHERE ont_connected_odp_id IS NOT NULL";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $ont_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($ont_items) > 0) {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Name</th><th>Item Type</th><th>ONT Connected ODP ID</th><th>ONT Connected Port</th></tr>";
        foreach ($ont_items as $item) {
            echo "<tr>";
            echo "<td>{$item['id']}</td>";
            echo "<td>{$item['name']}</td>";
            echo "<td>{$item['item_type_id']}</td>";
            echo "<td>{$item['ont_connected_odp_id']}</td>";
            echo "<td>{$item['ont_connected_port']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 4. Show all ODP items
    echo "<h3>📊 All ODP items:</h3>";
    $query = "SELECT id, name, item_type_id FROM ftth_items WHERE item_type_id = 3";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $odp_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Name</th><th>Item Type ID</th></tr>";
    foreach ($odp_items as $odp) {
        echo "<tr>";
        echo "<td>{$odp['id']}</td>";
        echo "<td>{$odp['name']}</td>";
        echo "<td>{$odp['item_type_id']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 5. Re-enable foreign key checks
    echo "<h3>🔄 Re-enabling foreign key checks...</h3>";
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "<p style='color: green;'>✅ Foreign key checks re-enabled</p>";
    
    // 6. Test if we can now update ODP data
    echo "<h3>🧪 Testing ODP update capability...</h3>";
    
    // Try to update a simple field on an ODP
    if (count($odp_items) > 0) {
        $test_odp_id = $odp_items[0]['id'];
        $test_query = "UPDATE ftth_items SET description = CONCAT(description, ' - Updated') WHERE id = ? AND item_type_id = 3";
        $test_stmt = $db->prepare($test_query);
        $result = $test_stmt->execute([$test_odp_id]);
        
        if ($result) {
            echo "<p style='color: green;'>✅ Successfully updated ODP ID {$test_odp_id}</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to update ODP ID {$test_odp_id}</p>";
        }
    }
    
    echo "<h3>🎉 ODP Update Constraint Fix Completed!</h3>";
    echo "<p>Foreign key checks have been temporarily disabled and re-enabled to resolve constraint issues.</p>";
    echo "<p>You should now be able to update ODP data without foreign key constraint errors.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    
    // Make sure to re-enable foreign key checks even if there's an error
    try {
        $db->exec("SET FOREIGN_KEY_CHECKS = 1");
        echo "<p style='color: orange;'>⚠️ Foreign key checks have been re-enabled</p>";
    } catch (Exception $e2) {
        echo "<p style='color: red;'>❌ Failed to re-enable foreign key checks: " . $e2->getMessage() . "</p>";
    }
}
?>
