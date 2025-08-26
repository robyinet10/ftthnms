-- =====================================================
-- UPDATE DATABASE PON INTEGRATION (FIXED)
-- FTTH Network Monitoring System
-- =====================================================
-- File: update_database_pon_integration_fixed.sql
-- Description: Update database untuk integrasi PON Connection dropdown (FIXED)
-- Date: 2025-01-17
-- Fix: Removed description column from item_types INSERT
-- =====================================================

-- Set foreign key checks off untuk menghindari constraint issues
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- 1. UPDATE ITEM TYPES - Standardisasi ODC
-- =====================================================

-- Update nama item type untuk ODC Pole Mounted
UPDATE item_types SET name = 'ODC Pole Mounted' WHERE id = 4;

-- Hapus item type yang ambigu (ODC dengan ID 8) jika masih ada
-- Pertama, reassign item yang menggunakan ID 8 ke ID 4
UPDATE ftth_items SET item_type_id = 4 WHERE item_type_id = 8;

-- Kemudian hapus item type ID 8
DELETE FROM item_types WHERE id = 8;

-- Pastikan ODC Cabinet ada dengan ID 12 (FIXED - tanpa description column)
INSERT IGNORE INTO item_types (id, name, icon, color) VALUES 
(12, 'ODC Cabinet', 'fas fa-network-wired', '#FFA500');

-- =====================================================
-- 2. ADD ODC ENHANCEMENT COLUMNS TO FTTH_ITEMS
-- =====================================================

-- Tambah kolom ODC enhancement jika belum ada
ALTER TABLE ftth_items 
ADD COLUMN IF NOT EXISTS odc_type ENUM('pole_mounted', 'ground_mounted') NULL COMMENT 'Tipe ODC: pole_mounted atau ground_mounted',
ADD COLUMN IF NOT EXISTS odc_capacity INT NULL COMMENT 'Kapasitas customer ODC (otomatis dihitung)',
ADD COLUMN IF NOT EXISTS odc_ports_used INT DEFAULT 0 COMMENT 'Jumlah port ODC yang sudah digunakan',
ADD COLUMN IF NOT EXISTS odc_installation_type ENUM('pole', 'ground', 'wall') NULL COMMENT 'Tipe instalasi ODC',
ADD COLUMN IF NOT EXISTS odc_main_splitter_ratio VARCHAR(10) NULL COMMENT 'Ratio splitter utama (1:4, 1:8, dll)',
ADD COLUMN IF NOT EXISTS odc_odp_splitter_ratio VARCHAR(10) NULL COMMENT 'Ratio splitter ODP (1:8, 1:16, dll)',
ADD COLUMN IF NOT EXISTS odc_input_ports INT DEFAULT 1 COMMENT 'Jumlah port input dari backbone',
ADD COLUMN IF NOT EXISTS odc_output_ports INT DEFAULT 4 COMMENT 'Jumlah port output ke ODP',
ADD COLUMN IF NOT EXISTS odc_pon_connection VARCHAR(50) NULL COMMENT 'PON port dari OLT yang terhubung',
ADD COLUMN IF NOT EXISTS odc_vlan_id VARCHAR(10) NULL COMMENT 'VLAN ID untuk ODC';

-- =====================================================
-- 3. CREATE ODC PORTS TABLE
-- =====================================================

CREATE TABLE IF NOT EXISTS odc_ports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    odc_item_id INT NOT NULL,
    port_number INT NOT NULL,
    port_type ENUM('input', 'output') NOT NULL,
    port_status ENUM('available', 'connected', 'reserved', 'maintenance') DEFAULT 'available',
    connected_to_item_id INT NULL,
    connected_to_port VARCHAR(50) NULL,
    attenuation_dbm DECIMAL(5,2) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (odc_item_id) REFERENCES ftth_items(id) ON DELETE CASCADE,
    FOREIGN KEY (connected_to_item_id) REFERENCES ftth_items(id) ON DELETE SET NULL,
    UNIQUE KEY unique_odc_port (odc_item_id, port_number)
);

-- =====================================================
-- 4. CREATE ODC-PON MAPPING TABLE
-- =====================================================

