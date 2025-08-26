<?php
require_once '../config/database.php';

header('Content-Type: application/json');

function pingHost($host, $timeout = 3) {
    // Windows ping command
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $ping_cmd = "ping -n 1 -w " . ($timeout * 1000) . " " . escapeshellarg($host);
    } else {
        // Linux/Unix ping command
        $ping_cmd = "ping -c 1 -W " . $timeout . " " . escapeshellarg($host);
    }
    
    $output = [];
    $return_var = 0;
    
    exec($ping_cmd, $output, $return_var);
    
    $ping_result = [
        'host' => $host,
        'status' => $return_var === 0 ? 'up' : 'down',
        'response_time' => null,
        'packet_loss' => $return_var === 0 ? 0 : 100,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Extract response time from output
    if ($return_var === 0) {
        $output_string = implode(' ', $output);
        
        // Windows format: time<1ms or time=5ms
        if (preg_match('/time[<=](\d+)ms/', $output_string, $matches)) {
            $ping_result['response_time'] = intval($matches[1]);
        }
        // Linux format: time=1.23 ms
        elseif (preg_match('/time=([0-9.]+)\s*ms/', $output_string, $matches)) {
            $ping_result['response_time'] = floatval($matches[1]);
        }
    }
    
    return $ping_result;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'ping_single':
            $host = $_GET['host'] ?? '';
            
            if (empty($host)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Host parameter is required'
                ]);
                break;
            }
            
            $result = pingHost($host);
            
            echo json_encode([
                'success' => true,
                'ping_result' => $result
            ]);
            break;
            
        case 'ping_access_points':
            // Get all Access Points with IP addresses
            $query = "SELECT id, name, ip_address 
                      FROM ftth_items 
                      WHERE item_type_id = 9 
                      AND ip_address IS NOT NULL 
                      AND ip_address != ''
                      ORDER BY name";
            
            $stmt = $db->prepare($query);
            $stmt->execute();
            $access_points = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $ping_results = [];
            
            foreach ($access_points as $ap) {
                $ping_result = pingHost($ap['ip_address']);
                $ping_result['device_id'] = $ap['id'];
                $ping_result['device_name'] = $ap['name'];
                $ping_results[] = $ping_result;
            }
            
            echo json_encode([
                'success' => true,
                'ping_results' => $ping_results,
                'total_devices' => count($access_points),
                'online_devices' => count(array_filter($ping_results, function($r) { return $r['status'] === 'up'; }))
            ]);
            break;
            
        case 'ping_device':
            $device_id = intval($_GET['device_id'] ?? 0);
            
            if (!$device_id) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Device ID is required'
                ]);
                break;
            }
            
            // Get device info
            $query = "SELECT id, name, ip_address, item_type_id 
                      FROM ftth_items 
                      WHERE id = :device_id";
            
            $stmt = $db->prepare($query);
            $stmt->execute([':device_id' => $device_id]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$device) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Device not found'
                ]);
                break;
            }
            
            if (empty($device['ip_address'])) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Device has no IP address configured'
                ]);
                break;
            }
            
            $ping_result = pingHost($device['ip_address']);
            $ping_result['device_id'] = $device['id'];
            $ping_result['device_name'] = $device['name'];
            $ping_result['device_type'] = $device['item_type_id'];
            
            echo json_encode([
                'success' => true,
                'ping_result' => $ping_result
            ]);
            break;
            
        case 'bulk_ping':
            $devices = json_decode($_POST['devices'] ?? '[]', true);
            
            if (empty($devices)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'No devices provided'
                ]);
                break;
            }
            
            $ping_results = [];
            
            foreach ($devices as $device) {
                if (!empty($device['ip_address'])) {
                    $ping_result = pingHost($device['ip_address']);
                    $ping_result['device_id'] = $device['id'];
                    $ping_result['device_name'] = $device['name'];
                    $ping_results[] = $ping_result;
                }
            }
            
            echo json_encode([
                'success' => true,
                'ping_results' => $ping_results,
                'total_tested' => count($ping_results)
            ]);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action'
            ]);
            break;
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
