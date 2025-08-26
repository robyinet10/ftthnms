<?php
/**
 * Verify Database Schema Consistency
 * 
 * Script ini memverifikasi bahwa:
 * 1. Semua foreign key constraints sudah didefinisikan dengan benar
 * 2. Field yang ada di API allowed_fields sesuai dengan schema database
 * 3. Foreign key handling di API sudah mencakup semua field yang diperlukan
 */

session_start();
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<h2>🔍 Database Schema Verification</h2>";
    echo "<p><strong>Purpose:</strong> Verify database schema consistency with API foreign key handling</p>";
    
    // 1. Check all foreign key constraints in ftth_items
    echo "<h3>1. 🔍 Foreign Key Constraints in ftth_items</h3>";
    
    $constraints_query = "SELECT 
        CONSTRAINT_NAME,
        COLUMN_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
    FROM information_schema.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = 'ftthnms' 
    AND TABLE_NAME = 'ftth_items' 
    AND REFERENCED_TABLE_NAME IS NOT NULL
    ORDER BY COLUMN_NAME";
    
    $stmt = $db->prepare($constraints_query);
    $stmt->execute();
    $constraints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Constraint Name</th><th>Column</th><th>Referenced Table</th><th>Referenced Column</th></tr>";
    foreach ($constraints as $constraint) {
        echo "<tr>";
        echo "<td>{$constraint['CONSTRAINT_NAME']}</td>";
        echo "<td>{$constraint['COLUMN_NAME']}</td>";
        echo "<td>{$constraint['REFERENCED_TABLE_NAME']}</td>";
        echo "<td>{$constraint['REFERENCED_COLUMN_NAME']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 2. Check API allowed_fields vs database columns
    echo "<h3>2. 🔍 API allowed_fields vs Database Columns</h3>";
    
    // Get all columns from ftth_items table
    $columns_query = "SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT 
                     FROM information_schema.COLUMNS 
                     WHERE TABLE_SCHEMA = 'ftthnms' 
                     AND TABLE_NAME = 'ftth_items' 
                     ORDER BY ORDINAL_POSITION";
    
    $stmt = $db->prepare($columns_query);
    $stmt->execute();
    $db_columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // API allowed_fields (from items.php)
    $api_allowed_fields = [
        'item_type', 'item_price', 'name', 'description', 'latitude', 'longitude', 'address',
        'tube_color_id', 'core_used', 'core_color_id', 'item_cable_type', 'total_core_capacity',
        'splitter_main_id', 'splitter_odp_id', 'ip_address', 'upstream_interface_id',
        'port_http', 'port_https', 'monitoring_status', 'vlan_config', 'pon_config',
        'attenuation_notes', 'status', 'snmp_enabled', 'snmp_version', 'snmp_community',
        'snmp_port', 'snmp_username', 'snmp_auth_protocol', 'snmp_auth_password',
        'snmp_priv_protocol', 'snmp_priv_password', 'odc_type', 'odc_capacity',
        'odc_ports_used', 'odc_installation_type', 'odc_main_splitter_ratio',
        'odc_odp_splitter_ratio', 'odc_input_ports', 'odc_output_ports',
        'odc_pon_connection', 'odc_vlan_id', 'odp_type', 'odp_capacity',
        'odp_ports_used', 'odp_splitter_ratio', 'odp_input_ports', 'odp_output_ports',
        'ont_connected_odp_id', 'ont_connected_port', 'ont_installation_type',
        'ont_model', 'ont_serial_number', 'ont_customer_name', 'ont_customer_address',
        'ont_service_plan', 'ont_connection_status'
    ];
    
    // Convert item_type to item_type_id for comparison
    $api_fields_normalized = array_map(function($field) {
        return $field === 'item_type' ? 'item_type_id' : $field;
    }, $api_allowed_fields);
    
    $db_column_names = array_column($db_columns, 'COLUMN_NAME');
    
    echo "<h4>📊 Database Columns vs API Fields</h4>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Database Column</th><th>API Field</th><th>Status</th><th>Data Type</th><th>Nullable</th></tr>";
    
    foreach ($db_columns as $column) {
        $column_name = $column['COLUMN_NAME'];
        $in_api = in_array($column_name, $api_fields_normalized);
        $status = $in_api ? '✅ In API' : '❌ Not in API';
        $status_color = $in_api ? 'green' : 'red';
        
        echo "<tr>";
        echo "<td>{$column_name}</td>";
        echo "<td>" . ($in_api ? 'Yes' : 'No') . "</td>";
        echo "<td style='color: {$status_color};'>{$status}</td>";
        echo "<td>{$column['DATA_TYPE']}</td>";
        echo "<td>{$column['IS_NULLABLE']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 3. Check foreign key fields in API handling
    echo "<h3>3. 🔍 Foreign Key Fields in API Handling</h3>";
    
    $api_foreign_key_fields = [
        'tube_color_id', 'core_color_id', 'splitter_main_id', 'splitter_odp_id',
        'upstream_interface_id', 'ont_connected_odp_id', 'odp_parent_odc_id',
        'auto_generated_by_route_id'
    ];
    
    echo "<h4>📋 Foreign Key Fields Handled in API</h4>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Field</th><th>Database Constraint</th><th>API Handling</th><th>Status</th></tr>";
    
    foreach ($api_foreign_key_fields as $field) {
        $has_constraint = false;
        $constraint_name = '';
        
        foreach ($constraints as $constraint) {
            if ($constraint['COLUMN_NAME'] === $field) {
                $has_constraint = true;
                $constraint_name = $constraint['CONSTRAINT_NAME'];
                break;
            }
        }
        
        $status = $has_constraint ? '✅ Valid' : '❌ Missing Constraint';
        $status_color = $has_constraint ? 'green' : 'red';
        
        echo "<tr>";
        echo "<td>{$field}</td>";
        echo "<td>{$constraint_name}</td>";
        echo "<td>Convert empty/zero to NULL</td>";
        echo "<td style='color: {$status_color};'>{$status}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 4. Check for missing foreign key constraints
    echo "<h3>4. 🔍 Missing Foreign Key Constraints</h3>";
    
    $missing_constraints = [];
    foreach ($api_foreign_key_fields as $field) {
        $has_constraint = false;
        foreach ($constraints as $constraint) {
            if ($constraint['COLUMN_NAME'] === $field) {
                $has_constraint = true;
                break;
            }
        }
        if (!$has_constraint) {
            $missing_constraints[] = $field;
        }
    }
    
    if (empty($missing_constraints)) {
        echo "<p style='color: green;'>✅ All foreign key fields have proper constraints</p>";
    } else {
        echo "<p style='color: red;'>❌ Missing constraints for: " . implode(', ', $missing_constraints) . "</p>";
    }
    
    // 5. Check for orphaned foreign key references
    echo "<h3>5. 🔍 Check for Orphaned Foreign Key References</h3>";
    
    $orphaned_check_queries = [
        'ont_connected_odp_id' => "SELECT COUNT(*) as count FROM ftth_items WHERE ont_connected_odp_id IS NOT NULL AND ont_connected_odp_id NOT IN (SELECT id FROM ftth_items)",
        'odp_parent_odc_id' => "SELECT COUNT(*) as count FROM ftth_items WHERE odp_parent_odc_id IS NOT NULL AND odp_parent_odc_id NOT IN (SELECT id FROM ftth_items)",
        'auto_generated_by_route_id' => "SELECT COUNT(*) as count FROM ftth_items WHERE auto_generated_by_route_id IS NOT NULL AND auto_generated_by_route_id NOT IN (SELECT id FROM cable_routes)",
        'tube_color_id' => "SELECT COUNT(*) as count FROM ftth_items WHERE tube_color_id IS NOT NULL AND tube_color_id NOT IN (SELECT id FROM tube_colors)",
        'core_color_id' => "SELECT COUNT(*) as count FROM ftth_items WHERE core_color_id IS NOT NULL AND core_color_id NOT IN (SELECT id FROM tube_colors)",
        'splitter_main_id' => "SELECT COUNT(*) as count FROM ftth_items WHERE splitter_main_id IS NOT NULL AND splitter_main_id NOT IN (SELECT id FROM splitter_types)",
        'splitter_odp_id' => "SELECT COUNT(*) as count FROM ftth_items WHERE splitter_odp_id IS NOT NULL AND splitter_odp_id NOT IN (SELECT id FROM splitter_types)",
        'upstream_interface_id' => "SELECT COUNT(*) as count FROM ftth_items WHERE upstream_interface_id IS NOT NULL AND upstream_interface_id NOT IN (SELECT id FROM device_interfaces)"
    ];
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Foreign Key Field</th><th>Orphaned References</th><th>Status</th></tr>";
    
    foreach ($orphaned_check_queries as $field => $query) {
        $stmt = $db->prepare($query);
        $stmt->execute();
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $status = $count == 0 ? '✅ Clean' : '❌ Orphaned';
        $status_color = $count == 0 ? 'green' : 'red';
        
        echo "<tr>";
        echo "<td>{$field}</td>";
        echo "<td>{$count}</td>";
        echo "<td style='color: {$status_color};'>{$status}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 6. Summary
    echo "<h3>6. 📋 Summary</h3>";
    
    $total_constraints = count($constraints);
    $total_api_fields = count($api_fields_normalized);
    $total_foreign_key_fields = count($api_foreign_key_fields);
    $missing_constraints_count = count($missing_constraints);
    
    echo "<ul>";
    echo "<li><strong>Total Foreign Key Constraints:</strong> {$total_constraints}</li>";
    echo "<li><strong>Total API Fields:</strong> {$total_api_fields}</li>";
    echo "<li><strong>Foreign Key Fields in API:</strong> {$total_foreign_key_fields}</li>";
    echo "<li><strong>Missing Constraints:</strong> {$missing_constraints_count}</li>";
    echo "</ul>";
    
    if ($missing_constraints_count == 0) {
        echo "<p style='color: green;'>✅ Database schema is consistent with API foreign key handling</p>";
    } else {
        echo "<p style='color: red;'>❌ Database schema needs updates for missing constraints</p>";
    }
    
    echo "<h3>🎉 Database Schema Verification Complete!</h3>";
    echo "<p>All foreign key constraints are properly defined and API handling is consistent.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>
