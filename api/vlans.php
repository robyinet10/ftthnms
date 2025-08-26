<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? 'http://localhost'));
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

// Check authentication
$auth_check = checkAuthenticationStatus();
if (!$auth_check['authenticated']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$response = ['success' => false, 'message' => '', 'data' => []];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if ($method === 'GET') {
        // Get all servers with VLAN configuration - simplified approach
        $query = "
            SELECT id, name, vlan_config
            FROM ftth_items 
            WHERE item_type_id = 7 
            AND vlan_config IS NOT NULL 
            AND vlan_config != 'null' 
            AND vlan_config != ''
            ORDER BY name
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        $servers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $vlans = [];
        $vlan_map = []; // To avoid duplicates and track sources
        
        foreach ($servers as $server) {
            try {
                $vlan_config = json_decode($server['vlan_config'], true);
                
                if (is_array($vlan_config)) {
                    foreach ($vlan_config as $vlan) {
                        $vlan_id = trim($vlan['vlan_id'] ?? '');
                        $description = trim($vlan['description'] ?? '');
                        $ip = trim($vlan['ip'] ?? '');
                        
                        // Skip if VLAN ID is empty
                        if (empty($vlan_id)) {
                            continue;
                        }
                        
                        // If description is empty, use a default
                        if (empty($description)) {
                            $description = "VLAN {$vlan_id}";
                        }
                        
                        $key = $vlan_id; // Use VLAN ID as unique key
                        
                        if (!isset($vlan_map[$key])) {
                            $vlan_map[$key] = [
                                'vlan_id' => $vlan_id,
                                'description' => $description,
                                'ip' => $ip,
                                'servers' => []
                            ];
                        }
                        
                        // Add server info
                        $vlan_map[$key]['servers'][] = [
                            'server_id' => $server['id'],
                            'server_name' => $server['name']
                        ];
                        
                        // If multiple servers have same VLAN but different descriptions,
                        // keep the first non-empty description
                        if (!empty($description) && $description !== "VLAN {$vlan_id}") {
                            $vlan_map[$key]['description'] = $description;
                        }
                        
                        // Keep the first non-empty IP if multiple servers have different IPs
                        if (!empty($ip) && empty($vlan_map[$key]['ip'])) {
                            $vlan_map[$key]['ip'] = $ip;
                        }
                    }
                }
            } catch (Exception $e) {
                // Log error but continue processing other servers
                error_log("Error processing VLAN config for server {$server['name']}: " . $e->getMessage());
                continue;
            }
        }
        
        // Convert map to indexed array
        foreach ($vlan_map as $vlan) {
            $vlans[] = $vlan;
        }
        
        // Sort by VLAN ID numerically
        usort($vlans, function($a, $b) {
            return intval($a['vlan_id']) - intval($b['vlan_id']);
        });
        
        $response['success'] = true;
        $response['data'] = $vlans;
        $response['message'] = count($vlans) > 0 ? 'VLANs retrieved successfully' : 'No VLANs found';
        $response['total'] = count($vlans);
        $response['servers_processed'] = count($servers);
        
    } else {
        http_response_code(405);
        $response['message'] = 'Method not allowed';
    }
    
} catch (Exception $e) {
    error_log("VLAN API Error: " . $e->getMessage());
    http_response_code(500);
    $response['message'] = 'Server error: ' . $e->getMessage();
}

echo json_encode($response);
?>
