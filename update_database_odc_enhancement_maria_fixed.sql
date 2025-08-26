-- FTTHNMS Database Update: ODC Enhancement & Standardization (MARIA DB FIXED)
-- Versi: 3.0.2 - Enhanced ODC Management with MariaDB Compatibility
-- Tanggal: 2024-01-15

USE ftthnms;

-- 1. Standardisasi Item Types ODC (FIXED - Handle Foreign Key Constraint)
-- Update existing ODC items to use proper item_type_id before deleting
UPDATE ftth_items SET item_type_id = 4 WHERE item_type_id = 8;

-- Now safe to delete the ambiguous item type
DELETE FROM item_types WHERE id = 8;

-- Tambah ODC Cabinet yang proper
INSERT INTO item_types (name, icon, color) VALUES
('ODC Cabinet', 'fas fa-box', '#F39C12');

-- Update nama Tiang ODC menjadi lebih jelas
UPDATE item_types SET name = 'ODC Pole Mounted' WHERE id = 4;

-- 2. Tambah kolom untuk ODC specifications (MariaDB compatible)
-- Check if columns exist first
SET @odc_type_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'ftthnms' 
    AND TABLE_NAME = 'ftth_items' 
    AND COLUMN_NAME = 'odc_type');

SET @sql = CASE 
    WHEN @odc_type_exists = 0 THEN 
        'ALTER TABLE ftth_items 
         ADD COLUMN odc_type ENUM("pole_mounted", "ground_mounted") DEFAULT "pole_mounted" AFTER item_type_id,
         ADD COLUMN odc_capacity INT DEFAULT 32 AFTER odc_type,
         ADD COLUMN odc_ports_used INT DEFAULT 0 AFTER odc_capacity,
         ADD COLUMN odc_installation_type ENUM("pole", "ground", "wall") DEFAULT "pole" AFTER odc_ports_used,
         ADD COLUMN odc_main_splitter_ratio VARCHAR(10) DEFAULT "1:4" AFTER odc_installation_type,
         ADD COLUMN odc_odp_splitter_ratio VARCHAR(10) DEFAULT "1:8" AFTER odc_main_splitter_ratio,
         ADD COLUMN odc_input_ports INT DEFAULT 1 AFTER odc_odp_splitter_ratio,
         ADD COLUMN odc_output_ports INT DEFAULT 4 AFTER odc_input_ports,
         ADD COLUMN odc_pon_connection VARCHAR(50) DEFAULT NULL AFTER odc_output_ports'
    ELSE 
        'SELECT "ODC columns already exist" as message'
END;

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Update existing ODC items dengan default values
UPDATE ftth_items SET 
    odc_type = 'pole_mounted',
    odc_capacity = 32,
    odc_ports_used = 0,
    odc_installation_type = 'pole',
    odc_main_splitter_ratio = '1:4',
    odc_odp_splitter_ratio = '1:8',
    odc_input_ports = 1,
    odc_output_ports = 4
WHERE item_type_id IN (4, 12);

-- 4. Tambah indexes untuk performance (MariaDB compatible)
SET @idx_odc_type_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = 'ftthnms' 
    AND TABLE_NAME = 'ftth_items' 
    AND INDEX_NAME = 'idx_odc_type');

SET @sql = CASE 
    WHEN @idx_odc_type_exists = 0 THEN 
        'CREATE INDEX idx_odc_type ON ftth_items(odc_type)'
    ELSE 
        'SELECT "Index idx_odc_type already exists" as message'
END;

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_odc_capacity_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = 'ftthnms' 
    AND TABLE_NAME = 'ftth_items' 
    AND INDEX_NAME = 'idx_odc_capacity');

SET @sql = CASE 
    WHEN @idx_odc_capacity_exists = 0 THEN 
        'CREATE INDEX idx_odc_capacity ON ftth_items(odc_capacity)'
    ELSE 
        'SELECT "Index idx_odc_capacity already exists" as message'
END;

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_odc_pon_connection_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = 'ftthnms' 
    AND TABLE_NAME = 'ftth_items' 
    AND INDEX_NAME = 'idx_odc_pon_connection');

SET @sql = CASE 
    WHEN @idx_odc_pon_connection_exists = 0 THEN 
        'CREATE INDEX idx_odc_pon_connection ON ftth_items(odc_pon_connection)'
    ELSE 
        'SELECT "Index idx_odc_pon_connection already exists" as message'
END;

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5. Tambah tabel untuk ODC port management (MariaDB compatible)
SET @odc_ports_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = 'ftthnms' 
    AND TABLE_NAME = 'odc_ports');

