<?php
// Enhanced SNMP Interface Monitoring API dengan Database Storage
// Provides comprehensive interface data WITH database storage untuk topology mapping

// Security headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Authentication and includes
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';

// ===== FIX SNMP OUTPUT BUFFER ISSUE =====
// Start output buffering to capture any SNMP warnings
ob_start();

// Set SNMP configuration to suppress warnings
ini_set('snmp.hide_warnings', '1');
putenv("MIBDIRS=" . __DIR__ . "\\..\\usr\\share\\snmp\\mibs");
putenv("MIBS=ALL");
putenv("SNMPCONFPATH=" . __DIR__ . "\\..\\usr\\share\\snmp");

// Suppress non-critical SNMP warnings
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

$database = new Database();
$db = $database->getConnection();

// Check authentication
if (!checkPermission()) {
    http_response_code(401);
    // Clean output buffer before sending JSON
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

$response = ['success' => false, 'message' => '', 'data' => null];

try {
    $action = $_GET['action'] ?? '';
    $item_id = $_GET['id'] ?? null;
    
    if (!$item_id) {
        throw new Exception('Device ID required');
    }
    
    // Get device details
    $deviceQuery = "SELECT * FROM ftth_items WHERE id = :id AND snmp_enabled = 1";
    $deviceStmt = $db->prepare($deviceQuery);
    $deviceStmt->bindParam(':id', $item_id);
    $deviceStmt->execute();
    $device = $deviceStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$device) {
        throw new Exception('SNMP device not found or not enabled');
    }
    
    // SNMP connection parameters
    $ip = $device['ip_address'];
    $community = $device['snmp_community'] ?: 'public';
    $port = $device['snmp_port'] ?: 161;
    $version = $device['snmp_version'] ?: '2c';
    $target = $ip . ':' . $port;
    
    // Configure SNMP
    snmp_set_valueretrieval(SNMP_VALUE_PLAIN);
    snmp_set_quick_print(true);
    snmp_set_oid_output_format(SNMP_OID_OUTPUT_NUMERIC);
    
    switch ($action) {
        case 'discover_store':
            // Discover interfaces dan store ke database
            $response['data'] = discoverAndStoreInterfaces($db, $target, $community, $version, $device);
            $response['success'] = true;
            break;
            
        case 'stored_interfaces':
            // Get interfaces dari database
            $response['data'] = getStoredInterfaces($db, $item_id);
            $response['success'] = true;
            break;
            
        case 'topology_map':
            // Get topology mapping
            $response['data'] = getTopologyMapping($db, $item_id);
            $response['success'] = true;
            break;
            
        case 'real_time_traffic':
            // Get real-time traffic (not stored)
            $response['data'] = getRealTimeTraffic($target, $community, $version, $item_id);
            $response['success'] = true;
            break;
            
        case 'enhanced_comprehensive':
        default:
            // Enhanced comprehensive data dengan database + real-time
            $response['data'] = getEnhancedComprehensiveData($db, $target, $community, $version, $device);
            $response['success'] = true;
            break;
    }
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log("Enhanced SNMP Interfaces API Error: " . $e->getMessage());
}

// Clean output buffer before sending JSON
ob_clean();
header('Content-Type: application/json; charset=utf-8');
echo json_encode($response);

// ===== DATABASE STORAGE FUNCTIONS =====

