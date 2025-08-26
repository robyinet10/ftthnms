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
$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    switch ($method) {
        case 'GET':
            if ($action === 'list') {
                getOdcList($db);
            } elseif ($action === 'ports') {
                getOdcPorts($db);
            } elseif ($action === 'pon_mapping') {
                getOdcPonMapping($db);
            } elseif ($action === 'capacity') {
                getOdcCapacity($db);
            } elseif ($action === 'statistics') {
                getOdcStatistics($db);
            } else {
                http_response_code(400);
                echo json_encode(array('success' => false, 'message' => 'Invalid action'));
            }
            break;
            
        case 'POST':
            if ($action === 'create') {
                createOdc($db);
            } elseif ($action === 'update_ports') {
                updateOdcPorts($db);
            } elseif ($action === 'connect_pon') {
                connectOdcToPon($db);
            } elseif ($action === 'calculate_capacity') {
                calculateOdcCapacity($db);
            } else {
                http_response_code(400);
                echo json_encode(array('success' => false, 'message' => 'Invalid action'));
            }
            break;
            
        case 'PUT':
            if ($action === 'update') {
                updateOdc($db);
            } elseif ($action === 'update_port') {
                updateOdcPort($db);
            } else {
                http_response_code(400);
                echo json_encode(array('success' => false, 'message' => 'Invalid action'));
            }
            break;
            
        case 'DELETE':
            if ($action === 'delete') {
                deleteOdc($db);
            } elseif ($action === 'disconnect_pon') {
                disconnectOdcFromPon($db);
            } else {
                http_response_code(400);
                echo json_encode(array('success' => false, 'message' => 'Invalid action'));
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

// Get ODC list with enhanced information
function getOdcList($db) {
    $query = "
        SELECT 
            i.*,
            it.name as item_type_name,
            it.icon as item_type_icon,
            it.color as item_type_color,
            COUNT(op.id) as total_ports,
            SUM(CASE WHEN op.port_status = 'connected' THEN 1 ELSE 0 END) as connected_ports,
            SUM(CASE WHEN op.port_status = 'available' THEN 1 ELSE 0 END) as available_ports,
            opm.pon_port,
            opm.vlan_id,
            olt.name as olt_name
        FROM ftth_items i
        LEFT JOIN item_types it ON i.item_type_id = it.id
        LEFT JOIN odc_ports op ON i.id = op.odc_item_id
        LEFT JOIN odc_pon_mapping opm ON i.id = opm.odc_item_id AND opm.status = 'active'
        LEFT JOIN ftth_items olt ON opm.olt_item_id = olt.id
        WHERE i.item_type_id IN (4, 12)
        GROUP BY i.id
        ORDER BY i.created_at DESC
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $odcs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(array(
        'success' => true,
        'data' => $odcs,
        'total' => count($odcs)
    ));
}

// Get ODC ports
function getOdcPorts($db) {
    $odc_id = isset($_GET['odc_id']) ? intval($_GET['odc_id']) : 0;
    
    if (!$odc_id) {
        http_response_code(400);
        echo json_encode(array('success' => false, 'message' => 'ODC ID required'));
        return;
    }
    
    $query = "
        SELECT 
            op.*,
            connected.name as connected_to_name,
            connected.item_type_id as connected_to_type
        FROM odc_ports op
        LEFT JOIN ftth_items connected ON op.connected_to_item_id = connected.id
        WHERE op.odc_item_id = ?
        ORDER BY op.port_number
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$odc_id]);
    $ports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(array(
        'success' => true,
        'data' => $ports,
        'total' => count($ports)
    ));
}

// Get ODC-PON mapping
function getOdcPonMapping($db) {
    $odc_id = isset($_GET['odc_id']) ? intval($_GET['odc_id']) : 0;
    
    $query = "
        SELECT 
            opm.*,
            olt.name as olt_name,
            olt.ip_address as olt_ip
        FROM odc_pon_mapping opm
        LEFT JOIN ftth_items olt ON opm.olt_item_id = olt.id
        WHERE opm.status = 'active'
    ";
    
    if ($odc_id) {
        $query .= " AND opm.odc_item_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$odc_id]);
    } else {
        $stmt = $db->prepare($query);
        $stmt->execute();
    }
    
    $mappings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(array(
        'success' => true,
        'data' => $mappings,
        'total' => count($mappings)
    ));
}

