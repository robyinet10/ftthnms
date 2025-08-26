<?php
// Test file untuk memeriksa data ODC di database
session_start();
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<h2>🔍 ODC Data Check</h2>";
    
    // Check item_types
    echo "<h3>📋 Item Types:</h3>";
    $query = "SELECT * FROM item_types WHERE id IN (4, 12)";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $item_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Name</th><th>Icon</th><th>Color</th></tr>";
    foreach ($item_types as $type) {
        echo "<tr>";
        echo "<td>{$type['id']}</td>";
        echo "<td>{$type['name']}</td>";
        echo "<td>{$type['icon']}</td>";
        echo "<td>{$type['color']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check ODC items
    echo "<h3>🏗️ ODC Items (item_type_id = 4):</h3>";
    $query = "SELECT id, name, description, odc_output_ports, status FROM ftth_items WHERE item_type_id = 4";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $odc_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($odc_items) > 0) {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Name</th><th>Description</th><th>Output Ports</th><th>Status</th></tr>";
        foreach ($odc_items as $item) {
            echo "<tr>";
            echo "<td>{$item['id']}</td>";
            echo "<td>{$item['name']}</td>";
            echo "<td>{$item['description']}</td>";
            echo "<td>{$item['odc_output_ports']}</td>";
            echo "<td>{$item['status']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'>❌ Tidak ada ODC items dengan item_type_id = 4</p>";
    }
    
    // Check ODC Cabinet items
    echo "<h3>🏗️ ODC Cabinet Items (item_type_id = 12):</h3>";
    $query = "SELECT id, name, description, odc_output_ports, status FROM ftth_items WHERE item_type_id = 12";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $odc_cabinet_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($odc_cabinet_items) > 0) {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Name</th><th>Description</th><th>Output Ports</th><th>Status</th></tr>";
        foreach ($odc_cabinet_items as $item) {
            echo "<tr>";
            echo "<td>{$item['id']}</td>";
            echo "<td>{$item['name']}</td>";
            echo "<td>{$item['description']}</td>";
            echo "<td>{$item['odc_output_ports']}</td>";
            echo "<td>{$item['status']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'>⚠️ Tidak ada ODC Cabinet items dengan item_type_id = 12</p>";
    }
    
    // Check all active items
    echo "<h3>📊 All Active Items:</h3>";
    $query = "SELECT id, item_type_id, name, status FROM ftth_items WHERE status = 'active' ORDER BY item_type_id, name";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $all_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Type ID</th><th>Name</th><th>Status</th></tr>";
    foreach ($all_items as $item) {
        $bgcolor = ($item['item_type_id'] == 4 || $item['item_type_id'] == 12) ? '#e8f5e8' : '';
        echo "<tr style='background-color: $bgcolor'>";
        echo "<td>{$item['id']}</td>";
        echo "<td>{$item['item_type_id']}</td>";
        echo "<td>{$item['name']}</td>";
        echo "<td>{$item['status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>