function discoverAndStoreInterfaces($db, $target, $community, $version, $device) {
    $results = [
        'device_id' => $device['id'],
        'device_name' => $device['name'],
        'discovery_time' => date('Y-m-d H:i:s'),
        'interfaces_discovered' => 0,
        'interfaces_stored' => 0,
        'ip_addresses_stored' => 0,
        'topology_links' => 0,
        'errors' => []
    ];
    
    try {
        // Get interface data via SNMP
        $interfaces = [];
        
        // Get interface basic info
        $indexes = @snmpwalk($target, $community, '1.3.6.1.2.1.2.2.1.1', 5000000, 2);
        $names = @snmpwalk($target, $community, '1.3.6.1.2.1.2.2.1.2', 5000000, 2);
        $types = @snmpwalk($target, $community, '1.3.6.1.2.1.2.2.1.3', 5000000, 2);
        $speeds = @snmpwalk($target, $community, '1.3.6.1.2.1.2.2.1.5', 5000000, 2);
        $admin_status = @snmpwalk($target, $community, '1.3.6.1.2.1.2.2.1.7', 5000000, 2);
        $oper_status = @snmpwalk($target, $community, '1.3.6.1.2.1.2.2.1.8', 5000000, 2);
        $mac_addresses = @snmpwalk($target, $community, '1.3.6.1.2.1.2.2.1.6', 5000000, 2);
        $mtu_values = @snmpwalk($target, $community, '1.3.6.1.2.1.2.2.1.4', 5000000, 2);
        
        if (!$indexes) {
            throw new Exception('No interfaces found via SNMP');
        }
        
        $results['interfaces_discovered'] = count($indexes);
        
        foreach ($indexes as $oid => $index) {
            $interface_index = intval($index);
            
            $interface_data = [
                'device_id' => $device['id'],
                'interface_index' => $interface_index,
                'interface_name' => isset($names[$oid]) ? trim($names[$oid], '"') : "Interface $interface_index",
                'interface_type' => isset($types[$oid]) ? getInterfaceTypeName($types[$oid]) : 'unknown',
                'interface_type_id' => isset($types[$oid]) ? intval($types[$oid]) : 1,
                'speed_bps' => isset($speeds[$oid]) ? intval($speeds[$oid]) : null,
                'admin_status' => isset($admin_status[$oid]) ? getStatusName($admin_status[$oid], 'admin') : 'unknown',
                'oper_status' => isset($oper_status[$oid]) ? getStatusName($oper_status[$oid], 'oper') : 'unknown',
                'mac_address' => isset($mac_addresses[$oid]) ? formatMacAddress($mac_addresses[$oid]) : null,
                'mtu' => isset($mtu_values[$oid]) ? intval($mtu_values[$oid]) : null
            ];
            
            // Store interface
            $interface_id = storeInterfaceData($db, $interface_data);
            if ($interface_id) {
                $results['interfaces_stored']++;
                
                // Get and store IP addresses
                $ip_addresses = getInterfaceIPAddresses($target, $community, $interface_index);
                foreach ($ip_addresses as $ip_data) {
                    if (storeIPAddress($db, $interface_id, $device['id'], $ip_data)) {
                        $results['ip_addresses_stored']++;
                    }
                }
                
                // Detect topology connections based on IP subnets
                $topology_links = detectTopologyConnections($db, $interface_id, $device['id'], $ip_addresses);
                $results['topology_links'] += count($topology_links);
            }
        }
        
        // Update device last discovery time
        updateDeviceDiscoveryTime($db, $device['id']);
        
    } catch (Exception $e) {
        $results['errors'][] = "Discovery error: " . $e->getMessage();
    }
    
    return $results;
}

function storeInterfaceData($db, $interface_data) {
    try {
        $query = "INSERT INTO device_interfaces (
            device_id, interface_index, interface_name, interface_type, interface_type_id,
            mac_address, mtu, speed_bps, admin_status, oper_status, last_seen, updated_at
        ) VALUES (
            :device_id, :interface_index, :interface_name, :interface_type, :interface_type_id,
            :mac_address, :mtu, :speed_bps, :admin_status, :oper_status, NOW(), NOW()
        ) ON DUPLICATE KEY UPDATE
            interface_name = VALUES(interface_name),
            interface_type = VALUES(interface_type),
            mac_address = VALUES(mac_address),
            mtu = VALUES(mtu),
            speed_bps = VALUES(speed_bps),
            admin_status = VALUES(admin_status),
            oper_status = VALUES(oper_status),
            last_seen = NOW(),
            updated_at = NOW()";
        
        $stmt = $db->prepare($query);
        $stmt->execute($interface_data);
        
        // Get interface ID
        $id_query = "SELECT id FROM device_interfaces WHERE device_id = :device_id AND interface_index = :interface_index";
        $id_stmt = $db->prepare($id_query);
        $id_stmt->execute([
            ':device_id' => $interface_data['device_id'],
            ':interface_index' => $interface_data['interface_index']
        ]);
        $result = $id_stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['id'] : null;
        
    } catch (Exception $e) {
        error_log("Error storing interface data: " . $e->getMessage());
        return null;
    }
}