// Get ODC capacity information
function getOdcCapacity($db) {
    $odc_id = isset($_GET['odc_id']) ? intval($_GET['odc_id']) : 0;
    
    if (!$odc_id) {
        http_response_code(400);
        echo json_encode(array('success' => false, 'message' => 'ODC ID required'));
        return;
    }
    
    $query = "
        SELECT 
            i.id,
            i.name,
            i.odc_capacity,
            i.odc_ports_used,
            i.odc_main_splitter_ratio,
            i.odc_odp_splitter_ratio,
            i.odc_input_ports,
            i.odc_output_ports,
            (i.odc_capacity - i.odc_ports_used * 8) as available_capacity,
            (i.odc_ports_used * 8) as used_capacity,
            ROUND((i.odc_ports_used * 8 / i.odc_capacity) * 100, 2) as utilization_percentage
        FROM ftth_items i
        WHERE i.id = ? AND i.item_type_id IN (4, 12)
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$odc_id]);
    $capacity = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$capacity) {
        http_response_code(404);
        echo json_encode(array('success' => false, 'message' => 'ODC not found'));
        return;
    }
    
    echo json_encode(array(
        'success' => true,
        'data' => $capacity
    ));
}

// Get ODC statistics
function getOdcStatistics($db) {
    $query = "
        SELECT 
            COUNT(*) as total_odcs,
            SUM(CASE WHEN odc_type = 'pole_mounted' THEN 1 ELSE 0 END) as pole_mounted_count,
            SUM(CASE WHEN odc_type = 'ground_mounted' THEN 1 ELSE 0 END) as ground_mounted_count,
            SUM(odc_capacity) as total_capacity,
            SUM(odc_ports_used * 8) as total_used_capacity,
            ROUND(AVG(odc_ports_used * 8 / odc_capacity) * 100, 2) as avg_utilization
        FROM ftth_items 
        WHERE item_type_id IN (4, 12)
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(array(
        'success' => true,
        'data' => $stats
    ));
}

// Create new ODC
function createOdc($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required_fields = ['item_type_id', 'name', 'latitude', 'longitude'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            http_response_code(400);
            echo json_encode(array('success' => false, 'message' => "Field $field is required"));
            return;
        }
    }
    
    // Validate ODC type
    if (!in_array($input['item_type_id'], [4, 12])) {
        http_response_code(400);
        echo json_encode(array('success' => false, 'message' => 'Invalid ODC type'));
        return;
    }
    
    $query = "
        INSERT INTO ftth_items (
            item_type_id, odc_type, odc_capacity, odc_installation_type,
            name, description, latitude, longitude, address,
            odc_main_splitter_ratio, odc_odp_splitter_ratio,
            odc_input_ports, odc_output_ports, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";
    
    $stmt = $db->prepare($query);
    $result = $stmt->execute([
        $input['item_type_id'],
        $input['odc_type'] ?? 'pole_mounted',
        $input['odc_capacity'] ?? 32,
        $input['odc_installation_type'] ?? 'pole',
        $input['name'],
        $input['description'] ?? '',
        $input['latitude'],
        $input['longitude'],
        $input['address'] ?? '',
        $input['odc_main_splitter_ratio'] ?? '1:4',
        $input['odc_odp_splitter_ratio'] ?? '1:8',
        $input['odc_input_ports'] ?? 1,
        $input['odc_output_ports'] ?? 4,
        $input['status'] ?? 'active'
    ]);
    
    if ($result) {
        $odc_id = $db->lastInsertId();
        
        // Create default ports
        createDefaultOdcPorts($db, $odc_id, $input['odc_input_ports'] ?? 1, $input['odc_output_ports'] ?? 4);
        
        echo json_encode(array(
            'success' => true,
            'message' => 'ODC created successfully',
            'data' => array('id' => $odc_id)
        ));
    } else {
        http_response_code(500);
        echo json_encode(array('success' => false, 'message' => 'Failed to create ODC'));
    }
}

