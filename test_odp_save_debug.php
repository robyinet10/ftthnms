<?php
// Test ODP Save Debug
session_start();
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<h2>🔧 ODP Save Debug Test</h2>";
    
    // 1. Check latest ODP items
    echo "<h3>📊 Latest ODP Items:</h3>";
    $query = "SELECT id, name, odp_parent_odc_id, pon_config, created_at 
              FROM ftth_items 
              WHERE item_type_id = 3 
              ORDER BY id DESC 
              LIMIT 5";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $odp_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($odp_items) > 0) {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Name</th><th>Parent ODC ID</th><th>PON Config</th><th>Created</th></tr>";
        foreach ($odp_items as $odp) {
            echo "<tr>";
            echo "<td>{$odp['id']}</td>";
            echo "<td>{$odp['name']}</td>";
            echo "<td>" . ($odp['odp_parent_odc_id'] ?? 'NULL') . "</td>";
            echo "<td>";
            if ($odp['pon_config']) {
                $pon_data = json_decode($odp['pon_config'], true);
                if ($pon_data) {
                    echo "<strong>ODC Connections:</strong> " . count($pon_data['odp_odc_connections'] ?? []) . "<br>";
                    echo "<strong>ONT Outputs:</strong> " . count($pon_data['odp_ont_mappings'] ?? []) . "<br>";
                    echo "<small>" . htmlspecialchars(substr($odp['pon_config'], 0, 100)) . "...</small>";
                } else {
                    echo "<span style='color: red;'>Invalid JSON</span>";
                }
            } else {
                echo "<span style='color: orange;'>NULL</span>";
            }
            echo "</td>";
            echo "<td>{$odp['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No ODP items found.</p>";
    }
    
    // 2. Check ODC items for reference
    echo "<h3>📊 Available ODC Items:</h3>";
    $query = "SELECT id, name, odc_output_ports, odc_ports_used, status 
              FROM ftth_items 
              WHERE item_type_id IN (4, 12) 
              ORDER BY id";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $odc_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($odc_items) > 0) {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Name</th><th>Output Ports</th><th>Used Ports</th><th>Status</th></tr>";
        foreach ($odc_items as $odc) {
            $available = $odc['odc_output_ports'] - $odc['odc_ports_used'];
            echo "<tr>";
            echo "<td>{$odc['id']}</td>";
            echo "<td>{$odc['name']}</td>";
            echo "<td>{$odc['odc_output_ports']}</td>";
            echo "<td>{$odc['odc_ports_used']} (Available: {$available})</td>";
            echo "<td>{$odc['status']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No ODC items found.</p>";
    }
    
    // 3. Test API endpoint
    echo "<h3>🧪 Testing ODC API Endpoint:</h3>";
    
    // Simulate the API call that populates ODC dropdown
    $api_url = 'http://localhost/ftthnms/api/sync_olt_odc_data.php?action=get_odc_output_data';
    
    echo "<p>Testing API: <code>{$api_url}</code></p>";
    
    // Use cURL to test the API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "<p>HTTP Code: <strong>{$http_code}</strong></p>";
    
    if ($response) {
        $api_data = json_decode($response, true);
        if ($api_data) {
            echo "<p style='color: green;'>✅ API Response:</p>";
            echo "<pre>" . htmlspecialchars(json_encode($api_data, JSON_PRETTY_PRINT)) . "</pre>";
            
            if (isset($api_data['data']) && is_array($api_data['data'])) {
                echo "<p>📊 ODC Data Count: <strong>" . count($api_data['data']) . "</strong></p>";
                
                foreach ($api_data['data'] as $odc) {
                    echo "<p>• {$odc['odc_name']}: {$odc['available_ports']} ports available</p>";
                }
            }
        } else {
            echo "<p style='color: red;'>❌ Invalid JSON response</p>";
            echo "<pre>" . htmlspecialchars($response) . "</pre>";
        }
    } else {
        echo "<p style='color: red;'>❌ No response from API</p>";
    }
    
    // 4. Check form data structure
    echo "<h3>📋 Expected Form Data Structure:</h3>";
    echo "<p>When saving ODP with ODC connections, the form should send:</p>";
    echo "<ul>";
    echo "<li><code>odp_odc_connections[]</code> - Array of ODC:port values</li>";
    echo "<li><code>odp_cable_lengths[]</code> - Array of cable lengths</li>";
    echo "<li><code>odp_attenuations[]</code> - Array of attenuations</li>";
    echo "<li><code>odp_connection_descriptions[]</code> - Array of descriptions</li>";
    echo "<li><code>pon_config</code> - JSON string with all ODC connection data</li>";
    echo "</ul>";
    
    echo "<h3>🔍 Debug Instructions:</h3>";
    echo "<ol>";
    echo "<li>Open browser console (F12)</li>";
    echo "<li>Fill ODP form with ODC connections</li>";
    echo "<li>Click 'Simpan Item'</li>";
    echo "<li>Check console for debug logs starting with '🔄' and '📋'</li>";
    echo "<li>Look for 'collectOdpOdcConnections' and 'enhancedSaveItemForODP' logs</li>";
    echo "</ol>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>