CREATE TABLE IF NOT EXISTS odc_pon_mapping (
    id INT PRIMARY KEY AUTO_INCREMENT,
    odc_item_id INT NOT NULL,
    olt_item_id INT NOT NULL,
    pon_port VARCHAR(50) NOT NULL,
    vlan_id VARCHAR(10) NULL,
    description VARCHAR(255) NULL,
    status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (odc_item_id) REFERENCES ftth_items(id) ON DELETE CASCADE,
    FOREIGN KEY (olt_item_id) REFERENCES ftth_items(id) ON DELETE CASCADE,
    UNIQUE KEY unique_odc_pon (odc_item_id, olt_item_id, pon_port)
);

-- =====================================================
-- 5. CREATE OLT PONS TABLE
-- =====================================================

CREATE TABLE IF NOT EXISTS olt_pons (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_id INT NOT NULL,
    pon_port VARCHAR(50) NOT NULL COMMENT 'PON port identifier (e.g., PON1/1/1, 0/1/1)',
    description VARCHAR(255) NULL COMMENT 'Description for this PON port',
    status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES ftth_items(id) ON DELETE CASCADE,
    UNIQUE KEY unique_item_pon (item_id, pon_port)
);

-- =====================================================
-- 6. CREATE PON-VLANS TABLE
-- =====================================================

CREATE TABLE IF NOT EXISTS pon_vlans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    pon_id INT NOT NULL,
    vlan_id VARCHAR(10) NOT NULL COMMENT 'VLAN ID (e.g., 100, 200)',
    description VARCHAR(255) NULL COMMENT 'VLAN description',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (pon_id) REFERENCES olt_pons(id) ON DELETE CASCADE,
    UNIQUE KEY unique_pon_vlan (pon_id, vlan_id)
);

-- =====================================================
-- 7. CREATE INDEXES FOR PERFORMANCE
-- =====================================================

-- ODC Enhancement Indexes
CREATE INDEX IF NOT EXISTS idx_odc_type ON ftth_items(odc_type);
CREATE INDEX IF NOT EXISTS idx_odc_capacity ON ftth_items(odc_capacity);
CREATE INDEX IF NOT EXISTS idx_odc_pon_connection ON ftth_items(odc_pon_connection);

-- OLT PON Indexes
CREATE INDEX IF NOT EXISTS idx_olt_pons_item ON olt_pons(item_id);
CREATE INDEX IF NOT EXISTS idx_olt_pons_port ON olt_pons(pon_port);
CREATE INDEX IF NOT EXISTS idx_pon_vlans_pon ON pon_vlans(pon_id);
CREATE INDEX IF NOT EXISTS idx_pon_vlans_vlan_id ON pon_vlans(vlan_id);

-- =====================================================
-- 8. INSERT SAMPLE DATA - OLT DEVICES
-- =====================================================

-- Insert OLT devices
INSERT INTO ftth_items (
    item_type_id, name, description, latitude, longitude, address, ip_address, status
) VALUES 
(1, 'OLT Central', 'OLT Central Area', -0.937783, 119.854373, 'Jl. Sultan Hasanuddin No. 1', '192.168.100.240', 'active'),
(1, 'OLT North', 'OLT North Area', -0.935783, 119.856373, 'Jl. Pattimura No. 15', '192.168.100.241', 'active')
ON DUPLICATE KEY UPDATE 
    name = VALUES(name),
    description = VALUES(description),
    ip_address = VALUES(ip_address);

-- =====================================================
-- 9. INSERT SAMPLE DATA - OLT PON PORTS
-- =====================================================

-- Get OLT IDs
SET @olt_central_id = (SELECT id FROM ftth_items WHERE name = 'OLT Central' AND item_type_id = 1 LIMIT 1);
SET @olt_north_id = (SELECT id FROM ftth_items WHERE name = 'OLT North' AND item_type_id = 1 LIMIT 1);

