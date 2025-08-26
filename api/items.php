<?php
// Prevent caching of API responses
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');


// Disable error output for clean JSON response
error_reporting(E_ALL);
ini_set("display_errors", 0);
ini_set("log_errors", 1);
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
// Don't use wildcard for origin when dealing with credentials/sessions
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? 'http://localhost'));
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, Authorization');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 3600');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';
require_once 'auth.php';

// Function to manually parse multipart form data
function parseMultipartFormData($input, $contentType) {
    $data = array();
    
    // Extract boundary from content type
    if (preg_match('/boundary=(.+)$/', $contentType, $matches)) {
        $boundary = $matches[1];
        
        // Split the input by boundary
        $parts = array_slice(explode('--' . $boundary, $input), 1);
        
        foreach ($parts as $part) {
            // Skip empty parts and closing boundary
            if (trim($part) == '--' || empty(trim($part))) continue;
            
            // Split headers and body
            $sections = explode("\r\n\r\n", $part, 2);
            if (count($sections) != 2) continue;
            
            $headers = $sections[0];
            $body = rtrim($sections[1], "\r\n");
            
            // Extract field name from Content-Disposition header
            if (preg_match('/name="([^"]*)"/', $headers, $matches)) {
                $fieldName = $matches[1];
                $data[$fieldName] = $body;
            }
        }
    }
    
    return $data;
}

$database = new Database();
$db = $database->getConnection();

// Handle method override and multipart data parsing
$method = $_SERVER['REQUEST_METHOD'];
$parsed_data = array();

// Parse multipart form data manually if needed
if (($method === 'PUT' || $method === 'PATCH') && empty($_POST) && 
    isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false) {
    
    // Force treat as POST to get parsed form data
    $raw_input = file_get_contents('php://input');
    $parsed_data = parseMultipartFormData($raw_input, $_SERVER['CONTENT_TYPE']);
    
    // If we found form data, treat this as a method override
    if (!empty($parsed_data)) {
        if (isset($parsed_data['_method'])) {
            $method = strtoupper($parsed_data['_method']);
            unset($parsed_data['_method']);
        }
        // Populate $_POST with parsed data for compatibility
        $_POST = $parsed_data;
    }
}

// Check for X-HTTP-Method-Override header
if (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
    $method = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'];
}

// Check for _method parameter (Laravel style) - now works with manually parsed data too
if (isset($_POST['_method'])) {
    $method = strtoupper($_POST['_method']);
    unset($_POST['_method']); // Clean it up
}

$response = array('success' => false, 'message' => '', 'data' => null);

// Log only important requests for production
if ($method === 'PUT' || $method === 'DELETE') {
    error_log("FTTH API - " . $method . " request, ID: " . (isset($_POST['id']) ? $_POST['id'] : 'N/A'));
}

