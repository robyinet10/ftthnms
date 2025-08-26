<?php
// API untuk sinkronisasi data OLT PON dengan ODC dan ODC output dengan ODP
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? 'http://localhost'));
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, Authorization');
header('Access-Control-Allow-Credentials: true');
// Add cache-busting headers to prevent dropdown cache issues
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';
require_once 'auth.php';

try {
    // Check authentication
    if (!checkPermission()) {
        http_response_code(401);
        echo json_encode(array('success' => false, 'message' => 'Authentication required'));
        exit();
    }

    $database = new Database();
    $db = $database->getConnection();
    $action = $_GET['action'] ?? '';

    switch($action) {
        case 'get_olt_pon_data':
            getOltPonData($db);
            break;
            
        case 'get_odc_output_data':
            getOdcOutputData($db);
            break;
            
        case 'get_connected_odp_for_odc':
            getConnectedOdpForOdc($db);
            break;
            

            
        default:
            http_response_code(400);
            echo json_encode(array('success' => false, 'message' => 'Invalid action'));
            break;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array('success' => false, 'message' => 'Server error: ' . $e->getMessage()));
}

/**
 * Get OLT PON data for ODC PON Connection dropdown
 */
function getOltPonData($db) {
    try {
        $query = "SELECT 
                    id,
                    name,
                    ip_address,
                    pon_config,
                    description,
                    latitude,
                    longitude
                  FROM ftth_items 
                  WHERE item_type_id = 1 
                  AND status = 'active'
                  AND pon_config IS NOT NULL 
                  AND pon_config != ''
                  ORDER BY name";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $olt_pon_options = [];
        
        while ($olt = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $pon_config = json_decode($olt['pon_config'], true);
            
            if ($pon_config && isset($pon_config['pons']) && is_array($pon_config['pons'])) {
                foreach ($pon_config['pons'] as $pon) {
                    $pon_port = $pon['port'] ?? '';
                    $pon_description = $pon['description'] ?? '';
                    
                    // Get VLANs for this PON port
                    $vlans = [];
                    if (isset($pon['vlans']) && is_array($pon['vlans'])) {
                        foreach ($pon['vlans'] as $vlan) {
                            $vlans[] = [
                                'vlan_id' => $vlan['vlan_id'] ?? '',
                                'description' => $vlan['description'] ?? ''
                            ];
                        }
                    }
                    
                    $olt_pon_options[] = [
                        'olt_id' => $olt['id'],
                        'olt_name' => $olt['name'],
                        'olt_ip' => $olt['ip_address'],
                        'pon_port' => $pon_port,
                        'pon_description' => $pon_description,
                        'vlans' => $vlans,
                        'display_text' => $olt['name'] . ' (' . $olt['ip_address'] . ') - ' . $pon_port,
                        'connection_value' => $olt['id'] . ':' . $pon_port
                    ];
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'data' => $olt_pon_options,
            'total' => count($olt_pon_options)
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching OLT PON data: ' . $e->getMessage()
        ]);
    }
}

/**
 * Get ODC output data for ODP Parent ODC dropdown
 */
function getOdcOutputData($db) {
    try {
        // Enhanced query to get all ODC types (both pole mounted and cabinet)
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
            
            // Parse ODC output configuration if exists
            $outputs = [];
            $pon_config = json_decode($odc['pon_config'], true);
            if ($pon_config && isset($pon_config['odc_odp_outputs'])) {
                $outputs = $pon_config['odc_odp_outputs'];
            }
            
            $odc_options[] = [
                'odc_id' => $odc['id'],
                'odc_name' => $odc['name'],
                'odc_description' => $odc['description'],
                'odc_type' => $odc['odc_type'] ?? 'pole_mounted',
                'total_ports' => $total_ports,
                'used_ports' => $used_ports,
                'available_ports' => $available_ports,
                'outputs' => $outputs,
                'display_text' => $odc['name'] . ' (' . $available_ports . '/' . $total_ports . ' ports available)',
                'disabled' => $available_ports <= 0
            ];
        }
        
        // Add debug information
        $debug_info = [
            'total_odcs_found' => $total_odcs,
            'query_executed' => $query,
            'item_types_searched' => [4, 12],
            'odc_details' => array_map(function($odc) {
                return [
                    'id' => $odc['odc_id'],
                    'name' => $odc['odc_name'],
                    'total_ports' => $odc['total_ports'],
                    'available_ports' => $odc['available_ports'],
                    'used_ports' => $odc['used_ports']
                ];
            }, $odc_options)
        ];
        
        echo json_encode([
            'success' => true,
            'data' => $odc_options,
            'total' => count($odc_options),
            'debug' => $debug_info
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching ODC output data: ' . $e->getMessage()
        ]);
    }
}

