<?php
// SNMP Monitoring API untuk FTTHNMS
// Mendukung Server, OLT, Access Point, dan ONT monitoring via SNMP

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
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

// ===== FIX SNMP OUTPUT BUFFER ISSUE =====
// Start output buffering to capture any SNMP warnings
ob_start();

// Set SNMP configuration to suppress warnings
ini_set('snmp.hide_warnings', '1');
putenv('MIBDIRS=' . __DIR__ . '\\..\\usr\\share\\snmp\\mibs');
putenv('MIBS=ALL');
putenv('SNMPCONFPATH=' . __DIR__ . '\\..\\usr\\share\\snmp');
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);


$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$response = array('success' => false, 'message' => '', 'data' => null);

try {
    // Check authentication for all requests
    if (!checkPermission()) {
        http_response_code(401);
        echo json_encode(array('success' => false, 'message' => 'Authentication required'));
        exit();
    }

    // Check if SNMP extension is loaded
    if (!extension_loaded('snmp')) {
        http_response_code(500);
        echo json_encode(array(
            'success' => false, 
            'message' => 'SNMP extension not loaded. Please install php-snmp extension.',
            'install_guide' => 'Run: sudo apt-get install php-snmp (Linux) or enable extension=snmp in php.ini (Windows)'
        ));
        exit();
    }

    

    switch($method) {
        case 'GET':
            $action = $_GET['action'] ?? '';
            
            if ($action === 'test') {
                // Test SNMP connection to specific device
                $item_id = $_GET['id'] ?? null;
                if (!$item_id) {
                    $response['message'] = 'Item ID required';
                    break;
                }
                
                $result = testSNMPConnection($db, $item_id);
                $response['success'] = true;
                $response['data'] = $result;
                
            } elseif ($action === 'metrics') {
                // Get latest SNMP metrics for item(s)
                $item_id = $_GET['id'] ?? null;
                $limit = $_GET['limit'] ?? 10;
                
                if ($item_id) {
                    // Get metrics for specific item
                    $query = "SELECT * FROM snmp_metrics 
                             WHERE item_id = :item_id 
                             ORDER BY metric_time DESC 
                             LIMIT :limit";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':item_id', $item_id);
                    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
                } else {
                    // Get latest metrics for all items
                    $query = "SELECT * FROM latest_snmp_metrics 
                             ORDER BY metric_time DESC 
                             LIMIT :limit";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
                }
                
                $stmt->execute();
                $metrics = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $response['success'] = true;
                $response['data'] = $metrics;
                
            } elseif ($action === 'status') {
                // Get SNMP monitoring status for all enabled devices with enhanced query optimization
                $query = "SELECT fi.id, fi.name, fi.ip_address, fi.snmp_enabled, fi.snmp_community, 
                                fi.snmp_port, it.name as item_type, sm.metric_time as last_snmp_time,
                                sm.device_name, sm.device_description, sm.device_contact, sm.device_location,
                                sm.device_uptime, sm.cpu_usage_percent, sm.memory_usage_percent, 
                                sm.memory_total_mb, sm.memory_used_mb, sm.interface_status,
                                sm.interface_speed_mbps, sm.bytes_in_total, sm.bytes_out_total,
                                sm.optical_power_tx_dbm, sm.optical_power_rx_dbm,
                                CASE 
                                    WHEN sm.metric_time IS NULL THEN 'no_data'
                                    WHEN (sm.cpu_usage_percent > 80 OR sm.memory_usage_percent > 80 OR sm.interface_status = 'down') THEN 'warning'
                                    WHEN sm.metric_time >= DATE_SUB(NOW(), INTERVAL 10 MINUTE) THEN 'online'
                                    ELSE 'stale_data'
                                END as device_status,
                                TIMESTAMPDIFF(MINUTE, sm.metric_time, NOW()) as minutes_since_update
                         FROM ftth_items fi
                         INNER JOIN item_types it ON fi.item_type_id = it.id
                         LEFT JOIN (
                             SELECT sm1.* FROM snmp_metrics sm1
                             INNER JOIN (
                                 SELECT item_id, MAX(metric_time) as latest_time
                                 FROM snmp_metrics
                                 GROUP BY item_id
                             ) sm2 ON sm1.item_id = sm2.item_id AND sm1.metric_time = sm2.latest_time
                         ) sm ON fi.id = sm.item_id
                         WHERE fi.snmp_enabled = 1 AND fi.ip_address IS NOT NULL AND fi.ip_address != ''
                         ORDER BY 
                             CASE 
                                 WHEN sm.metric_time IS NULL THEN 3
                                 WHEN sm.metric_time >= DATE_SUB(NOW(), INTERVAL 10 MINUTE) THEN 1
                                 ELSE 2
                             END,
                             fi.name ASC";
                
                $stmt = $db->prepare($query);
                $stmt->execute();
                
                $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Add statistical summary
                $stats = [
                    'total_devices' => count($devices),
                    'online' => 0,
                    'warning' => 0,
                    'offline' => 0,
                    'stale_data' => 0,
                    'last_updated' => date('Y-m-d H:i:s')
                ];
                
                foreach ($devices as $device) {
                    $stats[$device['device_status']]++;
                }
                
                $response['success'] = true;
                $response['data'] = $devices;
                $response['stats'] = $stats;
                
            } elseif ($action === 'oids') {
                // Get available OID mappings
                $device_type = $_GET['device_type'] ?? '';
                $vendor = $_GET['vendor'] ?? '';
                
                $query = "SELECT * FROM snmp_oid_mapping WHERE is_active = 1";
                $params = array();
                
                if ($device_type) {
                    $query .= " AND device_type = :device_type";
                    $params[':device_type'] = $device_type;
                }
                
                if ($vendor) {
                    $query .= " AND vendor = :vendor";
                    $params[':vendor'] = $vendor;
                }
                
                $query .= " ORDER BY device_type, vendor, oid_name";
                
                $stmt = $db->prepare($query);
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value);
                }
                $stmt->execute();
                
                $oids = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $response['success'] = true;
                $response['data'] = $oids;
                
            } else {
                $response['message'] = 'Invalid action';
            }
            break;
            
        case 'POST':
            $action = $_POST['action'] ?? '';
            
            if ($action === 'collect') {
                // Collect SNMP metrics for specific device
                $item_id = $_POST['item_id'] ?? null;
                if (!$item_id) {
                    $response['message'] = 'Item ID required';
                    break;
                }
                
                $result = collectSNMPMetrics($db, $item_id);
                $response['success'] = true;
                $response['data'] = $result;
                
            } elseif ($action === 'collect_all') {
                // Collect SNMP metrics for all enabled devices
                $results = collectAllSNMPMetrics($db);
                $response['success'] = true;
                $response['data'] = $results;
                
            } elseif ($action === 'configure') {
                // Configure SNMP settings for device (admin only)
                if (!isAdmin()) {
                    http_response_code(403);
                    echo json_encode(array('success' => false, 'message' => 'Admin permission required'));
                    exit();
                }
                
                $item_id = $_POST['item_id'] ?? null;
                $snmp_config = array(
                    'snmp_enabled' => $_POST['snmp_enabled'] ?? 0,
                    'snmp_version' => $_POST['snmp_version'] ?? '2c',
                    'snmp_community' => $_POST['snmp_community'] ?? 'public',
                    'snmp_port' => $_POST['snmp_port'] ?? 161,
                    'snmp_username' => $_POST['snmp_username'] ?? null,
                    'snmp_auth_protocol' => $_POST['snmp_auth_protocol'] ?? null,
                    'snmp_auth_password' => $_POST['snmp_auth_password'] ?? null,
                    'snmp_priv_protocol' => $_POST['snmp_priv_protocol'] ?? null,
                    'snmp_priv_password' => $_POST['snmp_priv_password'] ?? null
                );
                
                $result = configureSNMP($db, $item_id, $snmp_config);
                $response['success'] = $result['success'];
                $response['message'] = $result['message'];
                
            } else {
                $response['message'] = 'Invalid action';
            }
            break;
            
        default:
            $response['message'] = 'Method not allowed';
            break;
    }
    
} catch (Exception $e) {
    $response['message'] = 'Server error: ' . $e->getMessage();
    error_log("SNMP API Error: " . $e->getMessage());
}


