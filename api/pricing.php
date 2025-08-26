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

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

$response = array('success' => false, 'message' => '', 'data' => null);

try {
    $action = $_REQUEST['action'] ?? '';
    
    switch($action) {
        case 'save_default_pricing':
            // Only admin can modify pricing settings
            if ($_SESSION['role'] !== 'admin') {
                throw new Exception('Only admin can modify pricing settings');
            }
            
            $tiangTumpuPrice = (float)($_POST['tiang_tumpu_price'] ?? 0);
            $autoCalculateCost = (int)($_POST['auto_calculate_cost'] ?? 0);
            
            if ($tiangTumpuPrice <= 0) {
                throw new Exception('Harga tiang tumpu harus lebih dari 0');
            }
            
            // Check if pricing settings already exist
            $check_query = "SELECT id FROM default_pricing WHERE setting_type = 'tiang_tumpu' LIMIT 1";
            $stmt = $db->prepare($check_query);
            $stmt->execute();
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                // Update existing
                $update_query = "UPDATE default_pricing SET 
                                   price_value = :price,
                                   auto_calculate = :auto_calc,
                                   updated_at = NOW(),
                                   updated_by = :user_id
                                 WHERE setting_type = 'tiang_tumpu'";
                $stmt = $db->prepare($update_query);
                $stmt->bindParam(':price', $tiangTumpuPrice, PDO::PARAM_STR);
                $stmt->bindParam(':auto_calc', $autoCalculateCost, PDO::PARAM_INT);
                $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
            } else {
                // Insert new
                $insert_query = "INSERT INTO default_pricing 
                                  (setting_type, price_value, auto_calculate, created_by, updated_by) 
                                 VALUES ('tiang_tumpu', :price, :auto_calc, :user_id, :user_id)";
                $stmt = $db->prepare($insert_query);
                $stmt->bindParam(':price', $tiangTumpuPrice, PDO::PARAM_STR);
                $stmt->bindParam(':auto_calc', $autoCalculateCost, PDO::PARAM_INT);
                $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
            }
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Pengaturan harga berhasil disimpan';
                $response['data'] = [
                    'tiang_tumpu_price' => $tiangTumpuPrice,
                    'auto_calculate_cost' => $autoCalculateCost
                ];
            } else {
                throw new Exception('Gagal menyimpan pengaturan harga');
            }
            break;
            
        case 'get_default_pricing':
            $query = "SELECT setting_type, price_value, auto_calculate, 
                            created_at, updated_at 
                     FROM default_pricing 
                     WHERE setting_type = 'tiang_tumpu' 
                     LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $pricing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($pricing) {
                $response['success'] = true;
                $response['data'] = [
                    'tiang_tumpu_price' => (float)$pricing['price_value'],
                    'auto_calculate_cost' => (int)$pricing['auto_calculate'],
                    'created_at' => $pricing['created_at'],
                    'updated_at' => $pricing['updated_at']
                ];
            } else {
                // Return default values
                $response['success'] = true;
                $response['data'] = [
                    'tiang_tumpu_price' => 750000,
                    'auto_calculate_cost' => 1
                ];
            }
            break;
            
        case 'calculate_route_cost':
            $routeDistance = (float)($_REQUEST['distance'] ?? 0);
            $interval = (int)($_REQUEST['interval'] ?? 30);
            $generateAtTurns = (bool)($_REQUEST['generate_at_turns'] ?? true);
            $estimatedTurns = (int)($_REQUEST['estimated_turns'] ?? 0);
            
            // Get current pricing
            $pricing_query = "SELECT price_value FROM default_pricing WHERE setting_type = 'tiang_tumpu' LIMIT 1";
            $stmt = $db->prepare($pricing_query);
            $stmt->execute();
            $pricing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $pricePerUnit = $pricing ? (float)$pricing['price_value'] : 750000;
            
            // Calculate tiang tumpu count
            $intervalTiangCount = $routeDistance > 0 ? ceil($routeDistance / $interval) : 0;
            $turnTiangCount = $generateAtTurns ? $estimatedTurns : 0;
            $totalTiangCount = $intervalTiangCount + $turnTiangCount;
            $totalCost = $totalTiangCount * $pricePerUnit;
            
            $response['success'] = true;
            $response['data'] = [
                'route_distance' => $routeDistance,
                'interval_meters' => $interval,
                'price_per_unit' => $pricePerUnit,
                'interval_tiang_count' => $intervalTiangCount,
                'turn_tiang_count' => $turnTiangCount,
                'total_tiang_count' => $totalTiangCount,
                'total_cost' => $totalCost,
                'formatted_cost' => 'Rp ' . number_format($totalCost, 0, ',', '.')
            ];
            break;
            
        case 'get_pricing_statistics':
            // Get statistics for accounting dashboard
            $stats_query = "SELECT 
                              COUNT(CASE WHEN is_auto_generated = 1 AND item_type_id = 2 THEN 1 END) as auto_generated_tiang,
                              SUM(CASE WHEN is_auto_generated = 1 AND item_type_id = 2 THEN COALESCE(item_price, 0) END) as auto_generated_cost,
                              COUNT(CASE WHEN is_auto_generated = 0 AND item_type_id = 2 THEN 1 END) as manual_tiang,
                              SUM(CASE WHEN is_auto_generated = 0 AND item_type_id = 2 THEN COALESCE(item_price, 0) END) as manual_cost
                            FROM ftth_items";
            $stmt = $db->prepare($stats_query);
            $stmt->execute();
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $response['success'] = true;
            $response['data'] = [
                'auto_generated_count' => (int)$stats['auto_generated_tiang'],
                'auto_generated_cost' => (float)$stats['auto_generated_cost'],
                'manual_count' => (int)$stats['manual_tiang'],
                'manual_cost' => (float)$stats['manual_cost'],
                'total_count' => (int)$stats['auto_generated_tiang'] + (int)$stats['manual_tiang'],
                'total_cost' => (float)$stats['auto_generated_cost'] + (float)$stats['manual_cost']
            ];
            break;
            
        default:
            throw new Exception('Invalid action specified');
    }
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    error_log("Pricing API Error: " . $e->getMessage());
}

echo json_encode($response);
?>
