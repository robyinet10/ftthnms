<?php
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

$database = new Database();
$db = $database->getConnection();

$response = array('success' => false, 'message' => '', 'data' => null);

try {
    // Check authentication for all requests
    if (!checkPermission()) {
        http_response_code(401);
        echo json_encode(array('success' => false, 'message' => 'Authentication required'));
        exit();
    }
    // Get item counts by type
    $query = "SELECT it.name as item_type, COUNT(i.id) as count
              FROM item_types it
              LEFT JOIN ftth_items i ON it.id = i.item_type_id
              GROUP BY it.id, it.name
              ORDER BY it.id";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $statistics = array();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $key = strtolower(str_replace(' ', '_', $row['item_type']));
        $statistics[$key] = (int)$row['count'];
    }
    
    // Get total routes
    $query = "SELECT COUNT(*) as total_routes FROM cable_routes";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $statistics['total_routes'] = (int)$result['total_routes'];
    
    // Get route status counts
    $query = "SELECT status, COUNT(*) as count FROM cable_routes GROUP BY status";
    $stmt = $db->prepare($query);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $statistics['routes_' . $row['status']] = (int)$row['count'];
    }
    
    // Calculate total distance
    $query = "SELECT SUM(distance) as total_distance FROM cable_routes WHERE distance IS NOT NULL";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $statistics['total_distance_km'] = round((float)$result['total_distance'] / 1000, 2);
    
    // Calculate total items
    $query = "SELECT COUNT(*) as total_items FROM ftth_items";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $statistics['total_items'] = (int)$result['total_items'];
    
    // Get auto-generated tiang tumpu statistics
    $query = "SELECT 
                COUNT(*) as auto_generated_tiang_tumpu,
                COUNT(CASE WHEN auto_generated_type = 'interval' THEN 1 END) as auto_generated_interval,
                COUNT(CASE WHEN auto_generated_type = 'turn' THEN 1 END) as auto_generated_turn
              FROM ftth_items 
              WHERE item_type_id = 2 AND is_auto_generated = 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $auto_generated_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $statistics['auto_generated_tiang_tumpu'] = (int)$auto_generated_stats['auto_generated_tiang_tumpu'];
    $statistics['auto_generated_interval'] = (int)$auto_generated_stats['auto_generated_interval'];
    $statistics['auto_generated_turn'] = (int)$auto_generated_stats['auto_generated_turn'];
    
    // Get routes with auto-generate enabled
    $query = "SELECT COUNT(*) as routes_with_auto_generate FROM cable_routes WHERE auto_generate_tiang_tumpu = 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $statistics['routes_with_auto_generate'] = (int)$result['routes_with_auto_generate'];
    
    // Add specific mappings for better compatibility
    $statistics['server'] = $statistics['server'] ?? 0;
    $statistics['olt'] = $statistics['olt'] ?? 0;
    $statistics['tiang_tumpu'] = $statistics['tiang_tumpu'] ?? 0;
    $statistics['tiang_odp'] = $statistics['tiang_odp'] ?? 0;
    $statistics['tiang_odc'] = $statistics['tiang_odc'] ?? ($statistics['odc_pole_mounted'] ?? 0);
    $statistics['ont'] = $statistics['ont'] ?? 0;
    $statistics['htb'] = $statistics['htb'] ?? 0;
    $statistics['access_point'] = $statistics['access_point'] ?? 0;
    $statistics['tiang_joint_closure'] = $statistics['tiang_joint_closure'] ?? 0;
    
    $response['success'] = true;
    $response['data'] = $statistics;
    
} catch (Exception $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
}

echo json_encode($response);
?>