-- Insert OLT PON ports
INSERT INTO olt_pons (item_id, pon_port, description, status) VALUES
-- OLT Central PON ports
(@olt_central_id, 'PON1/1/1', 'PON Port 1 untuk Area Central', 'active'),
(@olt_central_id, 'PON1/1/2', 'PON Port 2 untuk Area Central', 'active'),
(@olt_central_id, 'PON1/1/3', 'PON Port 3 untuk Area Central', 'active'),
(@olt_central_id, 'PON1/1/4', 'PON Port 4 untuk Area Central', 'active'),
-- OLT North PON ports
(@olt_north_id, 'PON2/2/1', 'PON Port 1 untuk Area North', 'active'),
(@olt_north_id, 'PON2/2/2', 'PON Port 2 untuk Area North', 'active'),
(@olt_north_id, 'PON2/2/3', 'PON Port 3 untuk Area North', 'active'),
(@olt_north_id, 'PON2/2/4', 'PON Port 4 untuk Area North', 'active')
ON DUPLICATE KEY UPDATE 
    description = VALUES(description),
    status = VALUES(status);

-- =====================================================
-- 10. INSERT SAMPLE DATA - PON-VLAN MAPPINGS
-- =====================================================

-- Get PON IDs
SET @pon1_id = (SELECT id FROM olt_pons WHERE pon_port = 'PON1/1/1' LIMIT 1);
SET @pon2_id = (SELECT id FROM olt_pons WHERE pon_port = 'PON1/1/2' LIMIT 1);
SET @pon3_id = (SELECT id FROM olt_pons WHERE pon_port = 'PON1/1/3' LIMIT 1);
SET @pon4_id = (SELECT id FROM olt_pons WHERE pon_port = 'PON1/1/4' LIMIT 1);
SET @pon5_id = (SELECT id FROM olt_pons WHERE pon_port = 'PON2/2/1' LIMIT 1);
SET @pon6_id = (SELECT id FROM olt_pons WHERE pon_port = 'PON2/2/2' LIMIT 1);
SET @pon7_id = (SELECT id FROM olt_pons WHERE pon_port = 'PON2/2/3' LIMIT 1);
SET @pon8_id = (SELECT id FROM olt_pons WHERE pon_port = 'PON2/2/4' LIMIT 1);

-- Insert PON-VLAN mappings
INSERT INTO pon_vlans (pon_id, vlan_id, description) VALUES
-- OLT Central VLANs
(@pon1_id, '100', 'VLAN 100 (IP: 172.0.100.0/24)'),
(@pon2_id, '101', 'VLAN 101 (IP: 172.0.101.0/24)'),
(@pon3_id, '102', 'VLAN 102 (IP: 172.0.102.0/24)'),
(@pon4_id, '103', 'VLAN 103 (IP: 172.0.103.0/24)'),
-- OLT North VLANs
(@pon5_id, '200', 'VLAN 200 (IP: 172.0.200.0/24)'),
(@pon6_id, '201', 'VLAN 201 (IP: 172.0.201.0/24)'),
(@pon7_id, '202', 'VLAN 202 (IP: 172.0.202.0/24)'),
(@pon8_id, '203', 'VLAN 203 (IP: 172.0.203.0/24)')
ON DUPLICATE KEY UPDATE 
    description = VALUES(description);

-- =====================================================
-- 11. INSERT SAMPLE DATA - ODC DEVICES
-- =====================================================

-- Insert sample ODC devices
INSERT INTO ftth_items (
    item_type_id, odc_type, odc_capacity, odc_installation_type,
    name, description, latitude, longitude, address,
    odc_main_splitter_ratio, odc_odp_splitter_ratio,
    odc_input_ports, odc_output_ports, status
) VALUES 
(4, 'pole_mounted', 32, 'pole', 'ODC-Pole-001', 'ODC Pole Mounted Area Central', -0.937783, 119.854373, 'Jl. Sultan Hasanuddin No. 10', '1:4', '1:8', 1, 4, 'active'),
(12, 'ground_mounted', 64, 'ground', 'ODC-Cabinet-001', 'ODC Cabinet Ground Mounted Area North', -0.935783, 119.856373, 'Jl. Pattimura No. 25', '1:4', '1:16', 1, 4, 'active')
ON DUPLICATE KEY UPDATE 
    odc_type = VALUES(odc_type),
    odc_capacity = VALUES(odc_capacity),
    odc_installation_type = VALUES(odc_installation_type),
    odc_main_splitter_ratio = VALUES(odc_main_splitter_ratio),
    odc_odp_splitter_ratio = VALUES(odc_odp_splitter_ratio),
    odc_input_ports = VALUES(odc_input_ports),
    odc_output_ports = VALUES(odc_output_ports);