// Create default ODC ports
function createDefaultOdcPorts($db, $odc_id, $input_ports, $output_ports) {
    // Create input ports
    for ($i = 1; $i <= $input_ports; $i++) {
        $stmt = $db->prepare("
            INSERT INTO odc_ports (odc_item_id, port_number, port_type, port_status, notes)
            VALUES (?, ?, 'input', 'available', 'Input port ' . ?)
        ");
        $stmt->execute([$odc_id, $i, $i]);
    }
    
    // Create output ports
    for ($i = 1; $i <= $output_ports; $i++) {
        $stmt = $db->prepare("
            INSERT INTO odc_ports (odc_item_id, port_number, port_type, port_status, notes)
            VALUES (?, ?, 'output', 'available', 'Output port ' . ?)
        ");
        $stmt->execute([$odc_id, $input_ports + $i, $i]);
    }
}

// Update ODC
function updateOdc($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id'])) {
        http_response_code(400);
        echo json_encode(array('success' => false, 'message' => 'ODC ID required'));
        return;
    }
    
    $query = "
        UPDATE ftth_items SET
            odc_type = ?,
            odc_capacity = ?,
            odc_installation_type = ?,
            name = ?,
            description = ?,
            latitude = ?,
            longitude = ?,
            address = ?,
            odc_main_splitter_ratio = ?,
            odc_odp_splitter_ratio = ?,
            odc_input_ports = ?,
            odc_output_ports = ?,
            status = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ? AND item_type_id IN (4, 12)
    ";
    
    $stmt = $db->prepare($query);
    $result = $stmt->execute([
        $input['odc_type'] ?? 'pole_mounted',
        $input['odc_capacity'] ?? 32,
        $input['odc_installation_type'] ?? 'pole',
        $input['name'],
        $input['description'] ?? '',
        $input['latitude'],
        $input['longitude'],
        $input['address'] ?? '',
        $input['odc_main_splitter_ratio'] ?? '1:4',
        $input['odc_odp_splitter_ratio'] ?? '1:8',
        $input['odc_input_ports'] ?? 1,
        $input['odc_output_ports'] ?? 4,
        $input['status'] ?? 'active',
        $input['id']
    ]);
    
    if ($result) {
        echo json_encode(array(
            'success' => true,
            'message' => 'ODC updated successfully'
        ));
    } else {
        http_response_code(500);
        echo json_encode(array('success' => false, 'message' => 'Failed to update ODC'));
    }
}

// Update ODC ports
function updateOdcPorts($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['odc_id']) || !isset($input['ports'])) {
        http_response_code(400);
        echo json_encode(array('success' => false, 'message' => 'ODC ID and ports data required'));
        return;
    }
    
    $db->beginTransaction();
    
    try {
        foreach ($input['ports'] as $port) {
            $stmt = $db->prepare("
                UPDATE odc_ports SET
                    port_status = ?,
                    connected_to_item_id = ?,
                    connected_to_port = ?,
                    attenuation_dbm = ?,
                    notes = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE odc_item_id = ? AND port_number = ?
            ");
            
            $stmt->execute([
                $port['port_status'] ?? 'available',
                $port['connected_to_item_id'] ?? null,
                $port['connected_to_port'] ?? null,
                $port['attenuation_dbm'] ?? null,
                $port['notes'] ?? '',
                $input['odc_id'],
                $port['port_number']
            ]);
        }
        
        // Update ODC ports used count
        $stmt = $db->prepare("
            UPDATE ftth_items SET
                odc_ports_used = (
                    SELECT COUNT(*) 
                    FROM odc_ports 
                    WHERE odc_item_id = ? AND port_status = 'connected'
                )
            WHERE id = ?
        ");
        $stmt->execute([$input['odc_id'], $input['odc_id']]);
        
        $db->commit();
        
        echo json_encode(array(
            'success' => true,
            'message' => 'ODC ports updated successfully'
        ));
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(array('success' => false, 'message' => 'Failed to update ODC ports: ' . $e->getMessage()));
    }
}

// Connect ODC to PON
function connectOdcToPon($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required_fields = ['odc_id', 'olt_id', 'pon_port'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            http_response_code(400);
            echo json_encode(array('success' => false, 'message' => "Field $field is required"));
            return;
        }
    }
    
    $query = "
        INSERT INTO odc_pon_mapping (
            odc_item_id, olt_item_id, pon_port, vlan_id, description, status
        ) VALUES (?, ?, ?, ?, ?, 'active')
        ON DUPLICATE KEY UPDATE
            vlan_id = VALUES(vlan_id),
            description = VALUES(description),
            status = 'active',
            updated_at = CURRENT_TIMESTAMP
    ";
    
    $stmt = $db->prepare($query);
    $result = $stmt->execute([
        $input['odc_id'],
        $input['olt_id'],
        $input['pon_port'],
        $input['vlan_id'] ?? null,
        $input['description'] ?? ''
    ]);
    
    if ($result) {
        echo json_encode(array(
            'success' => true,
            'message' => 'ODC connected to PON successfully'
        ));
    } else {
        http_response_code(500);
        echo json_encode(array('success' => false, 'message' => 'Failed to connect ODC to PON'));
    }
}

