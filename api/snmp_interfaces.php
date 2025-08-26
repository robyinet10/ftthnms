<?php
// Real-time SNMP Interface Monitoring API
// Provides comprehensive interface data without database storage

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
        case 'interfaces':
            $response['data'] = getInterfacesList($target, $community, $version);
            $response['success'] = true;
            break;
            
        case 'interface_details':
            $interface_index = $_GET['interface'] ?? null;
            if (!$interface_index) {
                throw new Exception('Interface index required');
            }
            $response['data'] = getInterfaceDetails($target, $community, $version, $interface_index);
            $response['success'] = true;
            break;
            
        case 'traffic_stats':
            $response['data'] = getTrafficStats($target, $community, $version);
            $response['success'] = true;
            break;
            
        case 'discover_store':
            // New action: Discover interfaces dan store ke database
            $response['data'] = discoverAndStoreInterfaces($db, $target, $community, $version, $device);
            $response['success'] = true;
            break;
            
        case 'topology_map':
            // New action: Get topology mapping
            $response['data'] = getTopologyMapping($db, $item_id);
            $response['success'] = true;
            break;
            
        case 'comprehensive':
        default:
            // Enhanced comprehensive data dengan database storage
            $response['data'] = getComprehensiveInterfaceData($db, $target, $community, $version, $device);
            $response['success'] = true;
            break;
    }
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log("SNMP Interfaces API Error: " . $e->getMessage());
}

// Clean output buffer before sending JSON
ob_clean();
header('Content-Type: application/json; charset=utf-8');
echo json_encode($response);

// ===== HELPER FUNCTIONS =====

function getInterfacesList($target, $community, $version) {
    $interfaces = [];
    
    try {
        // Get interface indexes
        $indexes = snmpwalk($target, $community, '1.3.6.1.2.1.2.2.1.1', 3000000, 2);
        if (!$indexes) return $interfaces;
        
        // Get interface descriptions
        $descriptions = snmpwalk($target, $community, '1.3.6.1.2.1.2.2.1.2', 3000000, 2);
        $types = snmpwalk($target, $community, '1.3.6.1.2.1.2.2.1.3', 3000000, 2);
        $speeds = snmpwalk($target, $community, '1.3.6.1.2.1.2.2.1.5', 3000000, 2);
        $admin_status = snmpwalk($target, $community, '1.3.6.1.2.1.2.2.1.7', 3000000, 2);
        $oper_status = snmpwalk($target, $community, '1.3.6.1.2.1.2.2.1.8', 3000000, 2);
        
        foreach ($indexes as $oid => $index) {
            $interface_index = intval($index);
            $oid_suffix = substr($oid, strrpos($oid, '.') + 1);
            
            $interfaces[] = [
                'index' => $interface_index,
                'name' => isset($descriptions[$oid]) ? trim($descriptions[$oid], '"') : "Interface $interface_index",
                'type' => getInterfaceType(isset($types[$oid]) ? $types[$oid] : 1),
                'speed' => isset($speeds[$oid]) ? formatSpeed($speeds[$oid]) : 'Unknown',
                'admin_status' => isset($admin_status[$oid]) ? getStatusText($admin_status[$oid], 'admin') : 'Unknown',
                'oper_status' => isset($oper_status[$oid]) ? getStatusText($oper_status[$oid], 'oper') : 'Unknown',
                'status_color' => getStatusColor(isset($oper_status[$oid]) ? $oper_status[$oid] : 2)
            ];
        }
        
    } catch (Exception $e) {
        error_log("Error getting interfaces list: " . $e->getMessage());
    }
    
    return $interfaces;
}

