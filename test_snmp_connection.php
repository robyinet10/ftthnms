<?php
// Helper file untuk test SNMP connection (tanpa perlu item ID)
// Digunakan untuk test connection saat create item baru

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? 'http://localhost'));
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, Authorization');
header('Access-Control-Allow-Credentials: true');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'api/auth.php';

$response = array('success' => false, 'message' => '', 'data' => null);

try {
    // Check authentication
    if (!checkPermission()) {
        http_response_code(401);
        echo json_encode(array('success' => false, 'message' => 'Authentication required'));
        exit();
    }

    // Check if SNMP extension is loaded
    if (!extension_loaded('snmp')) {
        $response['message'] = 'SNMP extension not loaded. Please install php-snmp extension.';
        $response['install_guide'] = 'Windows: Enable extension=snmp in php.ini | Linux: sudo apt-get install php-snmp';
        echo json_encode($response);
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $response['message'] = 'Only POST method allowed';
        echo json_encode($response);
        exit();
    }

    $ip_address = $_POST['ip_address'] ?? '';
    $snmp_version = $_POST['snmp_version'] ?? '2c';
    $snmp_community = $_POST['snmp_community'] ?? 'public';
    $snmp_port = $_POST['snmp_port'] ?? 161;

    if (!$ip_address) {
        $response['message'] = 'IP address is required';
        echo json_encode($response);
        exit();
    }

    // Validate IP address format
    if (!filter_var($ip_address, FILTER_VALIDATE_IP)) {
        $response['message'] = 'Invalid IP address format';
        echo json_encode($response);
        exit();
    }

    // Test SNMP connection
    $result = testSNMPConnectionDirect($ip_address, $snmp_version, $snmp_community, $snmp_port);
    
    if ($result['success']) {
        $response['success'] = true;
        $response['message'] = 'SNMP connection successful';
        $response['system_name'] = $result['system_name'] ?? 'Unknown Device';
        $response['version'] = $snmp_version;
        $response['community'] = $snmp_community;
        $response['port'] = $snmp_port;
        $response['response_time'] = $result['response_time'] ?? null;
    } else {
        $response['success'] = false;
        $response['message'] = $result['message'];
    }

} catch (Exception $e) {
    $response['message'] = 'Server error: ' . $e->getMessage();
    error_log("SNMP Test Error: " . $e->getMessage());
}

echo json_encode($response);

// Helper function to test SNMP connection directly
function testSNMPConnectionDirect($ip, $version, $community, $port) {
    try {
        // OID for system name (standard MIB-2)
        $system_name_oid = '1.3.6.1.2.1.1.5.0';
        $system_desc_oid = '1.3.6.1.2.1.1.1.0';
        $system_uptime_oid = '1.3.6.1.2.1.1.3.0';
        
        $target = $ip . ':' . $port;
        $timeout = 3000000; // 3 seconds in microseconds
        $retries = 2;
        
        $start_time = microtime(true);
        
        // Try to get system name first
        if ($version === '1') {
            $system_name = @snmpget($target, $community, $system_name_oid, $timeout, $retries);
        } elseif ($version === '2c') {
            $system_name = @snmp2_get($target, $community, $system_name_oid, $timeout, $retries);
        } else {
            return array(
                'success' => false, 
                'message' => 'SNMPv3 not implemented yet'
            );
        }
        
        $end_time = microtime(true);
        $response_time = round(($end_time - $start_time) * 1000, 2); // in milliseconds
        
        if ($system_name !== false) {
            // Clean the result (remove type prefix like "STRING: ")
            $clean_name = preg_replace('/^[A-Z-]+:\s*/', '', $system_name);
            
            // Try to get additional info for better verification
            $additional_info = array();
            
            // Get system description
            if ($version === '1') {
                $system_desc = @snmpget($target, $community, $system_desc_oid, $timeout, 1);
            } else {
                $system_desc = @snmp2_get($target, $community, $system_desc_oid, $timeout, 1);
            }
            
            if ($system_desc !== false) {
                $additional_info['description'] = preg_replace('/^[A-Z-]+:\s*/', '', $system_desc);
            }
            
            // Get system uptime
            if ($version === '1') {
                $system_uptime = @snmpget($target, $community, $system_uptime_oid, $timeout, 1);
            } else {
                $system_uptime = @snmp2_get($target, $community, $system_uptime_oid, $timeout, 1);
            }
            
            if ($system_uptime !== false) {
                $uptime_value = preg_replace('/^[A-Z-]+:\s*\((\d+)\).*/', '$1', $system_uptime);
                if (is_numeric($uptime_value)) {
                    // Convert hundredths of seconds to human readable
                    $uptime_seconds = intval($uptime_value) / 100;
                    $additional_info['uptime_seconds'] = $uptime_seconds;
                    
                    $days = floor($uptime_seconds / 86400);
                    $hours = floor(($uptime_seconds % 86400) / 3600);
                    $minutes = floor(($uptime_seconds % 3600) / 60);
                    $additional_info['uptime_human'] = sprintf('%dd %dh %dm', $days, $hours, $minutes);
                }
            }
            
            return array(
                'success' => true,
                'system_name' => $clean_name,
                'response_time' => $response_time,
                'additional_info' => $additional_info,
                'raw_response' => $system_name
            );
            
        } else {
            // Get the last error
            $last_error = error_get_last();
            $error_message = 'SNMP request failed';
            
            if ($last_error && strpos($last_error['message'], 'snmp') !== false) {
                $error_message = $last_error['message'];
            }
            
            // Common error analysis
            if (strpos($error_message, 'timeout') !== false || 
                strpos($error_message, 'No response') !== false) {
                $error_message = 'Connection timeout - device might not support SNMP or wrong community string';
            } elseif (strpos($error_message, 'No route to host') !== false) {
                $error_message = 'Host unreachable - check IP address and network connectivity';
            } elseif (strpos($error_message, 'Connection refused') !== false) {
                $error_message = 'Connection refused - SNMP service might be disabled on device';
            }
            
            return array(
                'success' => false,
                'message' => $error_message,
                'response_time' => $response_time,
                'debug_info' => array(
                    'target' => $target,
                    'community' => $community,
                    'version' => $version,
                    'timeout' => $timeout,
                    'oid' => $system_name_oid
                )
            );
        }
        
    } catch (Exception $e) {
        return array(
            'success' => false,
            'message' => 'SNMP error: ' . $e->getMessage(),
            'exception' => get_class($e)
        );
    }
}
?>
