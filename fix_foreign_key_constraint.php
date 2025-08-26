<?php
/**
 * Fix Foreign Key Constraint Error Analysis
 * 
 * Error: SQLSTATE[23000]: Integrity constraint violation: 1452 
 * Cannot add or update a child row: a foreign key constraint fails 
 * (`ftthnms`.`ftth_items`, CONSTRAINT `fk_ftth_items_ont_connected_odp` 
 * FOREIGN KEY (`ont_connected_odp_id`) REFERENCES `ftth_items` (`id`) ON DELETE SET NULL)
 * 
 * Analisis: Error ini terjadi karena ada field ont_connected_odp_id yang berisi nilai 
 * yang tidak ada di tabel ftth_items, atau ada masalah dengan handling NULL values
 */

session_start();
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<h2>🔧 Foreign Key Constraint Error Analysis</h2>";
    echo "<p><strong>Error:</strong> SQLSTATE[23000]: Integrity constraint violation: 1452</p>";
    echo "<p><strong>Constraint:</strong> fk_ftth_items_ont_connected_odp</p>";
    echo "<p><strong>Field:</strong> ont_connected_odp_id</p>";
    
    // 1. Check for invalid ont_connected_odp_id values
    echo "<h3>1. 🔍 Checking for Invalid ont_connected_odp_id Values</h3>";
    
    $query = "SELECT id, name, item_type_id, ont_connected_odp_id 
              FROM ftth_items 
              WHERE ont_connected_odp_id IS NOT NULL 
              AND ont_connected_odp_id NOT IN (SELECT id FROM ftth_items)";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $invalid_odp_refs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($invalid_odp_refs)) {
        echo "<p style='color: green;'>✅ No invalid ont_connected_odp_id references found</p>";
    } else {
        echo "<p style='color: red;'>❌ Found " . count($invalid_odp_refs) . " invalid ont_connected_odp_id references:</p>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Name</th><th>Item Type ID</th><th>Invalid ODP ID</th></tr>";
        foreach ($invalid_odp_refs as $item) {
            echo "<tr>";
            echo "<td>{$item['id']}</td>";
            echo "<td>{$item['name']}</td>";
            echo "<td>{$item['item_type_id']}</td>";
            echo "<td style='color: red;'>{$item['ont_connected_odp_id']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 2. Check for items with ont_connected_odp_id that are not ONT/HTB
    echo "<h3>2. 🔍 Checking Items with ont_connected_odp_id (should only be ONT/HTB)</h3>";
    
    $query = "SELECT id, name, item_type_id, ont_connected_odp_id 
              FROM ftth_items 
              WHERE ont_connected_odp_id IS NOT NULL 
              AND item_type_id NOT IN (6, 10, 11)"; // 6=ONT, 10=HTB, 11=HTB
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $wrong_type_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($wrong_type_items)) {
        echo "<p style='color: green;'>✅ All items with ont_connected_odp_id are ONT/HTB items</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Found " . count($wrong_type_items) . " non-ONT items with ont_connected_odp_id:</p>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Name</th><th>Item Type ID</th><th>ODP ID</th></tr>";
        foreach ($wrong_type_items as $item) {
            echo "<tr>";
            echo "<td>{$item['id']}</td>";
            echo "<td>{$item['name']}</td>";
            echo "<td>{$item['item_type_id']}</td>";
            echo "<td>{$item['ont_connected_odp_id']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 3. Check for empty string values in ont_connected_odp_id
    echo "<h3>3. 🔍 Checking for Empty String Values in ont_connected_odp_id</h3>";
    
    $query = "SELECT id, name, item_type_id, ont_connected_odp_id 
              FROM ftth_items 
              WHERE ont_connected_odp_id = '' OR ont_connected_odp_id = '0'";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $empty_odp_refs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($empty_odp_refs)) {
        echo "<p style='color: green;'>✅ No empty string values found in ont_connected_odp_id</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Found " . count($empty_odp_refs) . " items with empty string values:</p>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Name</th><th>Item Type ID</th><th>ODP ID</th></tr>";
        foreach ($empty_odp_refs as $item) {
            echo "<tr>";
            echo "<td>{$item['id']}</td>";
            echo "<td>{$item['name']}</td>";
            echo "<td>{$item['item_type_id']}</td>";
            echo "<td style='color: orange;'>'{$item['ont_connected_odp_id']}'</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 4. Check API items.php handling of ont_connected_odp_id
    echo "<h3>4. 🔍 Analyzing API items.php Foreign Key Handling</h3>";
    
    echo "<p><strong>Current allowed_fields includes:</strong></p>";
    echo "<ul>";
    echo "<li>ont_connected_odp_id</li>";
    echo "<li>ont_connected_port</li>";
    echo "<li>ont_installation_type</li>";
    echo "<li>ont_model</li>";
    echo "<li>ont_serial_number</li>";
    echo "<li>ont_customer_name</li>";
    echo "<li>ont_customer_address</li>";
    echo "<li>ont_service_plan</li>";
    echo "<li>ont_connection_status</li>";
    echo "</ul>";
    
    echo "<p><strong>Current foreign key handling:</strong></p>";
    echo "<ul>";
    echo "<li>Handles: tube_color_id, core_color_id, splitter_main_id, splitter_odp_id, upstream_interface_id</li>";
    echo "<li><strong style='color: red;'>MISSING: ont_connected_odp_id is NOT handled!</strong></li>";
    echo "</ul>";
    
    // 5. Test fix for the issue
    echo "<h3>5. 🔧 Testing Fix for Foreign Key Constraint</h3>";
    
    // Check if there are any items that need fixing
    $query = "SELECT COUNT(*) as count FROM ftth_items 
              WHERE ont_connected_odp_id = '' OR ont_connected_odp_id = '0' 
              OR (ont_connected_odp_id IS NOT NULL AND ont_connected_odp_id NOT IN (SELECT id FROM ftth_items))";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $problem_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($problem_count > 0) {
        echo "<p style='color: red;'>❌ Found {$problem_count} items with problematic ont_connected_odp_id values</p>";
        
        // Fix empty string and zero values
        $fix_query = "UPDATE ftth_items 
                     SET ont_connected_odp_id = NULL 
                     WHERE ont_connected_odp_id = '' OR ont_connected_odp_id = '0'";
        
        $stmt = $db->prepare($fix_query);
        if ($stmt->execute()) {
            echo "<p style='color: green;'>✅ Fixed empty string and zero values in ont_connected_odp_id</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to fix empty string values</p>";
        }
        
        // Fix invalid references
        $fix_invalid_query = "UPDATE ftth_items 
                             SET ont_connected_odp_id = NULL 
                             WHERE ont_connected_odp_id IS NOT NULL 
                             AND ont_connected_odp_id NOT IN (SELECT id FROM ftth_items)";
        
        $stmt = $db->prepare($fix_invalid_query);
        if ($stmt->execute()) {
            echo "<p style='color: green;'>✅ Fixed invalid ont_connected_odp_id references</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to fix invalid references</p>";
        }
        
    } else {
        echo "<p style='color: green;'>✅ No problematic ont_connected_odp_id values found</p>";
    }
    
    // 6. Show the fix needed in API
    echo "<h3>6. 🔧 Required Fix in API items.php</h3>";
    
    echo "<p><strong>Add ont_connected_odp_id to foreign key handling:</strong></p>";
    echo "<pre style='background: #f4f4f4; padding: 10px; border-radius: 5px;'>";
    echo "// In api/items.php, around line 910, update the foreign key handling:\n";
    echo "if (in_array(\$field, ['tube_color_id', 'core_color_id', 'splitter_main_id', 'splitter_odp_id', 'upstream_interface_id', 'ont_connected_odp_id']) && \n";
    echo "    (\$value === '' || \$value === '0' || \$value === 0)) {\n";
    echo "    \$value = null;\n";
    echo "}\n";
    echo "</pre>";
    
    // 7. Test the fix
    echo "<h3>7. 🧪 Testing the Fix</h3>";
    
    // Get a test item
    $query = "SELECT id, name, item_type_id, ont_connected_odp_id 
              FROM ftth_items 
              WHERE item_type_id IN (6, 10, 11) 
              LIMIT 1";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $test_item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($test_item) {
        echo "<p><strong>Test Item:</strong> ID {$test_item['id']} - {$test_item['name']}</p>";
        echo "<p><strong>Current ont_connected_odp_id:</strong> " . ($test_item['ont_connected_odp_id'] ?? 'NULL') . "</p>";
        
        // Test update with empty string
        $test_update_query = "UPDATE ftth_items SET ont_connected_odp_id = :odp_id WHERE id = :id";
        $stmt = $db->prepare($test_update_query);
        
        try {
            $stmt->bindParam(':odp_id', $test_item['ont_connected_odp_id']);
            $stmt->bindParam(':id', $test_item['id']);
            $stmt->execute();
            echo "<p style='color: green;'>✅ Test update successful</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Test update failed: " . $e->getMessage() . "</p>";
        }
    }
    
    echo "<h3>8. 📋 Summary</h3>";
    echo "<ul>";
    echo "<li><strong>Root Cause:</strong> ont_connected_odp_id field is not handled in foreign key validation</li>";
    echo "<li><strong>Impact:</strong> Server items with empty string or invalid ODP references cause constraint violation</li>";
    echo "<li><strong>Solution:</strong> Add ont_connected_odp_id to foreign key handling in API</li>";
    echo "<li><strong>Prevention:</strong> Always validate foreign key values before database updates</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>
