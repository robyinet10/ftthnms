<?php
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

    switch($method) {
        case 'GET':
            $action = $_GET['action'] ?? '';
            
            if ($action === 'ping') {
                // Ping specific item
                $item_id = $_GET['id'] ?? null;
                if (!$item_id) {
                    $response['message'] = 'Item ID required';
                    break;
                }
                
                $result = pingItem($db, $item_id);
                $response['success'] = true;
                $response['data'] = $result;
                
            } elseif ($action === 'status') {
                // Get monitoring status for all items with IP
                $query = "SELECT id, name, ip_address, port_http, port_https, monitoring_status, 
                                last_ping_time, response_time_ms, item_type_id
                         FROM ftth_items 
                         WHERE ip_address IS NOT NULL AND ip_address != ''
                         ORDER BY monitoring_status DESC, name ASC";
                
                $stmt = $db->prepare($query);
                $stmt->execute();
                
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $response['success'] = true;
                $response['data'] = $items;
                
            } elseif ($action === 'logs') {
                // Get monitoring logs
                $item_id = $_GET['id'] ?? null;
                $limit = $_GET['limit'] ?? 50;
                
                if ($item_id) {
                    $query = "SELECT * FROM monitoring_logs 
                             WHERE item_id = :item_id 
                             ORDER BY ping_time DESC 
                             LIMIT :limit";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':item_id', $item_id);
                    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
                } else {
                    $query = "SELECT ml.*, fi.name as item_name 
                             FROM monitoring_logs ml
                             LEFT JOIN ftth_items fi ON ml.item_id = fi.id
                             ORDER BY ping_time DESC 
                             LIMIT :limit";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
                }
                
                $stmt->execute();
                $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $response['success'] = true;
                $response['data'] = $logs;
                
            } else {
                $response['message'] = 'Invalid action';
            }
            break;
            
        case 'POST':
            $action = $_POST['action'] ?? '';
            
            if ($action === 'ping_all') {
                // Ping all items with IP address
                $results = pingAllItems($db);
                $response['success'] = true;
                $response['data'] = $results;
                
            } elseif ($action === 'update_status') {
                // Manual status update (admin only)
                if (!isAdmin()) {
                    http_response_code(403);
                    echo json_encode(array('success' => false, 'message' => 'Admin permission required'));
                    exit();
                }
                
                $item_id = $_POST['item_id'] ?? null;
                $status = $_POST['status'] ?? null;
                
                if (!$item_id || !$status) {
                    $response['message'] = 'Item ID and status required';
                    break;
                }
                
                $query = "UPDATE ftth_items SET monitoring_status = :status WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':status', $status);
                $stmt->bindParam(':id', $item_id);
                
                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Status updated successfully';
                } else {
                    $response['message'] = 'Failed to update status';
                }
                
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
}

echo json_encode($response);

// Helper function to ping a single item
function pingItem($db, $item_id) {
    // Get item details
    $query = "SELECT id, name, ip_address, port_http, port_https FROM ftth_items WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $item_id);
    $stmt->execute();
    
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$item || !$item['ip_address']) {
        return array('status' => 'error', 'message' => 'Item not found or no IP address');
    }
    
    $ip = $item['ip_address'];
    
    // Try to ping the IP address
    $ping_result = pingHost($ip);
    
    // Determine status and get actual response time from ping result
    if ($ping_result['success']) {
        $status = 'online';
        $error_message = null;
        $response_time = $ping_result['response_time_ms']; // Use actual ping response time
    } else {
        // Analyze error message to determine appropriate status
        $error_lower = strtolower($ping_result['error']);
        
        // Check for different types of failures
        if (strpos($error_lower, 'request timed out') !== false || 
            strpos($error_lower, 'timeout') !== false ||
            strpos($error_lower, 'timed out') !== false) {
            // Timeout = device might be there but not responding to ping
            $status = 'warning';
        } elseif (strpos($error_lower, 'destination host unreachable') !== false ||
                  strpos($error_lower, 'host unreachable') !== false ||
                  strpos($error_lower, 'no route to host') !== false ||
                  strpos($error_lower, 'network is unreachable') !== false) {
            // Network/routing issue = offline
            $status = 'offline';
        } elseif (strpos($error_lower, 'could not find host') !== false ||
                  strpos($error_lower, 'ping: cannot resolve') !== false ||
                  strpos($error_lower, 'name or service not known') !== false) {
            // DNS/hostname resolution issue = offline
            $status = 'offline';
        } elseif (strpos($error_lower, 'general failure') !== false) {
            // Windows general failure = usually network adapter issue
            $status = 'offline';
        } else {
            // Default for unknown errors
            $status = 'offline';
        }
        
        $error_message = $ping_result['error'];
        $response_time = null;
    }
    
    // Update item status
    $update_query = "UPDATE ftth_items SET 
                     monitoring_status = :status, 
                     last_ping_time = NOW(), 
                     response_time_ms = :response_time 
                     WHERE id = :id";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':status', $status);
    $update_stmt->bindParam(':response_time', $response_time);
    $update_stmt->bindParam(':id', $item_id);
    $update_stmt->execute();
    
    // Log the result
    $log_query = "INSERT INTO monitoring_logs (item_id, status, response_time_ms, error_message) 
                  VALUES (:item_id, :status, :response_time, :error_message)";
    $log_stmt = $db->prepare($log_query);
    $log_stmt->bindParam(':item_id', $item_id);
    $log_stmt->bindParam(':status', $status);
    $log_stmt->bindParam(':response_time', $response_time);
    $log_stmt->bindParam(':error_message', $error_message);
    $log_stmt->execute();
    
    return array(
        'item_id' => $item_id,
        'name' => $item['name'],
        'ip_address' => $ip,
        'status' => $status,
        'response_time_ms' => $response_time,
        'error_message' => $error_message
    );
}

