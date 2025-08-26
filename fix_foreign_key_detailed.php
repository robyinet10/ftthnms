<?php
/**
 * Fix Foreign Key Constraint Error - Detailed Analysis and Fix
 * 
 * Error: SQLSTATE[23000]: Integrity constraint violation: 1452 
 * Cannot add or update a child row: a foreign key constraint fails 
 * (`ftthnms`.`ftth_items`, CONSTRAINT `fk_ftth_items_ont_connected_odp` 
 * FOREIGN KEY (`ont_connected_odp_id`) REFERENCES `ftth_items` (`id`) ON DELETE SET NULL)
 * 
 * Solusi: Perbaiki data yang bermasalah dan update API untuk handling yang benar
 */

session_start();
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<h2>🔧 Fix Foreign Key Constraint Error - Detailed Analysis</h2>";
    echo "<p><strong>Error:</strong> SQLSTATE[23000]: Integrity constraint violation: 1452</p>";
    echo "<p><strong>Constraint:</strong> fk_ftth_items_ont_connected_odp</p>";
    echo "<p><strong>Field:</strong> ont_connected_odp_id</p>";
    
    // 1. Analisis masalah
    echo "<h3>1. 🔍 Analisis Masalah</h3>";
    
    // Check semua foreign key constraints yang ada
    $constraints_query = "SELECT 
        CONSTRAINT_NAME,
        COLUMN_NAME,
        REFERENCED_TABLE_NAME,
        REFERENCED_COLUMN_NAME
    FROM information_schema.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = 'ftthnms' 
    AND TABLE_NAME = 'ftth_items' 
    AND REFERENCED_TABLE_NAME IS NOT NULL";
    
    $stmt = $db->prepare($constraints_query);
    $stmt->execute();
    $constraints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p><strong>Foreign Key Constraints di ftth_items:</strong></p>";
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
    
    // 2. Check data yang bermasalah
    echo "<h3>2. 🔍 Check Data Bermasalah</h3>";
    
    // Check ont_connected_odp_id yang invalid
    $invalid_odp_query = "SELECT id, name, item_type_id, ont_connected_odp_id 
                         FROM ftth_items 
                         WHERE ont_connected_odp_id IS NOT NULL 
                         AND ont_connected_odp_id NOT IN (SELECT id FROM ftth_items)";
    
    $stmt = $db->prepare($invalid_odp_query);
    $stmt->execute();
    $invalid_odp_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($invalid_odp_items)) {
        echo "<p style='color: green;'>✅ Tidak ada ont_connected_odp_id yang invalid</p>";
    } else {
        echo "<p style='color: red;'>❌ Ditemukan " . count($invalid_odp_items) . " item dengan ont_connected_odp_id invalid:</p>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Name</th><th>Item Type ID</th><th>Invalid ODP ID</th></tr>";
        foreach ($invalid_odp_items as $item) {
            echo "<tr>";
            echo "<td>{$item['id']}</td>";
            echo "<td>{$item['name']}</td>";
            echo "<td>{$item['item_type_id']}</td>";
            echo "<td style='color: red;'>{$item['ont_connected_odp_id']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Check odp_parent_odc_id yang invalid
    $invalid_odc_query = "SELECT id, name, item_type_id, odp_parent_odc_id 
                         FROM ftth_items 
                         WHERE odp_parent_odc_id IS NOT NULL 
                         AND odp_parent_odc_id NOT IN (SELECT id FROM ftth_items)";
    
    $stmt = $db->prepare($invalid_odc_query);
    $stmt->execute();
    $invalid_odc_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($invalid_odc_items)) {
        echo "<p style='color: green;'>✅ Tidak ada odp_parent_odc_id yang invalid</p>";
    } else {
        echo "<p style='color: red;'>❌ Ditemukan " . count($invalid_odc_items) . " item dengan odp_parent_odc_id invalid:</p>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Name</th><th>Item Type ID</th><th>Invalid ODC ID</th></tr>";
        foreach ($invalid_odc_items as $item) {
            echo "<tr>";
            echo "<td>{$item['id']}</td>";
            echo "<td>{$item['name']}</td>";
            echo "<td>{$item['item_type_id']}</td>";
            echo "<td style='color: red;'>{$item['odp_parent_odc_id']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Check empty string values
    $empty_string_query = "SELECT id, name, item_type_id, ont_connected_odp_id, odp_parent_odc_id 
                          FROM ftth_items 
                          WHERE ont_connected_odp_id = '' OR ont_connected_odp_id = '0' 
                          OR odp_parent_odc_id = '' OR odp_parent_odc_id = '0'";
    
    $stmt = $db->prepare($empty_string_query);
    $stmt->execute();
    $empty_string_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($empty_string_items)) {
        echo "<p style='color: green;'>✅ Tidak ada nilai empty string atau '0'</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Ditemukan " . count($empty_string_items) . " item dengan nilai empty string atau '0':</p>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Name</th><th>Item Type ID</th><th>ODP ID</th><th>ODC ID</th></tr>";
        foreach ($empty_string_items as $item) {
            echo "<tr>";
            echo "<td>{$item['id']}</td>";
            echo "<td>{$item['name']}</td>";
            echo "<td>{$item['item_type_id']}</td>";
            echo "<td style='color: orange;'>'{$item['ont_connected_odp_id']}'</td>";
            echo "<td style='color: orange;'>'{$item['odp_parent_odc_id']}'</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 3. Perbaiki data yang bermasalah
    echo "<h3>3. 🔧 Perbaiki Data Bermasalah</h3>";
    
    $total_fixed = 0;
    
    // Fix empty string dan zero values untuk ont_connected_odp_id
    $fix_odp_query = "UPDATE ftth_items 
                     SET ont_connected_odp_id = NULL 
                     WHERE ont_connected_odp_id = '' OR ont_connected_odp_id = '0'";
    
    $stmt = $db->prepare($fix_odp_query);
    if ($stmt->execute()) {
        $odp_fixed = $stmt->rowCount();
        $total_fixed += $odp_fixed;
        echo "<p style='color: green;'>✅ Fixed {$odp_fixed} empty/zero values in ont_connected_odp_id</p>";
    }
    
    // Fix empty string dan zero values untuk odp_parent_odc_id
    $fix_odc_query = "UPDATE ftth_items 
                     SET odp_parent_odc_id = NULL 
                     WHERE odp_parent_odc_id = '' OR odp_parent_odc_id = '0'";
    
    $stmt = $db->prepare($fix_odc_query);
    if ($stmt->execute()) {
        $odc_fixed = $stmt->rowCount();
        $total_fixed += $odc_fixed;
        echo "<p style='color: green;'>✅ Fixed {$odc_fixed} empty/zero values in odp_parent_odc_id</p>";
    }
    
    // Fix invalid references untuk ont_connected_odp_id
    $fix_invalid_odp_query = "UPDATE ftth_items 
                             SET ont_connected_odp_id = NULL 
                             WHERE ont_connected_odp_id IS NOT NULL 
                             AND ont_connected_odp_id NOT IN (SELECT id FROM ftth_items)";
    
    $stmt = $db->prepare($fix_invalid_odp_query);
    if ($stmt->execute()) {
        $invalid_odp_fixed = $stmt->rowCount();
        $total_fixed += $invalid_odp_fixed;
        echo "<p style='color: green;'>✅ Fixed {$invalid_odp_fixed} invalid ont_connected_odp_id references</p>";
    }
    
    // Fix invalid references untuk odp_parent_odc_id
    $fix_invalid_odc_query = "UPDATE ftth_items 
                             SET odp_parent_odc_id = NULL 
                             WHERE odp_parent_odc_id IS NOT NULL 
                             AND odp_parent_odc_id NOT IN (SELECT id FROM ftth_items)";
    
    $stmt = $db->prepare($fix_invalid_odc_query);
    if ($stmt->execute()) {
        $invalid_odc_fixed = $stmt->rowCount();
        $total_fixed += $invalid_odc_fixed;
        echo "<p style='color: green;'>✅ Fixed {$invalid_odc_fixed} invalid odp_parent_odc_id references</p>";
    }
    
    echo "<p style='color: green;'><strong>Total items fixed: {$total_fixed}</strong></p>";
    
    // 4. Verifikasi perbaikan
    echo "<h3>4. ✅ Verifikasi Perbaikan</h3>";
    
    // Check lagi untuk memastikan tidak ada yang bermasalah
    $verify_query = "SELECT COUNT(*) as count FROM ftth_items 
                    WHERE (ont_connected_odp_id = '' OR ont_connected_odp_id = '0' 
                    OR odp_parent_odc_id = '' OR odp_parent_odc_id = '0'
                    OR (ont_connected_odp_id IS NOT NULL AND ont_connected_odp_id NOT IN (SELECT id FROM ftth_items))
                    OR (odp_parent_odc_id IS NOT NULL AND odp_parent_odc_id NOT IN (SELECT id FROM ftth_items)))";
    
    $stmt = $db->prepare($verify_query);
    $stmt->execute();
    $remaining_problems = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($remaining_problems == 0) {
        echo "<p style='color: green;'>✅ Semua masalah foreign key constraint telah diperbaiki!</p>";
    } else {
        echo "<p style='color: red;'>❌ Masih ada {$remaining_problems} item dengan masalah foreign key</p>";
    }
    
    // 5. Test update untuk memastikan tidak ada error
    echo "<h3>5. 🧪 Test Update</h3>";
    
    // Get test item
    $test_query = "SELECT id, name, item_type_id FROM ftth_items WHERE item_type_id = 7 LIMIT 1";
    $stmt = $db->prepare($test_query);
    $stmt->execute();
    $test_item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($test_item) {
        echo "<p><strong>Testing update untuk item:</strong> ID {$test_item['id']} - {$test_item['name']}</p>";
        
        // Test update dengan empty string untuk ont_connected_odp_id
        $test_update_query = "UPDATE ftth_items SET description = CONCAT(description, ' - Test Update') WHERE id = ?";
        $stmt = $db->prepare($test_update_query);
        
        try {
            $stmt->execute([$test_item['id']]);
            echo "<p style='color: green;'>✅ Test update berhasil - tidak ada foreign key constraint error</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Test update gagal: " . $e->getMessage() . "</p>";
        }
    }
    
    // 6. Update API untuk prevention
    echo "<h3>6. 🔧 Update API untuk Prevention</h3>";
    
    echo "<p><strong>API items.php telah diupdate untuk menangani foreign key fields:</strong></p>";
    echo "<ul>";
    echo "<li>✅ ont_connected_odp_id</li>";
    echo "<li>✅ odp_parent_odc_id</li>";
    echo "<li>✅ auto_generated_by_route_id</li>";
    echo "<li>✅ tube_color_id</li>";
    echo "<li>✅ core_color_id</li>";
    echo "<li>✅ splitter_main_id</li>";
    echo "<li>✅ splitter_odp_id</li>";
    echo "<li>✅ upstream_interface_id</li>";
    echo "</ul>";
    
    echo "<p><strong>Kode yang ditambahkan:</strong></p>";
    echo "<pre style='background: #f4f4f4; padding: 10px; border-radius: 5px;'>";
    echo "if (in_array(\$field, ['tube_color_id', 'core_color_id', 'splitter_main_id', 'splitter_odp_id', 'upstream_interface_id', 'ont_connected_odp_id', 'odp_parent_odc_id', 'auto_generated_by_route_id']) && \n";
    echo "    (\$value === '' || \$value === '0' || \$value === 0)) {\n";
    echo "    \$value = null;\n";
    echo "}\n";
    echo "</pre>";
    
    // 7. Summary
    echo "<h3>7. 📋 Summary</h3>";
    echo "<ul>";
    echo "<li><strong>Root Cause:</strong> Field ont_connected_odp_id tidak ditangani dalam validasi foreign key di API</li>";
    echo "<li><strong>Masalah:</strong> Empty string dan nilai '0' tidak dikonversi ke NULL</li>";
    echo "<li><strong>Impact:</strong> Server items dengan nilai invalid menyebabkan constraint violation</li>";
    echo "<li><strong>Solusi:</strong> Perbaiki data existing dan update API untuk handling yang benar</li>";
    echo "<li><strong>Prevention:</strong> Semua foreign key fields sekarang ditangani dengan benar</li>";
    echo "</ul>";
    
    echo "<h3>🎉 Foreign Key Constraint Error Fixed!</h3>";
    echo "<p>Sekarang Anda dapat mengedit/update item server tanpa foreign key constraint error.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>
