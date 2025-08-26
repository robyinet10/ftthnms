<?php
// Fix ODC Output Ports
session_start();
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<h2>🔧 Fixing ODC Output Ports</h2>";
    
    // Check current ODC data
    echo "<h3>📊 Current ODC Data:</h3>";
    $query = "SELECT id, name, odc_output_ports, odc_capacity, odc_ports_used, status FROM ftth_items WHERE item_type_id = 4";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $current_odcs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Name</th><th>Output Ports</th><th>Capacity</th><th>Used Ports</th><th>Status</th></tr>";
    foreach ($current_odcs as $odc) {
        echo "<tr>";
        echo "<td>{$odc['id']}</td>";
        echo "<td>{$odc['name']}</td>";
        echo "<td>{$odc['odc_output_ports']}</td>";
        echo "<td>{$odc['odc_capacity']}</td>";
        echo "<td>{$odc['odc_ports_used']}</td>";
        echo "<td>{$odc['status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Update semua ODC yang memiliki output_ports = 0
    echo "<h3>🔄 Updating semua ODC dengan output_ports = 0...</h3>";
    $update_query = "UPDATE ftth_items 
                     SET odc_output_ports = 4,
                         odc_capacity = 32,
                         odc_ports_used = 0,
                         odc_type = 'pole_mounted',
                         odc_installation_type = 'pole',
                         odc_main_splitter_ratio = '1:4',
                         odc_odp_splitter_ratio = '1:8',
                         odc_input_ports = 1
                     WHERE item_type_id = 4 
                     AND (odc_output_ports = 0 OR odc_output_ports IS NULL)";
    
    $update_stmt = $db->prepare($update_query);
    $result = $update_stmt->execute();
    
    if ($result) {
        echo "<p style='color: green;'>✅ ODC Central updated successfully!</p>";
    } else {
        echo "<p style='color: red;'>❌ Failed to update ODC Central</p>";
    }
    
    // Check updated ODC data
    echo "<h3>📊 Updated ODC Data:</h3>";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $updated_odcs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Name</th><th>Output Ports</th><th>Capacity</th><th>Used Ports</th><th>Status</th></tr>";
    foreach ($updated_odcs as $odc) {
        $bgcolor = ($odc['odc_output_ports'] > 0) ? '#e8f5e8' : '#ffe6e6';
        echo "<tr style='background-color: $bgcolor'>";
        echo "<td>{$odc['id']}</td>";
        echo "<td>{$odc['name']}</td>";
        echo "<td>{$odc['odc_output_ports']}</td>";
        echo "<td>{$odc['odc_capacity']}</td>";
        echo "<td>{$odc['odc_ports_used']}</td>";
        echo "<td>{$odc['status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Test API response
    echo "<h3>🧪 Testing API Response:</h3>";
    echo "<p>Now test the ODC dropdown in the ODP form or use debugOdc.testOdcApi() in browser console.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>