// Helper function to ping all items
function pingAllItems($db) {
    $query = "SELECT id, name, ip_address FROM ftth_items WHERE ip_address IS NOT NULL AND ip_address != ''";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $results = array();
    
    foreach ($items as $item) {
        $result = pingItem($db, $item['id']);
        $results[] = $result;
    }
    
    return $results;
}

// Enhanced ping function - cross-platform with response time parsing
function pingHost($host) {
    // Remove any protocol prefix
    $host = preg_replace('/^https?:\/\//', '', $host);
    
    // Detect OS and use appropriate ping command
    $os = strtoupper(substr(PHP_OS, 0, 3));
    
    if ($os === 'WIN') {
        // Windows ping command (1 packet, 3 second timeout)
        $command = "ping -n 1 -w 3000 " . escapeshellarg($host);
    } else {
        // Unix/Linux ping command (1 packet, 3 second timeout)
        $command = "ping -c 1 -W 3 " . escapeshellarg($host);
    }
    
    $output = array();
    $return_code = 0;
    
    exec($command, $output, $return_code);
    $output_text = implode("\n", $output);
    
    // Log the ping command and output for debugging
    error_log("PING COMMAND: $command");
    error_log("PING OUTPUT: " . substr($output_text, 0, 300));
    error_log("PING RETURN CODE: $return_code");
    
    // For Windows, check output content as well as return code
    // Sometimes ping returns 0 but the ping actually failed
    if ($return_code === 0) {
        if ($os === 'WIN') {
            // Windows-specific validation
            if (strpos($output_text, 'Reply from') !== false && 
                strpos($output_text, 'time') !== false) {
                // Valid response with time
                $ping_success = true;
            } elseif (strpos($output_text, 'Request timed out') !== false) {
                // Timeout - treat as failure
                $ping_success = false;
                $output_text .= "\nStatus: Request timed out";
            } elseif (strpos($output_text, 'Destination host unreachable') !== false) {
                // Host unreachable - treat as failure
                $ping_success = false;
                $output_text .= "\nStatus: Destination host unreachable";
            } elseif (strpos($output_text, 'General failure') !== false) {
                // General failure - treat as failure
                $ping_success = false;
                $output_text .= "\nStatus: General failure";
            } else {
                // Default to success if return code is 0
                $ping_success = true;
            }
        } else {
            // Linux/Unix - return code 0 usually means success
            $ping_success = true;
        }
    } else {
        // Non-zero return code = failure
        $ping_success = false;
    }
    
    if ($ping_success) {
        // Parse response time from ping output
        $response_time_ms = null;
        
        if ($os === 'WIN') {
            // Windows format: "Reply from 192.168.1.1: bytes=32 time=2ms TTL=64"
            // or "Reply from 192.168.1.1: bytes=32 time<1ms TTL=64"
            if (preg_match('/time<1ms/i', $output_text)) {
                // Handle "time<1ms" case - set to 0.5ms
                $response_time_ms = 0.5;
            } elseif (preg_match('/time[=](\d+(?:\.\d+)?)ms/i', $output_text, $matches)) {
                $response_time_ms = floatval($matches[1]);
            } elseif (preg_match('/time[=](\d+(?:\.\d+)?)\s*ms/i', $output_text, $matches)) {
                $response_time_ms = floatval($matches[1]);
            }
        } else {
            // Linux format: "64 bytes from 192.168.1.1: icmp_seq=1 ttl=64 time=2.34 ms"
            if (preg_match('/time=(\d+(?:\.\d+)?)\s*ms/i', $output_text, $matches)) {
                $response_time_ms = floatval($matches[1]);
            }
        }
        
        // Log ping details for debugging
        error_log("PING SUCCESS - Host: $host, OS: $os, Response Time: " . ($response_time_ms ?? 'NULL') . "ms");
        
        // If response time not found in output, default to 1ms (very fast response)
        if ($response_time_ms === null) {
            $response_time_ms = 1.0;
            error_log("PING WARNING - Could not parse response time for $host, using default 1ms");
        }
        
        // Round to 1 decimal place for consistency
        $response_time_ms = round($response_time_ms, 1);
        
        return array(
            'success' => true, 
            'output' => $output_text,
            'response_time_ms' => $response_time_ms
        );
    } else {
        // Log ping failure details
        error_log("PING FAILURE - Host: $host, OS: $os, Return Code: $return_code");
        error_log("PING ERROR OUTPUT: " . substr($output_text, 0, 200));
        
        return array(
            'success' => false, 
            'error' => $output_text,
            'response_time_ms' => null
        );
    }
}
?>
