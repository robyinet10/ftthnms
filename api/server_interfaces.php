<?php
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_servers':
            // Get all server devices with interfaces
            $query = "SELECT 
                fi.id, 
                fi.name, 
                fi.ip_address,
                fi.item_type_id,
                COUNT(di.id) as interface_count
            FROM ftth_items fi 
            LEFT JOIN device_interfaces di ON fi.id = di.device_id
            WHERE fi.item_type_id = 7 AND fi.ip_address IS NOT NULL AND fi.ip_address != ''
            GROUP BY fi.id, fi.name, fi.ip_address, fi.item_type_id
            ORDER BY fi.name";
            
            $stmt = $db->prepare($query);
            $stmt->execute();
            $servers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'servers' => $servers
            ]);
            break;
            
        case 'get_interfaces':
            $server_id = intval($_GET['server_id'] ?? 0);
            
            if (!$server_id) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Server ID is required'
                ]);
                break;
            }
            
            // Get interfaces for specific server
            $query = "SELECT 
                di.id,
                di.interface_index,
                di.interface_name,
                di.interface_type,
                di.oper_status,
                di.admin_status,
                di.speed_bps,
                COALESCE(iia.ip_addresses, 'No IP') as ip_addresses
            FROM device_interfaces di
            LEFT JOIN (
                SELECT 
                    interface_id,
                    GROUP_CONCAT(ip_address SEPARATOR ', ') as ip_addresses
                FROM interface_ip_addresses 
                WHERE is_active = 1
                GROUP BY interface_id
            ) iia ON di.id = iia.interface_id
            WHERE di.device_id = :server_id 
            AND di.is_active = 1
            ORDER BY di.interface_index";
            
            $stmt = $db->prepare($query);
            $stmt->execute([':server_id' => $server_id]);
            $interfaces = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'interfaces' => $interfaces
            ]);
            break;
            
        case 'get_all_interfaces':
            // Get all interfaces from all servers for dropdown
            $query = "SELECT 
                di.id,
                di.device_id,
                fi.name as server_name,
                di.interface_name,
                di.interface_type,
                di.oper_status,
                COALESCE(iia.ip_addresses, 'No IP') as ip_addresses,
                CONCAT(fi.name, ' - ', di.interface_name, 
                    CASE 
                        WHEN iia.ip_addresses IS NOT NULL THEN CONCAT(' (', iia.ip_addresses, ')')
                        ELSE ' (No IP)'
                    END
                ) as display_name
            FROM device_interfaces di
            JOIN ftth_items fi ON di.device_id = fi.id
            LEFT JOIN (
                SELECT 
                    interface_id,
                    GROUP_CONCAT(ip_address SEPARATOR ', ') as ip_addresses
                FROM interface_ip_addresses 
                WHERE is_active = 1
                GROUP BY interface_id
            ) iia ON di.id = iia.interface_id
            WHERE fi.item_type_id = 7 
            AND di.is_active = 1
            AND di.oper_status = 'up'
            ORDER BY fi.name, di.interface_index";
            
            $stmt = $db->prepare($query);
            $stmt->execute();
            $interfaces = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'interfaces' => $interfaces
            ]);
            break;
            
        case 'get_upstream_interface':
            $interface_id = intval($_GET['interface_id'] ?? 0);
            
            if (!$interface_id) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Interface ID is required'
                ]);
                break;
            }
            
            // Get upstream interface details
            $query = "SELECT 
                di.id,
                di.device_id,
                di.interface_name,
                di.interface_type,
                di.oper_status,
                di.admin_status,
                di.speed_bps,
                fi.name as server_name,
                fi.ip_address as server_ip,
                COALESCE(iia.ip_addresses, 'No IP') as ip_addresses,
                COALESCE(iia.ip_count, 0) as ip_count
            FROM device_interfaces di
            JOIN ftth_items fi ON di.device_id = fi.id
            LEFT JOIN (
                SELECT 
                    interface_id,
                    GROUP_CONCAT(ip_address SEPARATOR ', ') as ip_addresses,
                    COUNT(ip_address) as ip_count
                FROM interface_ip_addresses 
                WHERE is_active = 1
                GROUP BY interface_id
            ) iia ON di.id = iia.interface_id
            WHERE di.id = :interface_id";
            
            $stmt = $db->prepare($query);
            $stmt->execute([':interface_id' => $interface_id]);
            $interface = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$interface) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Interface not found'
                ]);
                break;
            }
            
            echo json_encode([
                'success' => true,
                'interface' => $interface
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
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
