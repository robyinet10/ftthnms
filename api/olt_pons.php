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
    echo json_encode(array('success' => false, 'message' => 'Authentication required'));
    exit();
}

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    http_response_code(500);
    echo json_encode(array('success' => false, 'message' => 'Database connection failed'));
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['pon_port'])) {
                getPonVlans($db, $_GET['pon_port']);
            } else {
                getOltPonPorts($db);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(array('success' => false, 'message' => 'Method not allowed'));
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array('success' => false, 'message' => 'Server error: ' . $e->getMessage()));
}

// Get available OLT PON ports
function getOltPonPorts($db) {
    $query = "
        SELECT 
            op.id,
            op.pon_port,
            op.description,
            op.status,
            olt.id as olt_id,
            olt.name as olt_name,
            olt.ip_address as olt_ip,
            COALESCE(COUNT(opm.id), 0) as connected_odcs
        FROM olt_pons op
        INNER JOIN ftth_items olt ON op.item_id = olt.id
        LEFT JOIN odc_pon_mapping opm ON op.pon_port = opm.pon_port AND opm.status = 'active'
        WHERE op.status = 'active' AND olt.item_type_id = 1
        GROUP BY op.id, op.pon_port, op.description, op.status, olt.id, olt.name, olt.ip_address
        HAVING connected_odcs < 4
        ORDER BY olt.name, op.pon_port
    ";
    
    try {
        $stmt = $db->prepare($query);
        $stmt->execute();
        $pon_ports = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(array(
            'success' => true,
            'data' => $pon_ports,
            'total' => count($pon_ports)
        ));
    } catch (Exception $e) {
        echo json_encode(array(
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage(),
            'data' => [],
            'total' => 0
        ));
    }
}

// Get VLAN information for specific PON port
function getPonVlans($db, $pon_port) {
    $query = "
        SELECT 
            pv.vlan_id,
            pv.description
        FROM pon_vlans pv
        INNER JOIN olt_pons op ON pv.pon_id = op.id
        WHERE op.pon_port = :pon_port
        ORDER BY pv.vlan_id
    ";
    
    try {
        $stmt = $db->prepare($query);
        $stmt->bindParam(':pon_port', $pon_port);
        $stmt->execute();
        $vlans = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(array(
            'success' => true,
            'vlans' => $vlans,
            'total' => count($vlans)
        ));
    } catch (Exception $e) {
        echo json_encode(array(
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage(),
            'vlans' => [],
            'total' => 0
        ));
    }
}
?>
