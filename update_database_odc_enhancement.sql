-- FTTHNMS Database Update: ODC Enhancement & Standardization
-- Versi: 3.0.0 - Enhanced ODC Management
-- Tanggal: 2024-01-15

USE ftthnms;

-- 1. Standardisasi Item Types ODC
-- Hapus item type yang ambigu (ODC dengan ID 8)
DELETE FROM item_types WHERE id = 8;

-- Tambah ODC Cabinet yang proper
INSERT INTO item_types (name, icon, color) VALUES
('ODC Cabinet', 'fas fa-box', '#F39C12');

-- Update nama Tiang ODC menjadi lebih jelas
UPDATE item_types SET name = 'ODC Pole Mounted' WHERE id = 4;

-- 2. Tambah kolom untuk ODC specifications
ALTER TABLE ftth_items 
ADD COLUMN odc_type ENUM('pole_mounted', 'ground_mounted') DEFAULT 'pole_mounted' AFTER item_type_id,
ADD COLUMN odc_capacity INT DEFAULT 32 AFTER odc_type,
ADD COLUMN odc_ports_used INT DEFAULT 0 AFTER odc_capacity,
ADD COLUMN odc_installation_type ENUM('pole', 'ground', 'wall') DEFAULT 'pole' AFTER odc_ports_used,
ADD COLUMN odc_main_splitter_ratio VARCHAR(10) DEFAULT '1:4' AFTER odc_installation_type,
ADD COLUMN odc_odp_splitter_ratio VARCHAR(10) DEFAULT '1:8' AFTER odc_main_splitter_ratio,
ADD COLUMN odc_input_ports INT DEFAULT 1 AFTER odc_odp_splitter_ratio,
ADD COLUMN odc_output_ports INT DEFAULT 4 AFTER odc_input_ports,
ADD COLUMN odc_pon_connection VARCHAR(50) DEFAULT NULL AFTER odc_output_ports COMMENT 'PON port dari OLT yang terhubung';

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

-- 4. Tambah indexes untuk performance
CREATE INDEX idx_odc_type ON ftth_items(odc_type);
CREATE INDEX idx_odc_capacity ON ftth_items(odc_capacity);
CREATE INDEX idx_odc_pon_connection ON ftth_items(odc_pon_connection);

-- 5. Tambah tabel untuk ODC port management
CREATE TABLE odc_ports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    odc_item_id INT NOT NULL,
    port_number INT NOT NULL,
    port_type ENUM('input', 'output') NOT NULL,
    port_status ENUM('available', 'connected', 'reserved', 'maintenance') DEFAULT 'available',
    connected_to_item_id INT NULL,
    connected_to_port VARCHAR(50) NULL,
    attenuation_dbm DECIMAL(5,2) NULL COMMENT 'Attenuation in dBm',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (odc_item_id) REFERENCES ftth_items(id) ON DELETE CASCADE,
    FOREIGN KEY (connected_to_item_id) REFERENCES ftth_items(id) ON DELETE SET NULL,
    UNIQUE KEY unique_odc_port (odc_item_id, port_number)
);

-- 6. Tambah tabel untuk ODC-PON mapping
CREATE TABLE odc_pon_mapping (
    id INT PRIMARY KEY AUTO_INCREMENT,
    odc_item_id INT NOT NULL,
    olt_item_id INT NOT NULL,
    pon_port VARCHAR(50) NOT NULL COMMENT 'PON port dari OLT (e.g., PON1/1/1)',
    vlan_id VARCHAR(10) NULL COMMENT 'VLAN ID untuk ODC ini',
    description VARCHAR(255) NULL,
    status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (odc_item_id) REFERENCES ftth_items(id) ON DELETE CASCADE,
    FOREIGN KEY (olt_item_id) REFERENCES ftth_items(id) ON DELETE CASCADE,
    UNIQUE KEY unique_odc_pon (odc_item_id, olt_item_id, pon_port)
);

-- 7. Insert sample data untuk testing
INSERT INTO ftth_items (
    item_type_id, odc_type, odc_capacity, odc_installation_type,
    name, description, latitude, longitude, address,
    odc_main_splitter_ratio, odc_odp_splitter_ratio,
    odc_input_ports, odc_output_ports, status
) VALUES 
(4, 'pole_mounted', 32, 'pole', 'ODC-Pole-001', 'ODC Pole Mounted Area Central', -0.937783, 119.854373, 'Jl. Sultan Hasanuddin No. 10', '1:4', '1:8', 1, 4, 'active'),
(12, 'ground_mounted', 64, 'ground', 'ODC-Cabinet-001', 'ODC Cabinet Ground Mounted Area North', -0.935783, 119.856373, 'Jl. Pattimura No. 25', '1:4', '1:16', 1, 4, 'active');

-- 8. Insert sample ODC ports
INSERT INTO odc_ports (odc_item_id, port_number, port_type, port_status, attenuation_dbm, notes) VALUES
-- ODC Pole Mounted (ID dari insert sebelumnya)
(LAST_INSERT_ID()-1, 1, 'input', 'connected', -3.2, 'Input dari backbone'),
(LAST_INSERT_ID()-1, 2, 'output', 'connected', -4.1, 'Output ke ODP-001'),
(LAST_INSERT_ID()-1, 3, 'output', 'connected', -4.3, 'Output ke ODP-002'),
(LAST_INSERT_ID()-1, 4, 'output', 'available', NULL, 'Port tersedia'),
(LAST_INSERT_ID()-1, 5, 'output', 'available', NULL, 'Port tersedia'),

-- ODC Cabinet (ID dari insert sebelumnya)
(LAST_INSERT_ID(), 1, 'input', 'connected', -2.8, 'Input dari backbone'),
(LAST_INSERT_ID(), 2, 'output', 'connected', -3.9, 'Output ke ODP-003'),
(LAST_INSERT_ID(), 3, 'output', 'connected', -4.1, 'Output ke ODP-004'),
(LAST_INSERT_ID(), 4, 'output', 'reserved', NULL, 'Port reserved untuk expansion'),
(LAST_INSERT_ID(), 5, 'output', 'available', NULL, 'Port tersedia');

-- 9. Insert sample ODC-PON mapping
INSERT INTO odc_pon_mapping (odc_item_id, olt_item_id, pon_port, vlan_id, description) VALUES
(LAST_INSERT_ID()-9, 1, 'PON1/1/1', '100', 'ODC Pole Central - VLAN 100'),
(LAST_INSERT_ID()-4, 1, 'PON1/1/2', '101', 'ODC Cabinet North - VLAN 101');

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
FROM odc_pon_mapping;