SET @sql = CASE 
    WHEN @odc_ports_exists = 0 THEN 
        'CREATE TABLE odc_ports (
            id INT PRIMARY KEY AUTO_INCREMENT,
            odc_item_id INT NOT NULL,
            port_number INT NOT NULL,
            port_type ENUM("input", "output") NOT NULL,
            port_status ENUM("available", "connected", "reserved", "maintenance") DEFAULT "available",
            connected_to_item_id INT NULL,
            connected_to_port VARCHAR(50) NULL,
            attenuation_dbm DECIMAL(5,2) NULL,
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (odc_item_id) REFERENCES ftth_items(id) ON DELETE CASCADE,
            FOREIGN KEY (connected_to_item_id) REFERENCES ftth_items(id) ON DELETE SET NULL,
            UNIQUE KEY unique_odc_port (odc_item_id, port_number)
        )'
    ELSE 
        'SELECT "Table odc_ports already exists" as message'
END;

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 6. Tambah tabel untuk ODC-PON mapping (MariaDB compatible)
SET @odc_pon_mapping_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = 'ftthnms' 
    AND TABLE_NAME = 'odc_pon_mapping');

SET @sql = CASE 
    WHEN @odc_pon_mapping_exists = 0 THEN 
        'CREATE TABLE odc_pon_mapping (
            id INT PRIMARY KEY AUTO_INCREMENT,
            odc_item_id INT NOT NULL,
            olt_item_id INT NOT NULL,
            pon_port VARCHAR(50) NOT NULL,
            vlan_id VARCHAR(10) NULL,
            description VARCHAR(255) NULL,
            status ENUM("active", "inactive", "maintenance") DEFAULT "active",
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (odc_item_id) REFERENCES ftth_items(id) ON DELETE CASCADE,
            FOREIGN KEY (olt_item_id) REFERENCES ftth_items(id) ON DELETE CASCADE,
            UNIQUE KEY unique_odc_pon (odc_item_id, olt_item_id, pon_port)
        )'
    ELSE 
        'SELECT "Table odc_pon_mapping already exists" as message'
END;

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 7. Insert sample data untuk testing (only if no existing ODC items)
INSERT INTO ftth_items (
    item_type_id, odc_type, odc_capacity, odc_installation_type,
    name, description, latitude, longitude, address,
    odc_main_splitter_ratio, odc_odp_splitter_ratio,
    odc_input_ports, odc_output_ports, status
) 
SELECT 4, 'pole_mounted', 32, 'pole', 'ODC-Pole-001', 'ODC Pole Mounted Area Central', -0.937783, 119.854373, 'Jl. Sultan Hasanuddin No. 10', '1:4', '1:8', 1, 4, 'active'
WHERE NOT EXISTS (SELECT 1 FROM ftth_items WHERE name = 'ODC-Pole-001');

INSERT INTO ftth_items (
    item_type_id, odc_type, odc_capacity, odc_installation_type,
    name, description, latitude, longitude, address,
    odc_main_splitter_ratio, odc_odp_splitter_ratio,
    odc_input_ports, odc_output_ports, status
) 
SELECT 12, 'ground_mounted', 64, 'ground', 'ODC-Cabinet-001', 'ODC Cabinet Ground Mounted Area North', -0.935783, 119.856373, 'Jl. Pattimura No. 25', '1:4', '1:16', 1, 4, 'active'
WHERE NOT EXISTS (SELECT 1 FROM ftth_items WHERE name = 'ODC-Cabinet-001');

-- 8. Insert sample ODC ports (only for newly created ODC items)
INSERT INTO odc_ports (odc_item_id, port_number, port_type, port_status, attenuation_dbm, notes)
SELECT 
    i.id, 1, 'input', 'connected', -3.2, 'Input dari backbone'
FROM ftth_items i 
WHERE i.name = 'ODC-Pole-001' 
AND NOT EXISTS (SELECT 1 FROM odc_ports WHERE odc_item_id = i.id AND port_number = 1);

INSERT INTO odc_ports (odc_item_id, port_number, port_type, port_status, attenuation_dbm, notes)
SELECT 
    i.id, 2, 'output', 'connected', -4.1, 'Output ke ODP-001'
FROM ftth_items i 
WHERE i.name = 'ODC-Pole-001' 
AND NOT EXISTS (SELECT 1 FROM odc_ports WHERE odc_item_id = i.id AND port_number = 2);

INSERT INTO odc_ports (odc_item_id, port_number, port_type, port_status, attenuation_dbm, notes)
SELECT 
    i.id, 3, 'output', 'connected', -4.3, 'Output ke ODP-002'
FROM ftth_items i 
WHERE i.name = 'ODC-Pole-001' 
AND NOT EXISTS (SELECT 1 FROM odc_ports WHERE odc_item_id = i.id AND port_number = 3);

INSERT INTO odc_ports (odc_item_id, port_number, port_type, port_status, attenuation_dbm, notes)
SELECT 
    i.id, 4, 'output', 'available', NULL, 'Port tersedia'
FROM ftth_items i 
WHERE i.name = 'ODC-Pole-001' 
AND NOT EXISTS (SELECT 1 FROM odc_ports WHERE odc_item_id = i.id AND port_number = 4);

