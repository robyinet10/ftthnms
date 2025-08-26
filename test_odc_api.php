<?php
// Test ODC API Response
session_start();
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<h2>🧪 Testing ODC API Response</h2>";
    
    // Simulate the API call
    $query = "SELECT 
                id,
                name,
                description,
                odc_output_ports,
                odc_capacity,
                odc_type,
                pon_config,
                latitude,
                longitude,
                status
              FROM ftth_items 
              WHERE item_type_id IN (4, 12) 
              AND status = 'active'
              ORDER BY name";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $odc_options = [];
    $total_odcs = 0;
    
    while ($odc = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $total_odcs++;
        
        // Get used ports count from ODP connections
        $used_ports_query = "SELECT COUNT(*) as used_count 
                           FROM ftth_items 
                           WHERE item_type_id = 3 
                           AND odp_parent_odc_id = :odc_id 
                           AND status = 'active'";
        $used_stmt = $db->prepare($used_ports_query);
        $used_stmt->bindParam(':odc_id', $odc['id']);
        $used_stmt->execute();
        $used_result = $used_stmt->fetch(PDO::FETCH_ASSOC);
        $used_ports = $used_result['used_count'] ?? 0;
        
        $total_ports = $odc['odc_output_ports'] ?? 4;
        
        // Handle case where odc_output_ports is 0 or null
        if ($total_ports <= 0) {
            $total_ports = 4; // Default to 4 ports
        }
        
        $available_ports = max(0, $total_ports - $used_ports);
        
        $odc_options[] = [
            'odc_id' => $odc['id'],
            'odc_name' => $odc['name'],
            'odc_description' => $odc['description'],
            'odc_type' => $odc['odc_type'] ?? 'pole_mounted',
            'total_ports' => $total_ports,
            'used_ports' => $used_ports,
            'available_ports' => $available_ports,
            'display_text' => $odc['name'] . ' (' . $available_ports . '/' . $total_ports . ' ports available)',
            'disabled' => $available_ports <= 0
        ];
    }
    
    echo "<h3>📊 API Response Data:</h3>";
    echo "<pre>";
    echo json_encode([
        'success' => true,
        'data' => $odc_options,
        'total' => count($odc_options),
        'debug' => [
            'total_odcs_found' => $total_odcs,
            'query_executed' => $query,
            'item_types_searched' => [4, 12]
        ]
    ], JSON_PRETTY_PRINT);
    echo "</pre>";
    
    echo "<h3>📋 Dropdown Options that will be generated:</h3>";
    if (count($odc_options) > 0) {
        echo "<ul>";
        foreach ($odc_options as $odc) {
            echo "<li><strong>{$odc['odc_name']}</strong> - {$odc['available_ports']}/{$odc['total_ports']} ports available</li>";
            
            // Show individual port options
            for ($port = 1; $port <= $odc['total_ports']; $port++) {
                $isAvailable = $port <= $odc['available_ports'];
                $status = $isAvailable ? 'Available' : 'Used';
                $color = $isAvailable ? 'green' : 'red';
                echo "<ul><li style='color: $color'>Port $port - $status</li></ul>";
            }
        }
        echo "</ul>";
    } else {
        echo "<p style='color: red;'>❌ No ODC options found</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>