function getInterfaceDetails($target, $community, $version, $interface_index) {
    $details = [];
    
    try {
        $oid_base = '1.3.6.1.2.1.2.2.1.';
        
        $details = [
            'index' => $interface_index,
            'description' => snmpget($target, $community, $oid_base . '2.' . $interface_index, 3000000, 2),
            'type' => getInterfaceType(snmpget($target, $community, $oid_base . '3.' . $interface_index, 3000000, 2)),
            'mtu' => snmpget($target, $community, $oid_base . '4.' . $interface_index, 3000000, 2),
            'speed' => formatSpeed(snmpget($target, $community, $oid_base . '5.' . $interface_index, 3000000, 2)),
            'mac_address' => formatMacAddress(snmpget($target, $community, $oid_base . '6.' . $interface_index, 3000000, 2)),
            'admin_status' => getStatusText(snmpget($target, $community, $oid_base . '7.' . $interface_index, 3000000, 2), 'admin'),
            'oper_status' => getStatusText(snmpget($target, $community, $oid_base . '8.' . $interface_index, 3000000, 2), 'oper'),
            'last_change' => snmpget($target, $community, $oid_base . '9.' . $interface_index, 3000000, 2)
        ];
        
        // Get IP addresses for this interface
        $details['ip_addresses'] = getInterfaceIPAddresses($target, $community, $interface_index);
        
        // Get traffic statistics
        $details['traffic'] = getInterfaceTraffic($target, $community, $interface_index);
        
    } catch (Exception $e) {
        error_log("Error getting interface details: " . $e->getMessage());
    }
    
    return $details;
}