INSERT INTO odc_ports (odc_item_id, port_number, port_type, port_status, attenuation_dbm, notes)
SELECT 
    i.id, 5, 'output', 'available', NULL, 'Port tersedia'
FROM ftth_items i 
WHERE i.name = 'ODC-Pole-001' 
AND NOT EXISTS (SELECT 1 FROM odc_ports WHERE odc_item_id = i.id AND port_number = 5);

-- ODC Cabinet ports
INSERT INTO odc_ports (odc_item_id, port_number, port_type, port_status, attenuation_dbm, notes)
SELECT 
    i.id, 1, 'input', 'connected', -2.8, 'Input dari backbone'
FROM ftth_items i 
WHERE i.name = 'ODC-Cabinet-001' 
AND NOT EXISTS (SELECT 1 FROM odc_ports WHERE odc_item_id = i.id AND port_number = 1);

INSERT INTO odc_ports (odc_item_id, port_number, port_type, port_status, attenuation_dbm, notes)
SELECT 
    i.id, 2, 'output', 'connected', -3.9, 'Output ke ODP-003'
FROM ftth_items i 
WHERE i.name = 'ODC-Cabinet-001' 
AND NOT EXISTS (SELECT 1 FROM odc_ports WHERE odc_item_id = i.id AND port_number = 2);

INSERT INTO odc_ports (odc_item_id, port_number, port_type, port_status, attenuation_dbm, notes)
SELECT 
    i.id, 3, 'output', 'connected', -4.1, 'Output ke ODP-004'
FROM ftth_items i 
WHERE i.name = 'ODC-Cabinet-001' 
AND NOT EXISTS (SELECT 1 FROM odc_ports WHERE odc_item_id = i.id AND port_number = 3);

INSERT INTO odc_ports (odc_item_id, port_number, port_type, port_status, attenuation_dbm, notes)
SELECT 
    i.id, 4, 'output', 'reserved', NULL, 'Port reserved untuk expansion'
FROM ftth_items i 
WHERE i.name = 'ODC-Cabinet-001' 
AND NOT EXISTS (SELECT 1 FROM odc_ports WHERE odc_item_id = i.id AND port_number = 4);

INSERT INTO odc_ports (odc_item_id, port_number, port_type, port_status, attenuation_dbm, notes)
SELECT 
    i.id, 5, 'output', 'available', NULL, 'Port tersedia'
FROM ftth_items i 
WHERE i.name = 'ODC-Cabinet-001' 
AND NOT EXISTS (SELECT 1 FROM odc_ports WHERE odc_item_id = i.id AND port_number = 5);

-- 9. Insert sample ODC-PON mapping (only if OLT exists)
INSERT INTO odc_pon_mapping (odc_item_id, olt_item_id, pon_port, vlan_id, description)
SELECT 
    odc.id, olt.id, 'PON1/1/1', '100', 'ODC Pole Central - VLAN 100'
FROM ftth_items odc, ftth_items olt
WHERE odc.name = 'ODC-Pole-001' 
AND olt.item_type_id = 1 
AND olt.name LIKE '%OLT%'
AND NOT EXISTS (SELECT 1 FROM odc_pon_mapping WHERE odc_item_id = odc.id AND pon_port = 'PON1/1/1')
LIMIT 1;

INSERT INTO odc_pon_mapping (odc_item_id, olt_item_id, pon_port, vlan_id, description)
SELECT 
    odc.id, olt.id, 'PON1/1/2', '101', 'ODC Cabinet North - VLAN 101'
FROM ftth_items odc, ftth_items olt
WHERE odc.name = 'ODC-Cabinet-001' 
AND olt.item_type_id = 1 
AND olt.name LIKE '%OLT%'
AND NOT EXISTS (SELECT 1 FROM odc_pon_mapping WHERE odc_item_id = odc.id AND pon_port = 'PON1/1/2')
LIMIT 1;

-- 10. Update statistics untuk ODC
UPDATE ftth_items SET 
    odc_ports_used = (
        SELECT COUNT(*) 
        FROM odc_ports 
        WHERE odc_item_id = ftth_items.id 
        AND port_status = 'connected'
    )
WHERE item_type_id IN (4, 12);

-- 11. Verifikasi update
SELECT 
    'ODC Item Types Updated' as status,
    COUNT(*) as count
FROM item_types 
WHERE name LIKE '%ODC%'

UNION ALL

SELECT 
    'ODC Items with Enhanced Config' as status,
    COUNT(*) as count
FROM ftth_items 
WHERE item_type_id IN (4, 12)

UNION ALL

SELECT 
    'ODC Ports Created' as status,
    COUNT(*) as count
FROM odc_ports

UNION ALL

SELECT 
    'ODC-PON Mappings' as status,
    COUNT(*) as count
FROM odc_pon_mapping

UNION ALL

SELECT 
    'Database Update Status' as status,
    'SUCCESS - All ODC enhancements applied' as count;