// Clean output buffer before sending JSON
ob_clean();
header('Content-Type: application/json; charset=utf-8');
echo json_encode($response);

// SNMP Helper Functions

function testSNMPConnection($db, $item_id) {
    // Get device details
    $query = "SELECT * FROM ftth_items WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $item_id);
    $stmt->execute();
    
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$item || !$item['ip_address']) {
        return array('success' => false, 'message' => 'Item not found or no IP address');
    }
    
    if (!$item['snmp_enabled']) {
        return array('success' => false, 'message' => 'SNMP not enabled for this device');
    }
    
    $ip = $item['ip_address'];
    $community = $item['snmp_community'] ?: 'public';
    $port = $item['snmp_port'] ?: 161;
    $version = $item['snmp_version'] ?: '2c';
    
    try {
        // Set SNMP timeout and retry parameters
        snmp_set_valueretrieval(SNMP_VALUE_PLAIN);
        snmp_set_quick_print(true);
        snmp_set_oid_output_format(SNMP_OID_OUTPUT_NUMERIC);
        
        // Test basic SNMP connectivity with multiple OIDs as fallback
        $test_oids = [
            'sysDescr' => '1.3.6.1.2.1.1.1.0',     // System Description  
            'sysName' => '1.3.6.1.2.1.1.5.0',      // System Name
            'sysUpTime' => '1.3.6.1.2.1.1.3.0'     // System Uptime
        ];
        
        $success_count = 0;
        $results = array();
        $last_error = '';
        
        foreach ($test_oids as $name => $oid) {
            try {
                if ($version === '1') {
                    $result = @snmpget($ip . ':' . $port, $community, $oid, 5000000, 2);
                } elseif ($version === '2c') {
                    $result = @snmp2_get($ip . ':' . $port, $community, $oid, 5000000, 2);
                } else {
                    return array('success' => false, 'message' => 'SNMPv3 not implemented yet');
                }
                
                if ($result !== false && $result !== "") {
                    $success_count++;
                    $results[$name] = $result;
                    
                    // If we get at least one successful response, consider it working
                    if ($success_count >= 1) {
                        break;
                    }
                } else {
                    $last_error = error_get_last()['message'] ?? 'No response';
                }
            } catch (Exception $e) {
                $last_error = $e->getMessage();
                continue;
            }
        }
        
        if ($success_count > 0) {
            return array(
                'success' => true, 
                'message' => 'SNMP connection successful',
                'results' => $results,
                'version' => $version,
                'community' => $community,
                'port' => $port,
                'tests_passed' => $success_count . '/' . count($test_oids)
            );
        } else {
            return array(
                'success' => false, 
                'message' => 'SNMP connection failed: ' . $last_error,
                'debug_info' => [
                    'ip' => $ip,
                    'port' => $port,
                    'community' => $community,
                    'version' => $version,
                    'tested_oids' => array_keys($test_oids)
                ]
            );
        }
        
    } catch (Exception $e) {
        return array(
            'success' => false, 
            'message' => 'SNMP error: ' . $e->getMessage(),
            'debug_info' => [
                'ip' => $ip,
                'port' => $port,
                'community' => $community,
                'version' => $version
            ]
        );
    }
}