-- =====================================================
-- 12. INSERT SAMPLE DATA - ODC PORTS
-- =====================================================

-- Get ODC IDs
SET @odc_pole_id = (SELECT id FROM ftth_items WHERE name = 'ODC-Pole-001' AND item_type_id = 4 LIMIT 1);
SET @odc_cabinet_id = (SELECT id FROM ftth_items WHERE name = 'ODC-Cabinet-001' AND item_type_id = 12 LIMIT 1);

-- Insert ODC ports
INSERT INTO odc_ports (odc_item_id, port_number, port_type, port_status, attenuation_dbm, notes) VALUES
-- ODC Pole Mounted ports
(@odc_pole_id, 1, 'input', 'connected', -3.2, 'Input dari backbone'),
(@odc_pole_id, 2, 'output', 'connected', -4.1, 'Output ke ODP-001'),
(@odc_pole_id, 3, 'output', 'connected', -4.3, 'Output ke ODP-002'),
(@odc_pole_id, 4, 'output', 'available', NULL, 'Port tersedia'),
(@odc_pole_id, 5, 'output', 'available', NULL, 'Port tersedia'),
-- ODC Cabinet ports
(@odc_cabinet_id, 1, 'input', 'connected', -2.8, 'Input dari backbone'),
(@odc_cabinet_id, 2, 'output', 'connected', -3.9, 'Output ke ODP-003'),
(@odc_cabinet_id, 3, 'output', 'connected', -4.1, 'Output ke ODP-004'),
(@odc_cabinet_id, 4, 'output', 'reserved', NULL, 'Port reserved untuk expansion'),
(@odc_cabinet_id, 5, 'output', 'available', NULL, 'Port tersedia')
ON DUPLICATE KEY UPDATE 
    port_status = VALUES(port_status),
    attenuation_dbm = VALUES(attenuation_dbm),
    notes = VALUES(notes);

-- =====================================================
-- 13. UPDATE ODC PORTS USED COUNT
-- =====================================================

-- Update ODC ports used count
UPDATE ftth_items SET 
    odc_ports_used = (
        SELECT COUNT(*) 
        FROM odc_ports 
        WHERE odc_item_id = ftth_items.id 
        AND port_status = 'connected'
    )
WHERE item_type_id IN (4, 12);

-- =====================================================
-- 14. SAMPLE ODC-PON MAPPING
-- =====================================================

-- Connect ODC Pole to OLT Central PON1/1/1
INSERT INTO odc_pon_mapping (odc_item_id, olt_item_id, pon_port, vlan_id, description, status) VALUES
(@odc_pole_id, @olt_central_id, 'PON1/1/1', '100', 'ODC Pole Mounted terhubung ke OLT Central', 'active')
ON DUPLICATE KEY UPDATE 
    vlan_id = VALUES(vlan_id),
    description = VALUES(description),
    status = VALUES(status);

-- Update ODC with PON connection
UPDATE ftth_items SET 
    odc_pon_connection = 'PON1/1/1',
    odc_vlan_id = '100'
WHERE id = @odc_pole_id;

-- =====================================================
-- 15. FINAL CLEANUP AND VERIFICATION
-- =====================================================

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Verify data integrity
SELECT 'ODC Enhancement Update Complete (FIXED)' as status;

-- Show summary of changes
SELECT 
    'Item Types Updated' as table_name,
    COUNT(*) as record_count
FROM item_types 
WHERE name LIKE '%ODC%'
UNION ALL
SELECT 
    'OLT Devices' as table_name,
    COUNT(*) as record_count
FROM ftth_items 
WHERE item_type_id = 1
UNION ALL
SELECT 
    'OLT PON Ports' as table_name,
    COUNT(*) as record_count
FROM olt_pons
UNION ALL
SELECT 
    'PON-VLAN Mappings' as table_name,
    COUNT(*) as record_count
FROM pon_vlans
UNION ALL
SELECT 
    'ODC Devices' as table_name,
    COUNT(*) as record_count
FROM ftth_items 
WHERE item_type_id IN (4, 12)
UNION ALL
SELECT 
    'ODC Ports' as table_name,
    COUNT(*) as record_count
FROM odc_ports;

-- =====================================================
-- END OF UPDATE SCRIPT (FIXED)
-- =====================================================