/**
 * Get ODP items connected to specific ODC via pon_config
 */
function getConnectedOdpForOdc($db) {
    try {
        $odc_id = $_GET['odc_id'] ?? null;
        
        if (!$odc_id) {
            throw new Exception('ODC ID is required');
        }
        
        // Query untuk mendapatkan ODP yang memiliki koneksi ke ODC ini dalam pon_config
        $query = "SELECT 
                    id,
                    name,
                    description,
                    odp_capacity,
                    odp_ports_used,
                    pon_config,
                    latitude,
                    longitude
                  FROM ftth_items 
                  WHERE item_type_id = 3 
                  AND status = 'active'
                  AND pon_config IS NOT NULL
                  AND pon_config != ''
                  AND pon_config LIKE CONCAT('%\"odc_connection\":\"', :odc_id, ':%')";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':odc_id', $odc_id);
        $stmt->execute();
        
        $connected_odp = [];
        
        while ($odp = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Parse pon_config untuk extract connection details
            $connection_details = [];
            $pon_config = json_decode($odp['pon_config'], true);
            
            if ($pon_config && isset($pon_config['odp_odc_connections'])) {
                foreach ($pon_config['odp_odc_connections'] as $conn) {
                    if (is_array($conn) && isset($conn['odc_connection'])) {
                        $odc_connection = $conn['odc_connection'];
                        
                        // Check if this connection is for our ODC
                        if (strpos($odc_connection, $odc_id . ':') === 0) {
                            $port = explode(':', $odc_connection)[1] ?? 'Unknown';
                            $connection_details[] = [
                                'port' => $port,
                                'cable_length' => $conn['cable_length'] ?? '-',
                                'attenuation' => $conn['attenuation'] ?? '-',
                                'description' => $conn['description'] ?? ''
                            ];
                        }
                    }
                }
            }
            
            if (!empty($connection_details)) {
                $connected_odp[] = [
                    'id' => $odp['id'],
                    'name' => $odp['name'],
                    'description' => $odp['description'],
                    'odp_capacity' => $odp['odp_capacity'],
                    'odp_ports_used' => $odp['odp_ports_used'],
                    'latitude' => $odp['latitude'],
                    'longitude' => $odp['longitude'],
                    'connection_details' => $connection_details,
                    'total_connections' => count($connection_details)
                ];
            }
        }
        
        echo json_encode([
            'success' => true,
            'data' => $connected_odp,
            'total' => count($connected_odp),
            'odc_id' => $odc_id
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching connected ODP: ' . $e->getMessage()
        ]);
    }
}

/**
 * Get available ONT ports for specific ODP
 */
function getAvailableOntPorts($db) {
    try {
        $odp_id = $_GET['odp_id'] ?? null;
        
        if (!$odp_id) {
            throw new Exception('ODP ID is required');
        }
        
        // Get ODP capacity
        $odp_query = "SELECT odp_capacity FROM ftth_items WHERE id = :odp_id AND item_type_id = 3 AND status = 'active'";
        $odp_stmt = $db->prepare($odp_query);
        $odp_stmt->bindParam(':odp_id', $odp_id);
        $odp_stmt->execute();
        $odp_result = $odp_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$odp_result) {
            throw new Exception('ODP not found');
        }
        
        $total_ports = $odp_result['odp_capacity'] ?? 8;
        
        // Get occupied ports
        $query = "SELECT ont_connected_port 
                  FROM ftth_items 
                  WHERE ont_connected_odp_id = :odp_id 
                    AND ont_connected_port IS NOT NULL 
                    AND item_type_id = 6 
                    AND status = 'active'";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':odp_id', $odp_id);
        $stmt->execute();
        
        $occupied_ports = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $occupied_ports[] = (int)$row['ont_connected_port'];
        }
        
        $available_ports = $total_ports - count($occupied_ports);
        
        echo json_encode([
            'success' => true,
            'odp_id' => $odp_id,
            'total_ports' => $total_ports,
            'occupied_ports' => $occupied_ports,
            'available_ports' => $available_ports,
            'port_usage' => count($occupied_ports) . '/' . $total_ports
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching available ONT ports: ' . $e->getMessage()
        ]);
    }
}

?>