try {
    // Check authentication for all requests
    if (!checkPermission()) {
        http_response_code(401);
        echo json_encode(array('success' => false, 'message' => 'Authentication required'));
        exit();
    }

    switch($method) {
        case 'GET':
            // GET requests allowed for all authenticated users
            if (isset($_GET['action']) && $_GET['action'] === 'get_available_odc_ports') {
                // Get available ODC ports for ODP form
                $query = "SELECT 
                            odc.id as odc_id,
                            odc.name as odc_name,
                            odc.odc_output_ports,
                            COALESCE(used_ports.used_count, 0) as used_ports,
                            (odc.odc_output_ports - COALESCE(used_ports.used_count, 0)) as available_ports
                         FROM ftth_items odc
                         LEFT JOIN (
                            SELECT odc_item_id, COUNT(*) as used_count 
                            FROM odp_odc_mapping 
                            WHERE status = 'active' 
                            GROUP BY odc_item_id
                         ) used_ports ON odc.id = used_ports.odc_item_id
                         WHERE odc.item_type_id IN (4, 12) 
                         AND odc.status = 'active'
                         AND (odc.odc_output_ports - COALESCE(used_ports.used_count, 0)) > 0
                         ORDER BY odc.name";
                
                $stmt = $db->prepare($query);
                $stmt->execute();
                $available_odcs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $response['success'] = true;
                $response['data'] = $available_odcs;
                break;
            }
            
            if (isset($_GET['id'])) {
                // Get single item with SNMP metrics data
                $query = "SELECT i.*, it.name as item_type_name, it.icon, it.color,
                                tc.color_name as tube_color_name, tc.hex_code,
                                cc.color_name as core_color_name, cc.hex_code as core_hex_code,
                                sm.ratio as splitter_main_ratio,
                                so.ratio as splitter_odp_ratio,
                                snmp.cpu_usage_percent as cpu_usage,
                                snmp.memory_usage_percent as memory_usage,
                                snmp.device_name as snmp_device_name,
                                snmp.device_uptime,
                                snmp.interface_status,
                                snmp.interface_speed_mbps,
                                snmp.bytes_in_total,
                                snmp.bytes_out_total,
                                snmp.optical_power_tx_dbm,
                                snmp.optical_power_rx_dbm,
                                snmp.metric_time as last_snmp_time,
                                CASE 
                                    WHEN snmp.metric_time IS NULL THEN 'no_snmp_data'
                                    WHEN (snmp.cpu_usage_percent > 80 OR snmp.memory_usage_percent > 80) THEN 'snmp_warning'
                                    WHEN snmp.metric_time >= DATE_SUB(NOW(), INTERVAL 10 MINUTE) THEN 'snmp_active'
                                    ELSE 'snmp_stale'
                                END as snmp_status
                         FROM ftth_items i
                         LEFT JOIN item_types it ON i.item_type_id = it.id
                         LEFT JOIN tube_colors tc ON i.tube_color_id = tc.id
                         LEFT JOIN tube_colors cc ON i.core_color_id = cc.id
                         LEFT JOIN splitter_types sm ON i.splitter_main_id = sm.id
                         LEFT JOIN splitter_types so ON i.splitter_odp_id = so.id
                         LEFT JOIN latest_snmp_metrics snmp ON i.id = snmp.item_id
                         WHERE i.id = :id";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $_GET['id']);
                $stmt->execute();
                
                $item = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($item) {
                    $response['success'] = true;
                    $response['data'] = $item;
                } else {
                    $response['message'] = 'Item not found';
                }
            } else {
                // Get all items with SNMP metrics data
                $where_conditions = ["1=1"]; // Base condition
                $params = [];
                
                // Filter by item_type_id if provided
                if (isset($_GET['item_type_id']) && !empty($_GET['item_type_id'])) {
                    $where_conditions[] = "i.item_type_id = :item_type_id";
                    $params[':item_type_id'] = $_GET['item_type_id'];
                }
                
                // Filter by status if provided
                if (isset($_GET['status']) && !empty($_GET['status'])) {
                    $where_conditions[] = "i.status = :status";
                    $params[':status'] = $_GET['status'];
                }
                
                // Additional filter to ensure only "Tiang ODP" items for item_type_id = 3
                if (isset($_GET['item_type_id']) && $_GET['item_type_id'] == 3) {
                    $where_conditions[] = "it.name = 'Tiang ODP'";
                }
                
                $where_clause = implode(" AND ", $where_conditions);
                
                $query = "SELECT i.*, it.name as item_type_name, it.icon, it.color,
                                tc.color_name as tube_color_name, tc.hex_code,
                                cc.color_name as core_color_name, cc.hex_code as core_hex_code,
                                sm.ratio as splitter_main_ratio,
                                so.ratio as splitter_odp_ratio,
                                snmp.cpu_usage_percent as cpu_usage,
                                snmp.memory_usage_percent as memory_usage,
                                snmp.device_name as snmp_device_name,
                                snmp.device_uptime,
                                snmp.interface_status,
                                snmp.interface_speed_mbps,
                                snmp.bytes_in_total,
                                snmp.bytes_out_total,
                                snmp.optical_power_tx_dbm,
                                snmp.optical_power_rx_dbm,
                                snmp.metric_time as last_snmp_time,
                                CASE 
                                    WHEN snmp.metric_time IS NULL THEN 'no_snmp_data'
                                    WHEN (snmp.cpu_usage_percent > 80 OR snmp.memory_usage_percent > 80) THEN 'snmp_warning'
                                    WHEN snmp.metric_time >= DATE_SUB(NOW(), INTERVAL 10 MINUTE) THEN 'snmp_active'
                                    ELSE 'snmp_stale'
                                END as snmp_status
                         FROM ftth_items i
                         LEFT JOIN item_types it ON i.item_type_id = it.id
                         LEFT JOIN tube_colors tc ON i.tube_color_id = tc.id
                         LEFT JOIN tube_colors cc ON i.core_color_id = cc.id
                         LEFT JOIN splitter_types sm ON i.splitter_main_id = sm.id
                         LEFT JOIN splitter_types so ON i.splitter_odp_id = so.id
                         LEFT JOIN latest_snmp_metrics snmp ON i.id = snmp.item_id
                         WHERE {$where_clause}
                         ORDER BY i.created_at DESC";
                
                $stmt = $db->prepare($query);
                
                // Bind parameters
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value);
                }
                
                $stmt->execute();
                
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $response['success'] = true;
                $response['data'] = $items;
            }
            break;
            
        case 'POST':
            // Check admin permission for create operations
            if (!isAdmin()) {
                http_response_code(403);
                echo json_encode(array('success' => false, 'message' => 'Admin permission required for create operations'));
                exit();
            }
            
            // DEBUG: Log all POST data for debugging upstream interface issue
            error_log("🔧 API DEBUG - POST data received: " . json_encode($_POST));
            if (isset($_POST['upstream_interface_id'])) {
                error_log("🔌 Upstream interface ID found in POST: " . $_POST['upstream_interface_id']);
            } else {
                error_log("❌ Upstream interface ID NOT found in POST data");
            }
            
            // Handle different POST actions
            if (isset($_POST['action']) && $_POST['action'] === 'generate_tiang_tumpu') {
                // Generate tiang tumpu for route with pricing
                $route_id = $_POST['route_id'];
                $positions = json_decode($_POST['positions'], true);
                $interval_meters = $_POST['interval_meters'] ?? 30;
                $generate_at_turns = $_POST['generate_at_turns'] ?? true;
                $default_price = (float)($_POST['default_price'] ?? 750000);
                $auto_calculate_cost = (int)($_POST['auto_calculate_cost'] ?? 1);
                $estimated_total_cost = (float)($_POST['estimated_total_cost'] ?? 0);
                
                if (!$positions || !is_array($positions)) {
                    $response['message'] = 'Invalid positions data';
                    break;
                }
                
                $generated_count = 0;
                $generated_ids = [];
                $total_cost = 0;
                
                // Begin transaction
                $db->beginTransaction();
                
                try {
                    foreach ($positions as $position) {
                        $name = "Tiang Tumpu " . ($position['type'] === 'turn' ? 'Tikungan' : 'Auto');
                        $description = $position['type'] === 'turn' ? 
                            "Auto-generated tiang tumpu di tikungan (sudut: " . round($position['angle_change'] ?? 0, 1) . "°)" :
                            "Auto-generated tiang tumpu setiap " . $interval_meters . "m (jarak: " . round($position['distance_from_start'] ?? 0, 1) . "m)";
                        
                        // Add pricing info to description if available
                        if ($auto_calculate_cost && $default_price > 0) {
                            $description .= " | Harga: Rp " . number_format($default_price, 0, ',', '.');
                        }
                        
                        $query = "INSERT INTO ftth_items (item_type_id, name, description, latitude, longitude, 
                                 item_price, is_auto_generated, auto_generated_by_route_id, auto_generated_type, 
                                 created_at, updated_at) 
                                 VALUES (2, :name, :description, :latitude, :longitude, :item_price,
                                 1, :route_id, :type, NOW(), NOW())";
                        
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':name', $name);
                        $stmt->bindParam(':description', $description);
                        $stmt->bindParam(':latitude', $position['lat']);
                        $stmt->bindParam(':longitude', $position['lng']);
                        $stmt->bindParam(':item_price', $default_price);
                        $stmt->bindParam(':route_id', $route_id);
                        $stmt->bindParam(':type', $position['type']);
                        
                        if ($stmt->execute()) {
                            $generated_ids[] = $db->lastInsertId();
                            $generated_count++;
                            $total_cost += $default_price;
                        }
                    }
                    
                    // Update cable_routes with generated tiang tumpu info and cost
                    $query = "UPDATE cable_routes SET 
                             auto_generate_tiang_tumpu = 1,
                             generated_tiang_tumpu_ids = :generated_ids,
                             tiang_tumpu_interval_meters = :interval_meters,
                             generate_at_turns = :generate_at_turns,
                             total_generated_cost = :total_cost
                             WHERE id = :route_id";
                    
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':generated_ids', json_encode($generated_ids));
                    $stmt->bindParam(':interval_meters', $interval_meters);
                    $stmt->bindParam(':generate_at_turns', $generate_at_turns);
                    $stmt->bindParam(':total_cost', $total_cost);
                    $stmt->bindParam(':route_id', $route_id);
                    $stmt->execute();
                    
                    $db->commit();
                    
                    $response['success'] = true;
                    $response['message'] = "Successfully generated {$generated_count} tiang tumpu with total cost Rp " . number_format($total_cost, 0, ',', '.');
                    $response['generated_count'] = $generated_count;
                    $response['generated_ids'] = $generated_ids;
                    $response['total_cost'] = $total_cost;
                    $response['formatted_cost'] = 'Rp ' . number_format($total_cost, 0, ',', '.');
                    
                } catch (Exception $e) {
                    $db->rollBack();
                    $response['message'] = 'Failed to generate tiang tumpu: ' . $e->getMessage();
                }
                
                break;
            }
            
            // Create new item
            $item_type_id = $_POST['item_type'] ?? null;
            $item_type = $_POST['item_type_name'] ?? null;
            $item_price = (!empty($_POST['item_price'])) ? $_POST['item_price'] : null;
            $name = $_POST['name'] ?? null;
            $description = $_POST['description'] ?? null;
            $latitude = $_POST['latitude'] ?? null;
            $longitude = $_POST['longitude'] ?? null;
            $address = $_POST['address'] ?? null;
            // Handle foreign key fields - convert empty strings to NULL
            $tube_color_id = (!empty($_POST['tube_color_id']) && $_POST['tube_color_id'] !== '0') ? $_POST['tube_color_id'] : null;
            $core_used = (!empty($_POST['core_used']) && $_POST['core_used'] !== '0') ? $_POST['core_used'] : null;
            $core_color_id = (!empty($_POST['core_color_id']) && $_POST['core_color_id'] !== '0') ? $_POST['core_color_id'] : null;
            $item_cable_type = $_POST['item_cable_type'] ?? 'distribution';
            $total_core_capacity = $_POST['total_core_capacity'] ?? 24;
            $splitter_main_id = (!empty($_POST['splitter_main_id']) && $_POST['splitter_main_id'] !== '0') ? $_POST['splitter_main_id'] : null;
            $splitter_odp_id = (!empty($_POST['splitter_odp_id']) && $_POST['splitter_odp_id'] !== '0') ? $_POST['splitter_odp_id'] : null;
            
            // Handle monitoring fields - only for ONT (6) and Server (7)
            $ip_address = (!empty($_POST['ip_address'])) ? $_POST['ip_address'] : null;
            $upstream_interface_id = (!empty($_POST['upstream_interface_id']) && $_POST['upstream_interface_id'] !== '0') ? $_POST['upstream_interface_id'] : null;
            $port_http = (!empty($_POST['port_http'])) ? $_POST['port_http'] : 80;
            $port_https = (!empty($_POST['port_https'])) ? $_POST['port_https'] : 443;
            $monitoring_status = 'offline'; // Default status
            
            // Handle SNMP fields - for Server (7), OLT (1), Access Point (9), ONT (6)
            $snmp_enabled = (isset($_POST['snmp_enabled']) && $_POST['snmp_enabled'] == '1') ? 1 : 0;
            $snmp_version = (!empty($_POST['snmp_version'])) ? $_POST['snmp_version'] : '2c';
            $snmp_community = (!empty($_POST['snmp_community'])) ? $_POST['snmp_community'] : 'public';
            $snmp_port = (!empty($_POST['snmp_port'])) ? $_POST['snmp_port'] : 161;
            $snmp_username = (!empty($_POST['snmp_username'])) ? $_POST['snmp_username'] : null;
            $snmp_auth_protocol = (!empty($_POST['snmp_auth_protocol'])) ? $_POST['snmp_auth_protocol'] : null;
            $snmp_auth_password = (!empty($_POST['snmp_auth_password'])) ? $_POST['snmp_auth_password'] : null;
            $snmp_priv_protocol = (!empty($_POST['snmp_priv_protocol'])) ? $_POST['snmp_priv_protocol'] : null;
            $snmp_priv_password = (!empty($_POST['snmp_priv_password'])) ? $_POST['snmp_priv_password'] : null;
            
            // Handle ODC fields - for ODC Pole Mounted (4) and ODC Cabinet (12)
            $odc_type = (!empty($_POST['odc_type'])) ? $_POST['odc_type'] : 'pole_mounted';
            $odc_capacity = (!empty($_POST['odc_capacity'])) ? $_POST['odc_capacity'] : 32;
            $odc_ports_used = (!empty($_POST['odc_ports_used'])) ? $_POST['odc_ports_used'] : 0;
            $odc_installation_type = (!empty($_POST['odc_installation_type'])) ? $_POST['odc_installation_type'] : 'pole';
            $odc_main_splitter_ratio = (!empty($_POST['odc_main_splitter_ratio'])) ? $_POST['odc_main_splitter_ratio'] : '1:4';
            $odc_odp_splitter_ratio = (!empty($_POST['odc_odp_splitter_ratio'])) ? $_POST['odc_odp_splitter_ratio'] : '1:8';
            $odc_input_ports = (!empty($_POST['odc_input_ports'])) ? $_POST['odc_input_ports'] : 1;
            $odc_output_ports = (!empty($_POST['odc_output_ports'])) ? $_POST['odc_output_ports'] : 4;
            $odc_pon_connection = (!empty($_POST['odc_pon_connection'])) ? $_POST['odc_pon_connection'] : null;
            $odc_vlan_id = (!empty($_POST['odc_vlan_id'])) ? $_POST['odc_vlan_id'] : null;
            
            // Handle ODP fields - for ODP/Tiang ODP (3)
            $odp_type = (!empty($_POST['odp_type'])) ? $_POST['odp_type'] : 'pole_mounted';
            $odp_capacity = (!empty($_POST['odp_capacity'])) ? $_POST['odp_capacity'] : 16;
            $odp_ports_used = (!empty($_POST['odp_ports_used'])) ? $_POST['odp_ports_used'] : 0;
            $odp_splitter_ratio = (!empty($_POST['odp_splitter_ratio'])) ? $_POST['odp_splitter_ratio'] : '1:8';
            $odp_input_ports = (!empty($_POST['odp_input_ports'])) ? $_POST['odp_input_ports'] : 1;
            $odp_output_ports = (!empty($_POST['odp_output_ports'])) ? $_POST['odp_output_ports'] : 8;
            
            // ONT-specific fields
            $ont_connected_odp_id = (!empty($_POST['ont_connected_odp_id']) && $_POST['ont_connected_odp_id'] !== '0') ? $_POST['ont_connected_odp_id'] : null;
            $ont_connected_port = (!empty($_POST['ont_connected_port'])) ? $_POST['ont_connected_port'] : null;
            $ont_installation_type = (!empty($_POST['ont_installation_type'])) ? $_POST['ont_installation_type'] : 'indoor';
            $ont_model = (!empty($_POST['ont_model'])) ? $_POST['ont_model'] : null;
            $ont_serial_number = (!empty($_POST['ont_serial_number'])) ? $_POST['ont_serial_number'] : null;
            $ont_customer_name = (!empty($_POST['ont_customer_name'])) ? $_POST['ont_customer_name'] : null;
            $ont_customer_address = (!empty($_POST['ont_customer_address'])) ? $_POST['ont_customer_address'] : null;
            $ont_service_plan = (!empty($_POST['ont_service_plan'])) ? $_POST['ont_service_plan'] : null;
            $ont_connection_status = (!empty($_POST['ont_connection_status'])) ? $_POST['ont_connection_status'] : 'connected';

            
            // Handle VLAN data for Server (7)
            $vlan_config = null;
            if ($item_type_id == 7 && isset($_POST['vlan_ids']) && is_array($_POST['vlan_ids'])) {
                $vlans = [];
                $vlan_ids = $_POST['vlan_ids'];
                $vlan_ips = $_POST['vlan_ips'] ?? [];
                $vlan_descriptions = $_POST['vlan_descriptions'] ?? [];
                
                for ($i = 0; $i < count($vlan_ids); $i++) {
                    if (!empty($vlan_ids[$i]) || !empty($vlan_ips[$i]) || !empty($vlan_descriptions[$i])) {
                        $vlans[] = [
                            'vlan_id' => $vlan_ids[$i] ?? '',
                            'ip' => $vlan_ips[$i] ?? '',
                            'description' => $vlan_descriptions[$i] ?? ''
                        ];
                    }
                }
                
                if (!empty($vlans)) {
                    $vlan_config = json_encode($vlans);
                }
            }
            
            // Enhanced PON data handling for OLT (1) with interface integration
            $pon_config = null;
            $pon_interface_mapping = null;
            if ($item_type_id == 1 && isset($_POST['pon_ports']) && is_array($_POST['pon_ports'])) {
                $pons = [];
                $interface_mappings = [];
                $pon_ports = $_POST['pon_ports'];
                $pon_interfaces = $_POST['pon_interfaces'] ?? [];
                $pon_descriptions = $_POST['pon_descriptions'] ?? [];
                $pon_vlan_ids = $_POST['pon_vlan_ids'] ?? [];
                $pon_vlan_descriptions = $_POST['pon_vlan_descriptions'] ?? [];
                
                for ($i = 0; $i < count($pon_ports); $i++) {
                    if (!empty($pon_ports[$i]) || !empty($pon_descriptions[$i])) {
                        $pon_vlans = [];
                        
                        // Process VLANs for this PON
                        $pon_key = 'pon_' . ($i + 1);
                        if (isset($pon_vlan_ids[$pon_key]) && is_array($pon_vlan_ids[$pon_key])) {
                            for ($v = 0; $v < count($pon_vlan_ids[$pon_key]); $v++) {
                                $vlan_id = $pon_vlan_ids[$pon_key][$v] ?? '';
                                $vlan_desc = $pon_vlan_descriptions[$pon_key][$v] ?? '';
                                
                                if (!empty($vlan_id) || !empty($vlan_desc)) {
                                    $pon_vlans[] = [
                                        'vlan_id' => $vlan_id,
                                        'description' => $vlan_desc
                                    ];
                                }
                            }
                        }
                        
                        $pons[] = [
                            'port' => $pon_ports[$i] ?? '',
                            'interface_id' => $pon_interfaces[$i] ?? null,
                            'description' => $pon_descriptions[$i] ?? '',
                            'vlans' => $pon_vlans
                        ];
                        
                        // Build interface mapping
                        if (!empty($pon_ports[$i]) && !empty($pon_interfaces[$i])) {
                            $interface_mappings[] = [
                                'pon_port' => $pon_ports[$i],
                                'interface_id' => $pon_interfaces[$i]
                            ];
                        }
                    }
                }
                
                if (!empty($pons)) {
                    $pon_config = json_encode($pons);
                }
                if (!empty($interface_mappings)) {
                    $pon_interface_mapping = json_encode($interface_mappings);
                }
            }
            
            // Enhanced ODC data handling for ODC types (4, 12)
            $odc_pon_config = null;
            $odc_odp_config = null;
            if (($item_type_id == 4 || $item_type_id == 12)) {
                // Handle ODC PON connections
                if (isset($_POST['odc_pon_connections']) && is_array($_POST['odc_pon_connections'])) {
                    $odc_pons = [];
                    $odc_pon_connections = $_POST['odc_pon_connections'];
                    $odc_pon_vlans = $_POST['odc_pon_vlans'] ?? [];
                    $odc_pon_descriptions = $_POST['odc_pon_descriptions'] ?? [];
                    
                    for ($i = 0; $i < count($odc_pon_connections); $i++) {
                        if (!empty($odc_pon_connections[$i])) {
                            $odc_pons[] = [
                                'connection' => $odc_pon_connections[$i],
                                'vlan_id' => $odc_pon_vlans[$i] ?? '',
                                'description' => $odc_pon_descriptions[$i] ?? ''
                            ];
                        }
                    }
                    
                    if (!empty($odc_pons)) {
                        $odc_pon_config = json_encode($odc_pons);
                    }
                }
                
                // Handle ODC ODP outputs
                if (isset($_POST['odc_output_ports']) && is_array($_POST['odc_output_ports'])) {
                    $odc_odps = [];
                    $odc_output_ports = $_POST['odc_output_ports'];
                    $odc_odp_names = $_POST['odc_odp_names'] ?? [];
                    $odc_cable_lengths = $_POST['odc_cable_lengths'] ?? [];
                    $odc_attenuations = $_POST['odc_attenuations'] ?? [];
                    $odc_odp_descriptions = $_POST['odc_odp_descriptions'] ?? [];
                    
                    for ($i = 0; $i < count($odc_output_ports); $i++) {
                        if (!empty($odc_output_ports[$i])) {
                            $odc_odps[] = [
                                'output_port' => $odc_output_ports[$i],
                                'odp_name' => $odc_odp_names[$i] ?? '',
                                'cable_length' => $odc_cable_lengths[$i] ?? null,
                                'attenuation' => $odc_attenuations[$i] ?? null,
                                'description' => $odc_odp_descriptions[$i] ?? ''
                            ];
                        }
                    }
                    
                    if (!empty($odc_odps)) {
                        $odc_odp_config = json_encode($odc_odps);
                    }
                }
            }
            
            // Enhanced ODP data handling for ODP type (3)
            $odp_odc_config = null;
            $odp_ont_config = null;
            if ($item_type_id == 3) {
                // Handle ODP ODC connections
                if (isset($_POST['odp_odc_connections']) && is_array($_POST['odp_odc_connections'])) {
                    $odp_odcs = [];
                    $odp_odc_connections = $_POST['odp_odc_connections'];
                    $odp_input_ports_num = $_POST['odp_input_ports_num'] ?? [];
                    $odp_cable_lengths = $_POST['odp_cable_lengths'] ?? [];
                    $odp_attenuations = $_POST['odp_attenuations'] ?? [];
                    $odp_connection_descriptions = $_POST['odp_connection_descriptions'] ?? [];
                    
                    for ($i = 0; $i < count($odp_odc_connections); $i++) {
                        if (!empty($odp_odc_connections[$i])) {
                            $odp_odcs[] = [
                                'odc_connection' => $odp_odc_connections[$i],
                                'input_port' => $odp_input_ports_num[$i] ?? 1,
                                'cable_length' => $odp_cable_lengths[$i] ?? null,
                                'attenuation' => $odp_attenuations[$i] ?? null,
                                'description' => $odp_connection_descriptions[$i] ?? ''
                            ];
                        }
                    }
                    
                    if (!empty($odp_odcs)) {
                        $odp_odc_config = json_encode($odp_odcs);
                    }
                }
                
                // Handle ODP ONT ports
                if (isset($_POST['ont_serials']) && is_array($_POST['ont_serials'])) {
                    $odp_onts = [];
                    $ont_serials = $_POST['ont_serials'];
                    $customer_infos = $_POST['customer_infos'] ?? [];
                    $port_statuses = $_POST['port_statuses'] ?? [];
                    
                    for ($i = 0; $i < count($ont_serials); $i++) {
                        $port_number = $i + 1;
                        $odp_onts[] = [
                            'port_number' => $port_number,
                            'ont_serial' => $ont_serials[$i] ?? '',
                            'customer_info' => $customer_infos[$i] ?? '',
                            'status' => $port_statuses[$i] ?? 'available'
                        ];
                    }
                    
                    if (!empty($odp_onts)) {
                        $odp_ont_config = json_encode($odp_onts);
                    }
                }
                
                // Combine ODP configurations into pon_config for proper saving
                $pon_config_data = [];
                if ($odp_odc_config) {
                    $pon_config_data['odp_odc_connections'] = json_decode($odp_odc_config, true);
                }
                if ($odp_ont_config) {
                    $pon_config_data['odp_ont_mappings'] = json_decode($odp_ont_config, true);
                }
                
                // Set pon_config for ODP if we have data
                if (!empty($pon_config_data)) {
                    $pon_config = json_encode($pon_config_data);
                }
            }
            
            $status = $_POST['status'] ?? 'active';
            $attenuation_notes = $_POST['attenuation_notes'] ?? null;
            
            if (!$item_type_id || !$name || !$latitude || !$longitude) {
                $response['message'] = 'Required fields missing';
                break;
            }
            
            $query = "INSERT INTO ftth_items (item_type_id, item_type, item_price, name, description, latitude, longitude, address, tube_color_id, core_used, core_color_id, item_cable_type, total_core_capacity, splitter_main_id, splitter_odp_id, ip_address, upstream_interface_id, port_http, port_https, monitoring_status, vlan_config, pon_config, attenuation_notes, status, snmp_enabled, snmp_version, snmp_community, snmp_port, snmp_username, snmp_auth_protocol, snmp_auth_password, snmp_priv_protocol, snmp_priv_password, odc_type, odc_capacity, odc_ports_used, odc_installation_type, odc_main_splitter_ratio, odc_odp_splitter_ratio, odc_input_ports, odc_output_ports, odc_pon_connection, odc_vlan_id, odp_type, odp_capacity, odp_ports_used, odp_splitter_ratio, odp_input_ports, odp_output_ports, ont_connected_odp_id, ont_connected_port, ont_installation_type, ont_model, ont_serial_number, ont_customer_name, ont_customer_address, ont_service_plan, ont_connection_status) 
                     VALUES (:item_type_id, :item_type, :item_price, :name, :description, :latitude, :longitude, :address, :tube_color_id, :core_used, :core_color_id, :item_cable_type, :total_core_capacity, :splitter_main_id, :splitter_odp_id, :ip_address, :upstream_interface_id, :port_http, :port_https, :monitoring_status, :vlan_config, :pon_config, :attenuation_notes, :status, :snmp_enabled, :snmp_version, :snmp_community, :snmp_port, :snmp_username, :snmp_auth_protocol, :snmp_auth_password, :snmp_priv_protocol, :snmp_priv_password, :odc_type, :odc_capacity, :odc_ports_used, :odc_installation_type, :odc_main_splitter_ratio, :odc_odp_splitter_ratio, :odc_input_ports, :odc_output_ports, :odc_pon_connection, :odc_vlan_id, :odp_type, :odp_capacity, :odp_ports_used, :odp_splitter_ratio, :odp_input_ports, :odp_output_ports, :ont_connected_odp_id, :ont_connected_port, :ont_installation_type, :ont_model, :ont_serial_number, :ont_customer_name, :ont_customer_address, :ont_service_plan, :ont_connection_status)";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':item_type_id', $item_type_id);
            $stmt->bindParam(':item_type', $item_type);
            $stmt->bindParam(':item_price', $item_price);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':latitude', $latitude);
            $stmt->bindParam(':longitude', $longitude);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':tube_color_id', $tube_color_id);
            $stmt->bindParam(':core_used', $core_used);
            $stmt->bindParam(':core_color_id', $core_color_id);
            $stmt->bindParam(':item_cable_type', $item_cable_type);
            $stmt->bindParam(':total_core_capacity', $total_core_capacity);
            $stmt->bindParam(':splitter_main_id', $splitter_main_id);
            $stmt->bindParam(':splitter_odp_id', $splitter_odp_id);
            $stmt->bindParam(':ip_address', $ip_address);
            $stmt->bindParam(':upstream_interface_id', $upstream_interface_id);
            $stmt->bindParam(':port_http', $port_http);
            $stmt->bindParam(':port_https', $port_https);
            $stmt->bindParam(':monitoring_status', $monitoring_status);
            $stmt->bindParam(':vlan_config', $vlan_config);
            $stmt->bindParam(':pon_config', $pon_config);
            $stmt->bindParam(':attenuation_notes', $attenuation_notes);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':snmp_enabled', $snmp_enabled);
            $stmt->bindParam(':snmp_version', $snmp_version);
            $stmt->bindParam(':snmp_community', $snmp_community);
            $stmt->bindParam(':snmp_port', $snmp_port);
            $stmt->bindParam(':snmp_username', $snmp_username);
            $stmt->bindParam(':snmp_auth_protocol', $snmp_auth_protocol);
            $stmt->bindParam(':snmp_auth_password', $snmp_auth_password);
            $stmt->bindParam(':snmp_priv_protocol', $snmp_priv_protocol);
            $stmt->bindParam(':snmp_priv_password', $snmp_priv_password);
            $stmt->bindParam(':odc_type', $odc_type);
            $stmt->bindParam(':odc_capacity', $odc_capacity);
            $stmt->bindParam(':odc_ports_used', $odc_ports_used);
            $stmt->bindParam(':odc_installation_type', $odc_installation_type);
            $stmt->bindParam(':odc_main_splitter_ratio', $odc_main_splitter_ratio);
            $stmt->bindParam(':odc_odp_splitter_ratio', $odc_odp_splitter_ratio);
            $stmt->bindParam(':odc_input_ports', $odc_input_ports);
            $stmt->bindParam(':odc_output_ports', $odc_output_ports);
            $stmt->bindParam(':odc_pon_connection', $odc_pon_connection);
            $stmt->bindParam(':odc_vlan_id', $odc_vlan_id);
            $stmt->bindParam(':odp_type', $odp_type);
            $stmt->bindParam(':odp_capacity', $odp_capacity);
            $stmt->bindParam(':odp_ports_used', $odp_ports_used);
            $stmt->bindParam(':odp_splitter_ratio', $odp_splitter_ratio);
            $stmt->bindParam(':odp_input_ports', $odp_input_ports);
            $stmt->bindParam(':odp_output_ports', $odp_output_ports);
            $stmt->bindParam(':ont_connected_odp_id', $ont_connected_odp_id);
            $stmt->bindParam(':ont_connected_port', $ont_connected_port);
            $stmt->bindParam(':ont_installation_type', $ont_installation_type);
            $stmt->bindParam(':ont_model', $ont_model);
            $stmt->bindParam(':ont_serial_number', $ont_serial_number);
            $stmt->bindParam(':ont_customer_name', $ont_customer_name);
            $stmt->bindParam(':ont_customer_address', $ont_customer_address);
            $stmt->bindParam(':ont_service_plan', $ont_service_plan);
            $stmt->bindParam(':ont_connection_status', $ont_connection_status);

            
            if ($stmt->execute()) {
                $item_id = $db->lastInsertId();
                
                // Get the created item with joins
                $query = "SELECT i.*, it.name as item_type_name, it.icon, it.color,
                                tc.color_name as tube_color_name, tc.hex_code,
                                cc.color_name as core_color_name, cc.hex_code as core_hex_code,
                                sm.ratio as splitter_main_ratio,
                                so.ratio as splitter_odp_ratio
                         FROM ftth_items i
                         LEFT JOIN item_types it ON i.item_type_id = it.id
                         LEFT JOIN tube_colors tc ON i.tube_color_id = tc.id
                         LEFT JOIN tube_colors cc ON i.core_color_id = cc.id
                         LEFT JOIN splitter_types sm ON i.splitter_main_id = sm.id
                         LEFT JOIN splitter_types so ON i.splitter_odp_id = so.id
                         WHERE i.id = :id";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $item_id);
                $stmt->execute();
                
                $response['success'] = true;
                $response['message'] = 'Item created successfully';
                $response['data'] = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $response['message'] = 'Failed to create item';
            }
            break;
            
        case 'PUT':
            // Check admin permission for update operations
            if (!isAdmin()) {
                http_response_code(403);
                echo json_encode(array('success' => false, 'message' => 'Admin permission required for update operations'));
                exit();
            }
            
            // Update item - now $_POST should be properly populated
            $put_data = $_POST;
            
            // DEBUG: Log all PUT data for debugging upstream interface issue
            error_log("🔧 API DEBUG - PUT data received: " . json_encode($put_data));
            if (isset($put_data['upstream_interface_id'])) {
                error_log("🔌 Upstream interface ID found in PUT: " . $put_data['upstream_interface_id']);
            } else {
                error_log("❌ Upstream interface ID NOT found in PUT data");
            }
            
            $id = $put_data['id'] ?? null;
            
            if (!$id) {
                $response['message'] = 'ID required for update';
                break;
            }
            
            // Build dynamic update query
            $update_fields = array();
            $params = array(':id' => $id);
            
            $allowed_fields = ['item_type', 'item_price', 'name', 'description', 'latitude', 'longitude', 'address', 'tube_color_id', 'core_used', 'core_color_id', 'item_cable_type', 'total_core_capacity', 'splitter_main_id', 'splitter_odp_id', 'ip_address', 'upstream_interface_id', 'port_http', 'port_https', 'monitoring_status', 'vlan_config', 'pon_config', 'attenuation_notes', 'status', 'snmp_enabled', 'snmp_version', 'snmp_community', 'snmp_port', 'snmp_username', 'snmp_auth_protocol', 'snmp_auth_password', 'snmp_priv_protocol', 'snmp_priv_password', 'odc_type', 'odc_capacity', 'odc_ports_used', 'odc_installation_type', 'odc_main_splitter_ratio', 'odc_odp_splitter_ratio', 'odc_input_ports', 'odc_output_ports', 'odc_pon_connection', 'odc_vlan_id', 'odp_type', 'odp_capacity', 'odp_ports_used', 'odp_splitter_ratio', 'odp_input_ports', 'odp_output_ports', 'ont_connected_odp_id', 'ont_connected_port', 'ont_installation_type', 'ont_model', 'ont_serial_number', 'ont_customer_name', 'ont_customer_address', 'ont_service_plan', 'ont_connection_status'];
            
            // Handle VLAN data for Server (7) in UPDATE
            if (isset($put_data['vlan_ids']) && is_array($put_data['vlan_ids'])) {
                $vlans = [];
                $vlan_ids = $put_data['vlan_ids'];
                $vlan_ips = $put_data['vlan_ips'] ?? [];
                $vlan_descriptions = $put_data['vlan_descriptions'] ?? [];
                
                for ($i = 0; $i < count($vlan_ids); $i++) {
                    if (!empty($vlan_ids[$i]) || !empty($vlan_ips[$i]) || !empty($vlan_descriptions[$i])) {
                        $vlans[] = [
                            'vlan_id' => $vlan_ids[$i] ?? '',
                            'ip' => $vlan_ips[$i] ?? '',
                            'description' => $vlan_descriptions[$i] ?? ''
                        ];
                    }
                }
                
                $put_data['vlan_config'] = !empty($vlans) ? json_encode($vlans) : null;
            }
            
            // Handle PON data for OLT (1) in UPDATE
            if (isset($put_data['pon_ports']) && is_array($put_data['pon_ports'])) {
                $pons = [];
                $pon_ports = $put_data['pon_ports'];
                $pon_descriptions = $put_data['pon_descriptions'] ?? [];
                $pon_vlan_ids = $put_data['pon_vlan_ids'] ?? [];
                $pon_vlan_descriptions = $put_data['pon_vlan_descriptions'] ?? [];
                
                for ($i = 0; $i < count($pon_ports); $i++) {
                    if (!empty($pon_ports[$i]) || !empty($pon_descriptions[$i])) {
                        $pon_vlans = [];
                        
                        // Process VLANs for this PON
                        $pon_key = 'pon_' . ($i + 1);
                        if (isset($pon_vlan_ids[$pon_key]) && is_array($pon_vlan_ids[$pon_key])) {
                            for ($v = 0; $v < count($pon_vlan_ids[$pon_key]); $v++) {
                                $vlan_id = $pon_vlan_ids[$pon_key][$v] ?? '';
                                $vlan_desc = $pon_vlan_descriptions[$pon_key][$v] ?? '';
                                
                                if (!empty($vlan_id) || !empty($vlan_desc)) {
                                    $pon_vlans[] = [
                                        'vlan_id' => $vlan_id,
                                        'description' => $vlan_desc
                                    ];
                                }
                            }
                        }
                        
                        $pons[] = [
                            'port' => $pon_ports[$i] ?? '',
                            'description' => $pon_descriptions[$i] ?? '',
                            'vlans' => $pon_vlans
                        ];
                    }
                }
                
                $put_data['pon_config'] = !empty($pons) ? json_encode($pons) : null;
            }
            
            // Handle ODP data for UPDATE (Tiang ODP - item_type_id 3)
            if (isset($put_data['odp_odc_connections']) && is_array($put_data['odp_odc_connections'])) {
                $odp_odc_config_data = [];
                $odp_odc_connections = $put_data['odp_odc_connections'];
                $odp_cable_lengths = $put_data['odp_cable_lengths'] ?? [];
                $odp_attenuations = $put_data['odp_attenuations'] ?? [];
                $odp_connection_descriptions = $put_data['odp_connection_descriptions'] ?? [];
                
                for ($i = 0; $i < count($odp_odc_connections); $i++) {
                    if (!empty($odp_odc_connections[$i])) {
                        $odp_odc_config_data[] = [
                            'odc_connection' => $odp_odc_connections[$i],
                            'cable_length' => $odp_cable_lengths[$i] ?? null,
                            'attenuation' => $odp_attenuations[$i] ?? null,
                            'description' => $odp_connection_descriptions[$i] ?? ''
                        ];
                    }
                }
                
                // Handle ODP ONT mappings for UPDATE
                $odp_ont_config_data = [];
                if (isset($put_data['ont_serials']) && is_array($put_data['ont_serials'])) {
                    $ont_serials = $put_data['ont_serials'];
                    $customer_infos = $put_data['customer_infos'] ?? [];
                    $port_statuses = $put_data['port_statuses'] ?? [];
                    
                    for ($i = 0; $i < count($ont_serials); $i++) {
                        $port_number = $i + 1;
                        $odp_ont_config_data[] = [
                            'port_number' => $port_number,
                            'ont_serial' => $ont_serials[$i] ?? '',
                            'customer_info' => $customer_infos[$i] ?? '',
                            'status' => $port_statuses[$i] ?? 'available'
                        ];
                    }
                }
                
                // Combine ODP configurations into pon_config for UPDATE
                $pon_config_data = [];
                if (!empty($odp_odc_config_data)) {
                    $pon_config_data['odp_odc_connections'] = $odp_odc_config_data;
                }
                if (!empty($odp_ont_config_data)) {
                    $pon_config_data['odp_ont_mappings'] = $odp_ont_config_data;
                }
                
                // Set pon_config for ODP UPDATE if we have data
                if (!empty($pon_config_data)) {
                    $put_data['pon_config'] = json_encode($pon_config_data);
                }
            }
            
            // Handle SNMP fields processing in UPDATE
            if (isset($put_data['snmp_enabled'])) {
                $put_data['snmp_enabled'] = ($put_data['snmp_enabled'] == '1' || $put_data['snmp_enabled'] === 1) ? 1 : 0;
            }
            
            // Set default values for SNMP fields if not provided
            if (isset($put_data['snmp_enabled']) && $put_data['snmp_enabled'] == 1) {
                if (!isset($put_data['snmp_version']) || empty($put_data['snmp_version'])) {
                    $put_data['snmp_version'] = '2c';
                }
                if (!isset($put_data['snmp_community']) || empty($put_data['snmp_community'])) {
                    $put_data['snmp_community'] = 'public';
                }
                if (!isset($put_data['snmp_port']) || empty($put_data['snmp_port'])) {
                    $put_data['snmp_port'] = 161;
                }
            }
            
            foreach ($allowed_fields as $field) {
                if (isset($put_data[$field])) {
                    $db_field = $field === 'item_type' ? 'item_type_id' : $field;
                    $update_fields[] = "$db_field = :$field";
                    
                    // Handle empty values for foreign key fields - convert to NULL
                    $value = $put_data[$field];
                    if (in_array($field, ['tube_color_id', 'core_color_id', 'splitter_main_id', 'splitter_odp_id', 'upstream_interface_id', 'ont_connected_odp_id', 'odp_parent_odc_id', 'auto_generated_by_route_id']) && 
                        ($value === '' || $value === '0' || $value === 0)) {
                        $value = null;
                    }
                    
                    $params[":$field"] = $value;
                }
            }
            
            if (empty($update_fields)) {
                $response['message'] = 'No fields to update';
                break;
            }
            
            $query = "UPDATE ftth_items SET " . implode(', ', $update_fields) . " WHERE id = :id";
            
            $stmt = $db->prepare($query);
            
            if ($stmt->execute($params)) {
                // Get updated item
                $query = "SELECT i.*, it.name as item_type_name, it.icon, it.color,
                                tc.color_name as tube_color_name, tc.hex_code,
                                cc.color_name as core_color_name, cc.hex_code as core_hex_code,
                                sm.ratio as splitter_main_ratio,
                                so.ratio as splitter_odp_ratio
                         FROM ftth_items i
                         LEFT JOIN item_types it ON i.item_type_id = it.id
                         LEFT JOIN tube_colors tc ON i.tube_color_id = tc.id
                         LEFT JOIN tube_colors cc ON i.core_color_id = cc.id
                         LEFT JOIN splitter_types sm ON i.splitter_main_id = sm.id
                         LEFT JOIN splitter_types so ON i.splitter_odp_id = so.id
                         WHERE i.id = :id";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $id);
                $stmt->execute();
                
                $response['success'] = true;
                $response['message'] = 'Item updated successfully';
                $response['data'] = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $response['message'] = 'Failed to update item';
            }
            break;
            
        case 'DELETE':
            // Check admin permission for delete operations
            if (!isAdmin()) {
                http_response_code(403);
                echo json_encode(array('success' => false, 'message' => 'Admin permission required for delete operations'));
                exit();
            }
            
            // Delete item
            $input = file_get_contents("php://input");
            $delete_data = array();
            
            // Try to parse JSON first, then form data
            $json_data = json_decode($input, true);
            if ($json_data) {
                $delete_data = $json_data;
            } else {
                parse_str($input, $delete_data);
            }
            
            // Also check for regular POST data (for compatibility)
            if (empty($delete_data)) {
                $delete_data = $_POST;
            }
            
            $id = $delete_data['id'] ?? null;
            
            if (!$id) {
                $response['message'] = 'ID required for deletion';
                break;
            }
            
            // Delete related routes first
            $query = "DELETE FROM cable_routes WHERE from_item_id = :id OR to_item_id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            // Delete the item
            $query = "DELETE FROM ftth_items WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $id);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Item deleted successfully';
            } else {
                $response['message'] = 'Failed to delete item';
            }
            break;
            
        default:
            $response['message'] = 'Method not allowed';
            break;
    }
    
} catch (Exception $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
}

echo json_encode($response);
?>