// Calculate ODC capacity
function calculateOdcCapacity($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['main_splitter']) || !isset($input['odp_splitter'])) {
        http_response_code(400);
        echo json_encode(array('success' => false, 'message' => 'Splitter ratios required'));
        return;
    }
    
    $main_ratio = intval(explode(':', $input['main_splitter'])[1]);
    $odp_ratio = intval(explode(':', $input['odp_splitter'])[1]);
    
    $total_capacity = $main_ratio * $odp_ratio;
    $output_ports = $main_ratio;
    
    echo json_encode(array(
        'success' => true,
        'data' => array(
            'total_capacity' => $total_capacity,
            'output_ports' => $output_ports,
            'customers_per_odp' => $odp_ratio,
            'main_splitter_ratio' => $input['main_splitter'],
            'odp_splitter_ratio' => $input['odp_splitter']
        )
    ));
}

// Delete ODC
function deleteOdc($db) {
    $odc_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if (!$odc_id) {
        http_response_code(400);
        echo json_encode(array('success' => false, 'message' => 'ODC ID required'));
        return;
    }
    
    $db->beginTransaction();
    
    try {
        // Delete ODC ports
        $stmt = $db->prepare("DELETE FROM odc_ports WHERE odc_item_id = ?");
        $stmt->execute([$odc_id]);
        
        // Delete ODC-PON mapping
        $stmt = $db->prepare("DELETE FROM odc_pon_mapping WHERE odc_item_id = ?");
        $stmt->execute([$odc_id]);
        
        // Delete ODC item
        $stmt = $db->prepare("DELETE FROM ftth_items WHERE id = ? AND item_type_id IN (4, 12)");
        $stmt->execute([$odc_id]);
        
        $db->commit();
        
        echo json_encode(array(
            'success' => true,
            'message' => 'ODC deleted successfully'
        ));
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(array('success' => false, 'message' => 'Failed to delete ODC: ' . $e->getMessage()));
    }
}

// Disconnect ODC from PON
function disconnectOdcFromPon($db) {
    $odc_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if (!$odc_id) {
        http_response_code(400);
        echo json_encode(array('success' => false, 'message' => 'ODC ID required'));
        return;
    }
    
    $stmt = $db->prepare("DELETE FROM odc_pon_mapping WHERE odc_item_id = ?");
    $result = $stmt->execute([$odc_id]);
    
    if ($result) {
        echo json_encode(array(
            'success' => true,
            'message' => 'ODC disconnected from PON successfully'
        ));
    } else {
        http_response_code(500);
        echo json_encode(array('success' => false, 'message' => 'Failed to disconnect ODC from PON'));
    }
}
?>
