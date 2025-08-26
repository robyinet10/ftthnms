<?php
// Enhanced FTTH Integration API
// Handles OLT-ODC-ODP integration features

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

$response = array('success' => false, 'message' => '', 'data' => null);

try {
    // Check authentication for all requests
    if (!checkPermission()) {
        http_response_code(401);
        echo json_encode(array('success' => false, 'message' => 'Authentication required'));
        exit();
    }

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';

    switch($method) {
        case 'GET':
            switch($action) {
                case 'get_available_odc_ports':
                    // Get available ODC ports for ODP configuration
                    $query = "SELECT 
                                odc.id as odc_id,
                                odc.name as odc_name,
                                odc.odc_output_ports,
                                COALESCE(used_ports.used_count, 0) as used_ports,
                                (odc.odc_output_ports - COALESCE(used_ports.used_count, 0)) as available_ports
                             FROM ftth_items odc
                             LEFT JOIN (
                                SELECT odc_item_id, COUNT(*) as used_count 
                                FROM odp_odc_mapping 
                                WHERE status = 'active' 
                                GROUP BY odc_item_id
                             ) used_ports ON odc.id = used_ports.odc_item_id
                             WHERE odc.item_type_id IN (4, 12) 
                             AND odc.status = 'active'
                             ORDER BY odc.name";
                    
                    $stmt = $db->prepare($query);
                    $stmt->execute();
                    $available_odcs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $response['success'] = true;
                    $response['data'] = $available_odcs;
                    break;

                case 'get_olt_pon_chain':
                    // Get complete OLT-ODC-ODP chain for integration view
                    $query = "SELECT * FROM olt_odc_odp_chain ORDER BY olt_name, pon_port, odc_name";
                    
                    $stmt = $db->prepare($query);
                    $stmt->execute();
                    $chain_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $response['success'] = true;
                    $response['data'] = $chain_data;
                    break;

                case 'get_olt_pon_interfaces':
                    // Get OLT PON interface mappings
                    $olt_id = $_GET['olt_id'] ?? null;
                    
                    if ($olt_id) {
                        $query = "SELECT * FROM olt_pon_interfaces WHERE olt_item_id = :olt_id ORDER BY pon_port";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':olt_id', $olt_id);
                    } else {
                        $query = "SELECT opi.*, olt.name as olt_name 
                                 FROM olt_pon_interfaces opi
                                 JOIN ftth_items olt ON opi.olt_item_id = olt.id
                                 ORDER BY olt.name, opi.pon_port";
                        $stmt = $db->prepare($query);
                    }
                    
                    $stmt->execute();
                    $pon_interfaces = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $response['success'] = true;
                    $response['data'] = $pon_interfaces;
                    break;

                case 'get_odp_ports':
                    // Get ODP port status and usage
                    $odp_id = $_GET['odp_id'] ?? null;
                    
                    if ($odp_id) {
                        $query = "SELECT * FROM odp_ports WHERE odp_item_id = :odp_id ORDER BY port_number";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':odp_id', $odp_id);
                    } else {
                        $query = "SELECT op.*, odp.name as odp_name 
                                 FROM odp_ports op
                                 JOIN ftth_items odp ON op.odp_item_id = odp.id
                                 ORDER BY odp.name, op.port_number";
                        $stmt = $db->prepare($query);
                    }
                    
                    $stmt->execute();
                    $odp_ports = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $response['success'] = true;
                    $response['data'] = $odp_ports;
                    break;

                default:
                    $response['message'] = 'Invalid action';
                    break;
            }
            break;

        case 'POST':
            switch($action) {
                case 'create_olt_pon_interface':
                    // Create OLT PON interface mapping
                    $olt_item_id = $_POST['olt_item_id'] ?? null;
                    $pon_port = $_POST['pon_port'] ?? null;
                    $interface_id = $_POST['interface_id'] ?? null;
                    $vlan_id = $_POST['vlan_id'] ?? null;
                    
                    if (!$olt_item_id || !$pon_port) {
                        $response['message'] = 'OLT ID and PON port are required';
                        break;
                    }
                    
                    $query = "INSERT INTO olt_pon_interfaces (olt_item_id, pon_port, interface_id, vlan_id, status) 
                             VALUES (:olt_item_id, :pon_port, :interface_id, :vlan_id, 'active')";
                    
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':olt_item_id', $olt_item_id);
                    $stmt->bindParam(':pon_port', $pon_port);
                    $stmt->bindParam(':interface_id', $interface_id);
                    $stmt->bindParam(':vlan_id', $vlan_id);
                    
                    if ($stmt->execute()) {
                        $response['success'] = true;
                        $response['data'] = ['id' => $db->lastInsertId()];
                        $response['message'] = 'OLT PON interface mapping created successfully';
                    } else {
                        $response['message'] = 'Failed to create OLT PON interface mapping';
                    }
                    break;

                case 'create_odp_odc_mapping':
                    // Create ODP-ODC mapping
                    $odp_item_id = $_POST['odp_item_id'] ?? null;
                    $odc_item_id = $_POST['odc_item_id'] ?? null;
                    $odc_output_port = $_POST['odc_output_port'] ?? null;
                    $odp_input_port = $_POST['odp_input_port'] ?? 1;
                    $cable_length_m = $_POST['cable_length_m'] ?? null;
                    $attenuation_dbm = $_POST['attenuation_dbm'] ?? null;
                    $description = $_POST['description'] ?? null;
                    
                    if (!$odp_item_id || !$odc_item_id || !$odc_output_port) {
                        $response['message'] = 'ODP ID, ODC ID, and output port are required';
                        break;
                    }
                    
                    $query = "INSERT INTO odp_odc_mapping (odp_item_id, odc_item_id, odc_output_port, odp_input_port, cable_length_m, attenuation_dbm, description, status) 
                             VALUES (:odp_item_id, :odc_item_id, :odc_output_port, :odp_input_port, :cable_length_m, :attenuation_dbm, :description, 'active')";
                    
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':odp_item_id', $odp_item_id);
                    $stmt->bindParam(':odc_item_id', $odc_item_id);
                    $stmt->bindParam(':odc_output_port', $odc_output_port);
                    $stmt->bindParam(':odp_input_port', $odp_input_port);
                    $stmt->bindParam(':cable_length_m', $cable_length_m);
                    $stmt->bindParam(':attenuation_dbm', $attenuation_dbm);
                    $stmt->bindParam(':description', $description);
                    
                    if ($stmt->execute()) {
                        $response['success'] = true;
                        $response['data'] = ['id' => $db->lastInsertId()];
                        $response['message'] = 'ODP-ODC mapping created successfully';
                    } else {
                        $response['message'] = 'Failed to create ODP-ODC mapping';
                    }
                    break;

                case 'create_odp_port':
                    // Create ODP port configuration
                    $odp_item_id = $_POST['odp_item_id'] ?? null;
                    $port_number = $_POST['port_number'] ?? null;
                    $port_type = $_POST['port_type'] ?? 'output';
                    $ont_serial_number = $_POST['ont_serial_number'] ?? null;
                    $customer_info = $_POST['customer_info'] ?? null;
                    $port_status = $_POST['port_status'] ?? 'available';
                    
                    if (!$odp_item_id || !$port_number) {
                        $response['message'] = 'ODP ID and port number are required';
                        break;
                    }
                    
                    $query = "INSERT INTO odp_ports (odp_item_id, port_number, port_type, port_status, ont_serial_number, customer_info) 
                             VALUES (:odp_item_id, :port_number, :port_type, :port_status, :ont_serial_number, :customer_info)";
                    
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':odp_item_id', $odp_item_id);
                    $stmt->bindParam(':port_number', $port_number);
                    $stmt->bindParam(':port_type', $port_type);
                    $stmt->bindParam(':port_status', $port_status);
                    $stmt->bindParam(':ont_serial_number', $ont_serial_number);
                    $stmt->bindParam(':customer_info', $customer_info);
                    
                    if ($stmt->execute()) {
                        $response['success'] = true;
                        $response['data'] = ['id' => $db->lastInsertId()];
                        $response['message'] = 'ODP port created successfully';
                    } else {
                        $response['message'] = 'Failed to create ODP port';
                    }
                    break;

                default:
                    $response['message'] = 'Invalid action';
                    break;
            }
            break;

        case 'PUT':
            switch($action) {
                case 'update_odp_port':
                    // Update ODP port configuration
                    $port_id = $_POST['port_id'] ?? null;
                    $port_status = $_POST['port_status'] ?? null;
                    $ont_serial_number = $_POST['ont_serial_number'] ?? null;
                    $customer_info = $_POST['customer_info'] ?? null;
                    
                    if (!$port_id) {
                        $response['message'] = 'Port ID is required';
                        break;
                    }
                    
                    $update_fields = [];
                    $params = [':id' => $port_id];
                    
                    if ($port_status !== null) {
                        $update_fields[] = 'port_status = :port_status';
                        $params[':port_status'] = $port_status;
                    }
                    
                    if ($ont_serial_number !== null) {
                        $update_fields[] = 'ont_serial_number = :ont_serial_number';
                        $params[':ont_serial_number'] = $ont_serial_number;
                    }
                    
                    if ($customer_info !== null) {
                        $update_fields[] = 'customer_info = :customer_info';
                        $params[':customer_info'] = $customer_info;
                    }
                    
                    if (empty($update_fields)) {
                        $response['message'] = 'No fields to update';
                        break;
                    }
                    
                    $query = "UPDATE odp_ports SET " . implode(', ', $update_fields) . ", updated_at = NOW() WHERE id = :id";
                    
                    $stmt = $db->prepare($query);
                    
                    if ($stmt->execute($params)) {
                        $response['success'] = true;
                        $response['message'] = 'ODP port updated successfully';
                    } else {
                        $response['message'] = 'Failed to update ODP port';
                    }
                    break;

                default:
                    $response['message'] = 'Invalid action';
                    break;
            }
            break;

        default:
            $response['message'] = 'Method not allowed';
            break;
    }

} catch (Exception $e) {
    error_log('Integration API Error: ' . $e->getMessage());
    $response['message'] = 'Internal server error: ' . $e->getMessage();
}

echo json_encode($response);
?>