function collectSNMPMetrics($db, $item_id) {
    $start_time = microtime(true);
    
    // Get device details and OID mappings with optimized query
    $query = "SELECT fi.*, it.name as item_type_name,
                     COUNT(som.id) as oid_count
             FROM ftth_items fi 
             INNER JOIN item_types it ON fi.item_type_id = it.id 
             LEFT JOIN snmp_oid_mapping som ON (LOWER(som.device_type) = LOWER(it.name) OR som.device_type = 'universal') AND som.is_active = 1
             WHERE fi.id = :id AND fi.snmp_enabled = 1
             GROUP BY fi.id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $item_id);
    $stmt->execute();
    
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$item) {
        return array('success' => false, 'message' => 'Item not found or SNMP not enabled');
    }
    
    // Validate device has IP address
    if (empty($item['ip_address'])) {
        return array('success' => false, 'message' => 'Device has no IP address configured');
    }
    
    // Log collection attempt
    error_log("SNMP Collection started for device: {$item['name']} ({$item['ip_address']}) - OIDs available: {$item['oid_count']}");
    
    // Get relevant OID mappings for this device type
    $device_type = strtolower($item['item_type_name']);
    $oid_query = "SELECT * FROM snmp_oid_mapping 
                  WHERE (LOWER(device_type) = :device_type OR device_type = 'universal' OR device_type = 'server') 
                  AND is_active = 1 
                  ORDER BY 
                      CASE 
                          WHEN LOWER(device_type) = :device_type THEN 1
                          WHEN device_type = 'server' THEN 2
                          WHEN device_type = 'universal' THEN 3
                          ELSE 4
                      END,
                      oid_name";
    $oid_stmt = $db->prepare($oid_query);
    $oid_stmt->bindParam(':device_type', $device_type);
    $oid_stmt->execute();
    $oids = $oid_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no specific OIDs found, fall back to basic universal OIDs
    if (empty($oids)) {
        $oid_query = "SELECT * FROM snmp_oid_mapping 
                      WHERE device_type = 'universal' AND is_active = 1 
                      ORDER BY oid_name LIMIT 10";
        $oid_stmt = $db->prepare($oid_query);
        $oid_stmt->execute();
        $oids = $oid_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    $ip = $item['ip_address'];
    $community = $item['snmp_community'] ?: 'public';
    $port = $item['snmp_port'] ?: 161;
    $version = $item['snmp_version'] ?: '2c';
    
    $metrics = array();
    $errors = array();
    
    // Set SNMP configuration for better reliability
    snmp_set_valueretrieval(SNMP_VALUE_PLAIN);
    snmp_set_quick_print(true);
    snmp_set_oid_output_format(SNMP_OID_OUTPUT_NUMERIC);
    
    // Define essential MikroTik OIDs for immediate testing
    $essential_oids = [
        'system_name' => '1.3.6.1.2.1.1.5.0',
        'system_description' => '1.3.6.1.2.1.1.1.0',
        'system_uptime' => '1.3.6.1.2.1.1.3.0',
        'system_contact' => '1.3.6.1.2.1.1.4.0',
        'system_location' => '1.3.6.1.2.1.1.6.0',
        // MikroTik specific OIDs
        'cpu_usage' => '1.3.6.1.2.1.25.3.3.1.2.1', // CPU Load
        'memory_total' => '1.3.6.1.4.1.14988.1.1.1.1.1.0', // Total Memory
        'memory_free' => '1.3.6.1.4.1.14988.1.1.1.1.2.0', // Free Memory
        'interface_status' => '1.3.6.1.2.1.2.2.1.8.1', // Interface Status
        'interface_speed' => '1.3.6.1.2.1.2.2.1.5.1', // Interface Speed
        'bytes_in' => '1.3.6.1.2.1.2.2.1.10.1', // Bytes In
        'bytes_out' => '1.3.6.1.2.1.2.2.1.16.1', // Bytes Out
    ];
    
    try {
        // First try essential OIDs for immediate data
        foreach ($essential_oids as $oid_name => $oid) {
            try {
                $target = $ip . ':' . $port;
                
                if ($version === '1') {
                    $result = @snmpget($target, $community, $oid, 5000000, 3);
                } elseif ($version === '2c') {
                    $result = @snmp2_get($target, $community, $oid, 5000000, 3);
                }
                
                if ($result !== false && $result !== '') {
                    // Clean the result (remove type prefix like "STRING: ")
                    $clean_result = preg_replace('/^[A-Z-]+:\s*/', '', $result);
                    $clean_result = trim($clean_result, '"');
                    
                    // Convert specific values
                    if ($oid_name === 'cpu_usage' && is_numeric($clean_result)) {
                        $clean_result = floatval($clean_result);
                    } elseif (($oid_name === 'memory_total' || $oid_name === 'memory_free') && is_numeric($clean_result)) {
                        $clean_result = floatval($clean_result);
                    } elseif ($oid_name === 'interface_status' && is_numeric($clean_result)) {
                        $clean_result = ($clean_result == 1) ? 'up' : 'down';
                    } elseif ($oid_name === 'interface_speed' && is_numeric($clean_result)) {
                        $clean_result = floatval($clean_result);
                    }
                    
                    $metrics[$oid_name] = $clean_result;
                    error_log("SNMP Success: $oid_name = $clean_result");
                } else {
                    $last_error = error_get_last();
                    $errors[$oid_name] = 'Failed to retrieve OID: ' . $oid . ' - ' . ($last_error['message'] ?? 'No response');
                    error_log("SNMP Failed: $oid_name ($oid) - " . ($last_error['message'] ?? 'No response'));
                }
                
            } catch (Exception $e) {
                $errors[$oid_name] = 'Error: ' . $e->getMessage();
                error_log("SNMP Exception: $oid_name - " . $e->getMessage());
            }
        }
        
        // Then try additional OIDs from database if we have basic connectivity
        if (!empty($metrics)) {
            foreach ($oids as $oid_mapping) {
                $oid = $oid_mapping['oid_value'];
                $oid_name = $oid_mapping['oid_name'];
                
                // Skip if we already have this metric from essential OIDs
                if (isset($metrics[$oid_name])) continue;
                
                try {
                    if ($version === '1') {
                        $result = @snmpget($ip . ':' . $port, $community, $oid, 3000000, 2);
                    } elseif ($version === '2c') {
                        $result = @snmp2_get($ip . ':' . $port, $community, $oid, 3000000, 2);
                    }
                    
                    if ($result !== false && $result !== '') {
                        // Clean the result (remove type prefix like "STRING: ")
                        $clean_result = preg_replace('/^[A-Z-]+:\s*/', '', $result);
                        $clean_result = trim($clean_result, '"');
                        
                        // Apply multiplier if specified
                        if ($oid_mapping['oid_type'] === 'integer' && is_numeric($clean_result)) {
                            $clean_result = floatval($clean_result) * floatval($oid_mapping['multiplier']);
                        }
                        
                        $metrics[$oid_name] = $clean_result;
                    }
                    
                } catch (Exception $e) {
                    $errors[$oid_name] = 'Error: ' . $e->getMessage();
                }
            }
        }
        
        // Store metrics in database with transaction for performance
        if (!empty($metrics)) {
            $db->beginTransaction();
            try {
                // Process memory data for MikroTik
                $memory_total_mb = null;
                $memory_used_mb = null;
                $memory_usage_percent = null;
                
                if (isset($metrics['memory_total']) && isset($metrics['memory_free'])) {
                    $memory_total_bytes = floatval($metrics['memory_total']);
                    $memory_free_bytes = floatval($metrics['memory_free']);
                    $memory_used_bytes = $memory_total_bytes - $memory_free_bytes;
                    
                    $memory_total_mb = round($memory_total_bytes / (1024 * 1024), 2);
                    $memory_used_mb = round($memory_used_bytes / (1024 * 1024), 2);
                    
                    if ($memory_total_mb > 0) {
                        $memory_usage_percent = round(($memory_used_mb / $memory_total_mb) * 100, 2);
                    }
                }
                
                $insert_data = array(
                    'item_id' => $item_id,
                    'device_name' => $metrics['system_name'] ?? null,
                    'device_description' => $metrics['system_description'] ?? null,
                    'device_contact' => $metrics['system_contact'] ?? null,
                    'device_location' => $metrics['system_location'] ?? null,
                    'device_uptime' => $metrics['system_uptime'] ?? null,
                    'cpu_usage_percent' => $metrics['cpu_usage'] ?? null,
                    'memory_total_mb' => $memory_total_mb,
                    'memory_used_mb' => $memory_used_mb,
                    'memory_usage_percent' => $memory_usage_percent,
                    'interface_status' => $metrics['interface_status'] ?? null,
                    'interface_speed_mbps' => isset($metrics['interface_speed']) ? round($metrics['interface_speed'] / 1000000) : null,
                    'bytes_in_total' => $metrics['bytes_in'] ?? null,
                    'bytes_out_total' => $metrics['bytes_out'] ?? null,
                    'optical_power_rx_dbm' => isset($metrics['optical_power_rx']) ? round($metrics['optical_power_rx'] / 100, 2) : null,
                    'optical_power_tx_dbm' => isset($metrics['optical_power_tx']) ? round($metrics['optical_power_tx'] / 100, 2) : null,
                    'collection_duration_ms' => round((microtime(true) - $start_time) * 1000, 2)
                );
                
                // Insert metrics using prepared statement
                $fields = array_keys(array_filter($insert_data, function($value) { return $value !== null; }));
                $placeholders = ':' . implode(', :', $fields);
                $field_list = implode(', ', $fields);
                
                $insert_query = "INSERT INTO snmp_metrics ($field_list) VALUES ($placeholders)";
                $insert_stmt = $db->prepare($insert_query);
                
                foreach ($insert_data as $key => $value) {
                    if ($value !== null) {
                        $insert_stmt->bindValue(":$key", $value);
                    }
                }
                
                $insert_stmt->execute();
                $db->commit();
                
                $collection_time = round((microtime(true) - $start_time) * 1000, 2);
                error_log("SNMP Collection completed for {$item['name']} in {$collection_time}ms - Metrics: " . count($metrics) . ", Errors: " . count($errors));
                
            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
        }
        
        $collection_time = round((microtime(true) - $start_time) * 1000, 2);
        
        return array(
            'success' => true,
            'message' => 'SNMP metrics collected successfully',
            'metrics' => $metrics,
            'errors' => $errors,
            'item_name' => $item['name'],
            'collection_time_ms' => $collection_time,
            'metrics_count' => count($metrics),
            'errors_count' => count($errors)
        );
        
    } catch (Exception $e) {
        return array('success' => false, 'message' => 'SNMP collection error: ' . $e->getMessage());
    }
}

function collectAllSNMPMetrics($db) {
    $total_start_time = microtime(true);
    
    // Get all SNMP-enabled devices with priority ordering
    $query = "SELECT fi.id, fi.name, fi.ip_address,
                     COALESCE(sm.metric_time, '2000-01-01') as last_metric_time
              FROM ftth_items fi
              LEFT JOIN latest_snmp_metrics sm ON fi.id = sm.item_id
              WHERE fi.snmp_enabled = 1 AND fi.ip_address IS NOT NULL AND fi.ip_address != ''
              ORDER BY 
                  CASE WHEN sm.metric_time IS NULL THEN 1 ELSE 2 END,
                  sm.metric_time ASC,
                  fi.name ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $results = array(
        'total_devices' => count($items),
        'successful' => 0,
        'failed' => 0,
        'results' => array(),
        'summary' => array(),
        'total_time_ms' => 0
    );
    
    error_log("Starting bulk SNMP collection for " . count($items) . " devices");
    
    foreach ($items as $item) {
        $device_start = microtime(true);
        
        try {
            $result = collectSNMPMetrics($db, $item['id']);
            
            if ($result['success']) {
                $results['successful']++;
            } else {
                $results['failed']++;
            }
            
            $result['device_collection_time_ms'] = round((microtime(true) - $device_start) * 1000, 2);
            $results['results'][] = $result;
            
        } catch (Exception $e) {
            $results['failed']++;
            $results['results'][] = array(
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage(),
                'item_name' => $item['name'],
                'device_collection_time_ms' => round((microtime(true) - $device_start) * 1000, 2)
            );
            error_log("SNMP Collection failed for {$item['name']}: " . $e->getMessage());
        }
    }
    
    $results['total_time_ms'] = round((microtime(true) - $total_start_time) * 1000, 2);
    $results['average_time_per_device_ms'] = $results['total_devices'] > 0 ? 
        round($results['total_time_ms'] / $results['total_devices'], 2) : 0;
    
    error_log("Bulk SNMP collection completed - Success: {$results['successful']}, Failed: {$results['failed']}, Total time: {$results['total_time_ms']}ms");
    
    return $results;
}

function configureSNMP($db, $item_id, $snmp_config) {
    try {
        $fields = array();
        $values = array();
        
        foreach ($snmp_config as $field => $value) {
            if ($value !== null && $value !== '') {
                $fields[] = "$field = :$field";
                $values[":$field"] = $value;
            }
        }
        
        if (empty($fields)) {
            return array('success' => false, 'message' => 'No SNMP configuration provided');
        }
        
        $query = "UPDATE ftth_items SET " . implode(', ', $fields) . " WHERE id = :item_id";
        $values[':item_id'] = $item_id;
        
        $stmt = $db->prepare($query);
        foreach ($values as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        if ($stmt->execute()) {
            return array('success' => true, 'message' => 'SNMP configuration updated successfully');
        } else {
            return array('success' => false, 'message' => 'Failed to update SNMP configuration');
        }
        
    } catch (Exception $e) {
        return array('success' => false, 'message' => 'Configuration error: ' . $e->getMessage());
    }
}
?>