function getInterfaceIPAddresses($target, $community, $interface_index) {
    $ip_addresses = [];
    
    try {
        // Get IP address table
        $ip_addrs = snmpwalk($target, $community, '1.3.6.1.2.1.4.20.1.1', 3000000, 2);
        $ip_indexes = snmpwalk($target, $community, '1.3.6.1.2.1.4.20.1.2', 3000000, 2);
        $ip_netmasks = snmpwalk($target, $community, '1.3.6.1.2.1.4.20.1.3', 3000000, 2);
        
        if ($ip_addrs && $ip_indexes) {
            foreach ($ip_indexes as $oid => $if_index) {
                if (intval($if_index) == $interface_index) {
                    $ip_oid = str_replace('1.3.6.1.2.1.4.20.1.2', '1.3.6.1.2.1.4.20.1.1', $oid);
                    $mask_oid = str_replace('1.3.6.1.2.1.4.20.1.2', '1.3.6.1.2.1.4.20.1.3', $oid);
                    
                    $ip_addresses[] = [
                        'ip' => isset($ip_addrs[$ip_oid]) ? $ip_addrs[$ip_oid] : '',
                        'netmask' => isset($ip_netmasks[$mask_oid]) ? $ip_netmasks[$mask_oid] : '',
                        'cidr' => isset($ip_netmasks[$mask_oid]) ? netmaskToCIDR($ip_netmasks[$mask_oid]) : ''
                    ];
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error getting IP addresses: " . $e->getMessage());
    }
    
    return $ip_addresses;
}

function getInterfaceTraffic($target, $community, $interface_index) {
    $traffic = [];
    
    try {
        $oid_base = '1.3.6.1.2.1.2.2.1.';
        
        // Try high-capacity counters first (64-bit)
        $hc_in_octets = @snmpget($target, $community, '1.3.6.1.2.1.31.1.1.1.6.' . $interface_index, 3000000, 2);
        $hc_out_octets = @snmpget($target, $community, '1.3.6.1.2.1.31.1.1.1.10.' . $interface_index, 3000000, 2);
        
        if ($hc_in_octets !== false && $hc_out_octets !== false) {
            // Use high-capacity counters
            $traffic = [
                'in_octets' => $hc_in_octets,
                'out_octets' => $hc_out_octets,
                'counter_type' => '64-bit'
            ];
        } else {
            // Fall back to 32-bit counters
            $traffic = [
                'in_octets' => snmpget($target, $community, $oid_base . '10.' . $interface_index, 3000000, 2),
                'out_octets' => snmpget($target, $community, $oid_base . '16.' . $interface_index, 3000000, 2),
                'counter_type' => '32-bit'
            ];
        }
        
        // Get packet counters
        $traffic['in_ucast_pkts'] = snmpget($target, $community, $oid_base . '11.' . $interface_index, 3000000, 2);
        $traffic['out_ucast_pkts'] = snmpget($target, $community, $oid_base . '17.' . $interface_index, 3000000, 2);
        $traffic['in_errors'] = snmpget($target, $community, $oid_base . '14.' . $interface_index, 3000000, 2);
        $traffic['out_errors'] = snmpget($target, $community, $oid_base . '20.' . $interface_index, 3000000, 2);
        $traffic['in_discards'] = snmpget($target, $community, $oid_base . '13.' . $interface_index, 3000000, 2);
        $traffic['out_discards'] = snmpget($target, $community, $oid_base . '19.' . $interface_index, 3000000, 2);
        
        // Format traffic data
        $traffic['in_octets_formatted'] = formatBytes($traffic['in_octets']);
        $traffic['out_octets_formatted'] = formatBytes($traffic['out_octets']);
        $traffic['timestamp'] = time();
        
    } catch (Exception $e) {
        error_log("Error getting interface traffic: " . $e->getMessage());
    }
    
    return $traffic;
}

function getTrafficStats($target, $community, $version) {
    $stats = [];
    
    try {
        // Get all interface traffic in one operation
        $in_octets = snmpwalk($target, $community, '1.3.6.1.2.1.2.2.1.10', 3000000, 2);
        $out_octets = snmpwalk($target, $community, '1.3.6.1.2.1.2.2.1.16', 3000000, 2);
        $descriptions = snmpwalk($target, $community, '1.3.6.1.2.1.2.2.1.2', 3000000, 2);
        
        if ($in_octets && $out_octets) {
            foreach ($in_octets as $oid => $in_bytes) {
                $interface_index = substr($oid, strrpos($oid, '.') + 1);
                $out_oid = str_replace('1.3.6.1.2.1.2.2.1.10', '1.3.6.1.2.1.2.2.1.16', $oid);
                $desc_oid = str_replace('1.3.6.1.2.1.2.2.1.10', '1.3.6.1.2.1.2.2.1.2', $oid);
                
                $stats[] = [
                    'interface_index' => intval($interface_index),
                    'name' => isset($descriptions[$desc_oid]) ? trim($descriptions[$desc_oid], '"') : "Interface $interface_index",
                    'in_octets' => $in_bytes,
                    'out_octets' => isset($out_octets[$out_oid]) ? $out_octets[$out_oid] : 0,
                    'in_formatted' => formatBytes($in_bytes),
                    'out_formatted' => formatBytes(isset($out_octets[$out_oid]) ? $out_octets[$out_oid] : 0),
                    'total_formatted' => formatBytes($in_bytes + (isset($out_octets[$out_oid]) ? $out_octets[$out_oid] : 0))
                ];
            }
        }
        
    } catch (Exception $e) {
        error_log("Error getting traffic stats: " . $e->getMessage());
    }
    
    return $stats;
}

function getComprehensiveInterfaceData($target, $community, $version, $device) {
    $data = [
        'device' => [
            'id' => $device['id'],
            'name' => $device['name'],
            'ip' => $device['ip_address'],
            'community' => $community,
            'version' => $version
        ],
        'interfaces' => [],
        'summary' => [
            'total_interfaces' => 0,
            'active_interfaces' => 0,
            'total_traffic_in' => 0,
            'total_traffic_out' => 0
        ],
        'timestamp' => time()
    ];
    
    try {
        // Get comprehensive interface data
        $interfaces_list = getInterfacesList($target, $community, $version);
        $data['summary']['total_interfaces'] = count($interfaces_list);
        
        foreach ($interfaces_list as $interface) {
            if ($interface['oper_status'] === 'up') {
                $data['summary']['active_interfaces']++;
            }
            
            // Get detailed data for each interface
            $details = getInterfaceDetails($target, $community, $version, $interface['index']);
            $interface['details'] = $details;
            
            // Add to summary traffic
            if (isset($details['traffic']['in_octets'])) {
                $data['summary']['total_traffic_in'] += $details['traffic']['in_octets'];
            }
            if (isset($details['traffic']['out_octets'])) {
                $data['summary']['total_traffic_out'] += $details['traffic']['out_octets'];
            }
            
            $data['interfaces'][] = $interface;
        }
        
        // Format summary traffic
        $data['summary']['total_traffic_in_formatted'] = formatBytes($data['summary']['total_traffic_in']);
        $data['summary']['total_traffic_out_formatted'] = formatBytes($data['summary']['total_traffic_out']);
        $data['summary']['total_traffic_formatted'] = formatBytes($data['summary']['total_traffic_in'] + $data['summary']['total_traffic_out']);
        
    } catch (Exception $e) {
        error_log("Error getting comprehensive interface data: " . $e->getMessage());
        $data['error'] = $e->getMessage();
    }
    
    return $data;
}

// ===== UTILITY FUNCTIONS =====

function getInterfaceType($type_id) {
    $types = [
        1 => 'other', 2 => 'regular1822', 3 => 'hdh1822', 4 => 'ddnX25', 5 => 'rfc877x25',
        6 => 'ethernet-csmacd', 7 => 'iso88023-csmacd', 8 => 'iso88024-tokenBus',
        9 => 'iso88025-tokenRing', 10 => 'iso88026-man', 11 => 'starLan', 12 => 'proteon-10Mbit',
        13 => 'proteon-80Mbit', 14 => 'hyperchannel', 15 => 'fddi', 16 => 'lapb',
        17 => 'sdlc', 18 => 'ds1', 19 => 'e1', 20 => 'basicISDN', 21 => 'primaryISDN',
        22 => 'propPointToPointSerial', 23 => 'ppp', 24 => 'softwareLoopback',
        25 => 'eon', 26 => 'ethernet-3Mbit', 27 => 'nsip', 28 => 'slip',
        29 => 'ultra', 30 => 'ds3', 31 => 'sip', 32 => 'frame-relay',
        71 => 'ieee80211', 131 => 'tunnel', 135 => 'l2vlan', 136 => 'l3ipvlan'
    ];
    
    return isset($types[$type_id]) ? $types[$type_id] : "unknown ($type_id)";
}

function getStatusText($status, $type) {
    if ($type === 'admin') {
        return $status == 1 ? 'up' : ($status == 2 ? 'down' : 'testing');
    } else {
        return $status == 1 ? 'up' : ($status == 2 ? 'down' : ($status == 3 ? 'testing' : 'unknown'));
    }
}

function getStatusColor($status) {
    return $status == 1 ? '#28a745' : ($status == 2 ? '#dc3545' : '#ffc107');
}

function formatSpeed($speed_bps) {
    if (!$speed_bps || $speed_bps == 0) return 'Unknown';
    
    if ($speed_bps >= 1000000000) {
        return number_format($speed_bps / 1000000000, 1) . ' Gbps';
    } elseif ($speed_bps >= 1000000) {
        return number_format($speed_bps / 1000000, 1) . ' Mbps';
    } elseif ($speed_bps >= 1000) {
        return number_format($speed_bps / 1000, 1) . ' Kbps';
    } else {
        return $speed_bps . ' bps';
    }
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

function formatMacAddress($mac_hex) {
    if (!$mac_hex) return '';
    
    // Remove quotes and spaces
    $mac_hex = str_replace(['"', ' '], '', $mac_hex);
    
    // Convert hex string to MAC format
    if (strlen($mac_hex) == 12) {
        return strtoupper(implode(':', str_split($mac_hex, 2)));
    }
    
    return $mac_hex;
}

function netmaskToCIDR($netmask) {
    $bits = 0;
    $netmask_parts = explode('.', $netmask);
    
    foreach ($netmask_parts as $octet) {
        $bits += substr_count(decbin($octet), '1');
    }
    
    return $bits;
}
?>