function getInterfaceIPAddresses($target, $community, $interface_index) {
    $ip_addresses = [];
    
    try {
        // Get IP address mappings
        $ip_addrs = @snmpwalk($target, $community, '1.3.6.1.2.1.4.20.1.1', 5000000, 2);
        $ip_indexes = @snmpwalk($target, $community, '1.3.6.1.2.1.4.20.1.2', 5000000, 2);
        $ip_netmasks = @snmpwalk($target, $community, '1.3.6.1.2.1.4.20.1.3', 5000000, 2);
        
        if ($ip_addrs && $ip_indexes) {
            foreach ($ip_indexes as $oid => $if_index) {
                if (intval($if_index) == $interface_index) {
                    $ip_oid = str_replace('1.3.6.1.2.1.4.20.1.2', '1.3.6.1.2.1.4.20.1.1', $oid);
                    $mask_oid = str_replace('1.3.6.1.2.1.4.20.1.2', '1.3.6.1.2.1.4.20.1.3', $oid);
                    
                    if (isset($ip_addrs[$ip_oid]) && isset($ip_netmasks[$mask_oid])) {
                        $ip_addresses[] = [
                            'ip' => $ip_addrs[$ip_oid],
                            'netmask' => $ip_netmasks[$mask_oid],
                            'cidr' => netmaskToCIDR($ip_netmasks[$mask_oid])
                        ];
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error getting interface IP addresses: " . $e->getMessage());
    }
    
    return $ip_addresses;
}

function storeIPAddress($db, $interface_id, $device_id, $ip_data) {
    try {
        $network_info = calculateNetworkInfo($ip_data['ip'], $ip_data['netmask']);
        
        $query = "INSERT INTO interface_ip_addresses (
            interface_id, device_id, ip_address, netmask, prefix_length,
            ip_version, network_address, broadcast_address, last_seen, updated_at
        ) VALUES (
            :interface_id, :device_id, :ip_address, :netmask, :prefix_length,
            :ip_version, :network_address, :broadcast_address, NOW(), NOW()
        ) ON DUPLICATE KEY UPDATE
            netmask = VALUES(netmask),
            prefix_length = VALUES(prefix_length),
            network_address = VALUES(network_address),
            broadcast_address = VALUES(broadcast_address),
            last_seen = NOW(),
            updated_at = NOW()";
        
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':interface_id' => $interface_id,
            ':device_id' => $device_id,
            ':ip_address' => $ip_data['ip'],
            ':netmask' => $ip_data['netmask'],
            ':prefix_length' => $ip_data['cidr'],
            ':ip_version' => strpos($ip_data['ip'], ':') !== false ? 'ipv6' : 'ipv4',
            ':network_address' => $network_info['network'],
            ':broadcast_address' => $network_info['broadcast']
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Error storing IP address: " . $e->getMessage());
        return false;
    }
}

function detectTopologyConnections($db, $source_interface_id, $source_device_id, $ip_addresses) {
    $connections = [];
    
    try {
        foreach ($ip_addresses as $ip_data) {
            $network_info = calculateNetworkInfo($ip_data['ip'], $ip_data['netmask']);
            
            // Find other devices in same network
            $query = "SELECT DISTINCT 
                        iia.device_id as target_device_id,
                        iia.interface_id as target_interface_id
                      FROM interface_ip_addresses iia
                      WHERE iia.network_address = :network_address 
                        AND iia.device_id != :source_device_id
                        AND iia.is_active = 1";
            
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':network_address' => $network_info['network'],
                ':source_device_id' => $source_device_id
            ]);
            
            $targets = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($targets as $target) {
                $connection_id = storeTopologyConnection($db, [
                    'source_device_id' => $source_device_id,
                    'source_interface_id' => $source_interface_id,
                    'target_device_id' => $target['target_device_id'],
                    'target_interface_id' => $target['target_interface_id'],
                    'connection_type' => 'routed',
                    'discovery_method' => 'ip_subnet',
                    'confidence_level' => 'medium',
                    'shared_network' => $network_info['network']
                ]);
                
                if ($connection_id) {
                    $connections[] = $connection_id;
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error detecting topology connections: " . $e->getMessage());
    }
    
    return $connections;
}

function storeTopologyConnection($db, $connection_data) {
    try {
        $query = "INSERT INTO network_topology (
            source_device_id, source_interface_id, target_device_id, target_interface_id,
            connection_type, discovery_method, confidence_level, shared_network,
            last_seen, updated_at
        ) VALUES (
            :source_device_id, :source_interface_id, :target_device_id, :target_interface_id,
            :connection_type, :discovery_method, :confidence_level, :shared_network,
            NOW(), NOW()
        ) ON DUPLICATE KEY UPDATE
            connection_type = VALUES(connection_type),
            confidence_level = VALUES(confidence_level),
            last_seen = NOW(),
            updated_at = NOW()";
        
        $stmt = $db->prepare($query);
        $stmt->execute($connection_data);
        
        return $db->lastInsertId();
    } catch (Exception $e) {
        error_log("Error storing topology connection: " . $e->getMessage());
        return null;
    }
}

function getStoredInterfaces($db, $device_id) {
    try {
        $query = "SELECT * FROM interface_summary WHERE device_id = :device_id ORDER BY interface_index";
        $stmt = $db->prepare($query);
        $stmt->execute([':device_id' => $device_id]);
        
        $interfaces = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Ensure ip_count is returned as integer to prevent JavaScript string concatenation
        foreach ($interfaces as &$interface) {
            $interface['ip_count'] = (int)($interface['ip_count'] ?? 0);
        }
        
        return $interfaces;
    } catch (Exception $e) {
        error_log("Error getting stored interfaces: " . $e->getMessage());
        return [];
    }
}

function getTopologyMapping($db, $device_id) {
    try {
        $query = "SELECT * FROM topology_view 
                  WHERE (source_device_id = :device_id OR target_device_id = :device_id)
                  AND is_active = 1
                  ORDER BY confidence_level DESC, last_seen DESC";
        $stmt = $db->prepare($query);
        $stmt->execute([':device_id' => $device_id]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting topology mapping: " . $e->getMessage());
        return [];
    }
}

function getRealTimeTraffic($target, $community, $version, $device_id) {
    $traffic_data = [];
    
    try {
        // Get real-time traffic counters (not stored in database)
        $in_octets = @snmpwalk($target, $community, '1.3.6.1.2.1.2.2.1.10', 5000000, 2);
        $out_octets = @snmpwalk($target, $community, '1.3.6.1.2.1.2.2.1.16', 5000000, 2);
        $names = @snmpwalk($target, $community, '1.3.6.1.2.1.2.2.1.2', 5000000, 2);
        
        if ($in_octets && $out_octets) {
            foreach ($in_octets as $oid => $in_bytes) {
                $interface_index = substr($oid, strrpos($oid, '.') + 1);
                $out_oid = str_replace('1.3.6.1.2.1.2.2.1.10', '1.3.6.1.2.1.2.2.1.16', $oid);
                $name_oid = str_replace('1.3.6.1.2.1.2.2.1.10', '1.3.6.1.2.1.2.2.1.2', $oid);
                
                $traffic_data[] = [
                    'interface_index' => intval($interface_index),
                    'interface_name' => isset($names[$name_oid]) ? trim($names[$name_oid], '"') : "Interface $interface_index",
                    'in_octets' => intval($in_bytes),
                    'out_octets' => isset($out_octets[$out_oid]) ? intval($out_octets[$out_oid]) : 0,
                    'in_formatted' => formatBytes($in_bytes),
                    'out_formatted' => formatBytes(isset($out_octets[$out_oid]) ? $out_octets[$out_oid] : 0),
                    'timestamp' => time()
                ];
            }
        }
    } catch (Exception $e) {
        error_log("Error getting real-time traffic: " . $e->getMessage());
    }
    
    return $traffic_data;
}

function getEnhancedComprehensiveData($db, $target, $community, $version, $device) {
    $data = [
        'device' => [
            'id' => $device['id'],
            'name' => $device['name'],
            'ip' => $device['ip_address']
        ],
        'stored_interfaces' => getStoredInterfaces($db, $device['id']),
        'real_time_traffic' => getRealTimeTraffic($target, $community, $version, $device['id']),
        'topology_connections' => getTopologyMapping($db, $device['id']),
        'timestamp' => time()
    ];
    
    return $data;
}

function updateDeviceDiscoveryTime($db, $device_id) {
    try {
        $query = "UPDATE ftth_items SET updated_at = NOW() WHERE id = :device_id";
        $stmt = $db->prepare($query);
        $stmt->execute([':device_id' => $device_id]);
    } catch (Exception $e) {
        error_log("Error updating device discovery time: " . $e->getMessage());
    }
}

// ===== UTILITY FUNCTIONS =====

function getInterfaceTypeName($type_id) {
    $types = [
        1 => 'other', 6 => 'ethernet-csmacd', 24 => 'software-loopback',
        71 => 'ieee80211', 131 => 'tunnel', 135 => 'l2vlan'
    ];
    return isset($types[$type_id]) ? $types[$type_id] : "type-$type_id";
}

function getStatusName($status, $type) {
    if ($type === 'admin') {
        return $status == 1 ? 'up' : ($status == 2 ? 'down' : 'testing');
    } else {
        return $status == 1 ? 'up' : ($status == 2 ? 'down' : 'unknown');
    }
}

function formatMacAddress($mac_hex) {
    if (!$mac_hex) return '';
    $mac_hex = str_replace(['"', ' '], '', $mac_hex);
    if (strlen($mac_hex) == 12) {
        return strtoupper(implode(':', str_split($mac_hex, 2)));
    }
    return $mac_hex;
}

function calculateNetworkInfo($ip, $netmask) {
    $ip_long = ip2long($ip);
    $mask_long = ip2long($netmask);
    $network_long = $ip_long & $mask_long;
    $broadcast_long = $network_long | (~$mask_long & 0xFFFFFFFF);
    
    return [
        'network' => long2ip($network_long),
        'broadcast' => long2ip($broadcast_long)
    ];
}

function netmaskToCIDR($netmask) {
    $bits = 0;
    $netmask_parts = explode('.', $netmask);
    foreach ($netmask_parts as $octet) {
        $bits += substr_count(decbin($octet), '1');
    }
    return $bits;
}

function formatBytes($bytes) {
    if (!$bytes || $bytes == 0) return '0 B';
    
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= (1 << (10 * $pow));
    
    return round($bytes, 2) . ' ' . $units[$pow];
}
?>
