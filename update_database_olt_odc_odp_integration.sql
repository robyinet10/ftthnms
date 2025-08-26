-- FTTHNMS Database Update: Complete OLT-ODC-ODP Integration
-- Versi: 4.1.0 - Enhanced OLT-ODC-ODP Chain Management  
-- Tanggal: 2025-01-XX
-- Deskripsi: Database schema untuk integrasi lengkap OLT → ODC → ODP → ONT

USE ftthnms;

-- =====================================================
-- 1. ADD ENHANCED COLUMNS FOR OLT-INTERFACE INTEGRATION  
-- =====================================================

-- Add upstream interface support for OLT
ALTER TABLE ftth_items 
ADD COLUMN IF NOT EXISTS upstream_interface_id INT NULL COMMENT 'Reference to server interface for OLT upstream connection',
ADD COLUMN IF NOT EXISTS pon_interface_mapping JSON NULL COMMENT 'JSON mapping PON ports to server interfaces for OLT';

-- =====================================================
-- 2. ADD ODP ENHANCEMENT COLUMNS
-- =====================================================

-- Add ODP-specific columns to ftth_items 
ALTER TABLE ftth_items 
ADD COLUMN IF NOT EXISTS odp_type ENUM('pole_mounted', 'wall_mounted', 'underground') DEFAULT 'pole_mounted' COMMENT 'Type of ODP installation',
ADD COLUMN IF NOT EXISTS odp_capacity INT DEFAULT 8 COMMENT 'Total customer capacity for ODP',
ADD COLUMN IF NOT EXISTS odp_ports_used INT DEFAULT 0 COMMENT 'Number of ports currently in use',
ADD COLUMN IF NOT EXISTS odp_splitter_ratio VARCHAR(10) DEFAULT '1:8' COMMENT 'Splitter ratio for ODP (1:4, 1:8, 1:16)',
ADD COLUMN IF NOT EXISTS odp_input_ports INT DEFAULT 1 COMMENT 'Number of input ports from ODC',
ADD COLUMN IF NOT EXISTS odp_output_ports INT DEFAULT 8 COMMENT 'Number of output ports to ONT',
ADD COLUMN IF NOT EXISTS odp_parent_odc_id INT NULL COMMENT 'Reference to parent ODC item';

-- =====================================================
-- 3. CREATE ODP PORTS TABLE
-- =====================================================

CREATE TABLE IF NOT EXISTS odp_ports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    odp_item_id INT NOT NULL,
    port_number INT NOT NULL,
    port_type ENUM('input', 'output') NOT NULL,
    port_status ENUM('available', 'connected', 'reserved', 'maintenance') DEFAULT 'available',
    connected_to_item_id INT NULL COMMENT 'Connected device (ODC for input, ONT for output)',
    connected_to_port VARCHAR(50) NULL COMMENT 'Port number on connected device',
    attenuation_dbm DECIMAL(5,2) NULL COMMENT 'Signal attenuation in dBm',
    ont_serial_number VARCHAR(50) NULL COMMENT 'ONT serial number if connected',
    customer_info TEXT NULL COMMENT 'Customer information for this port',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (odp_item_id) REFERENCES ftth_items(id) ON DELETE CASCADE,
    FOREIGN KEY (connected_to_item_id) REFERENCES ftth_items(id) ON DELETE SET NULL,
    UNIQUE KEY unique_odp_port (odp_item_id, port_number)
);

-- =====================================================
-- 4. CREATE ODP-ODC MAPPING TABLE
-- =====================================================

CREATE TABLE IF NOT EXISTS odp_odc_mapping (
    id INT PRIMARY KEY AUTO_INCREMENT,
    odp_item_id INT NOT NULL,
    odc_item_id INT NOT NULL,
    odc_output_port INT NOT NULL COMMENT 'Output port number on ODC',
    odp_input_port INT NOT NULL COMMENT 'Input port number on ODP',
    cable_length_m INT NULL COMMENT 'Cable length in meters',
    attenuation_dbm DECIMAL(5,2) NULL COMMENT 'Total attenuation in dBm',
    cable_type VARCHAR(50) DEFAULT 'distribution' COMMENT 'Type of cable used',
    status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (odp_item_id) REFERENCES ftth_items(id) ON DELETE CASCADE,
    FOREIGN KEY (odc_item_id) REFERENCES ftth_items(id) ON DELETE CASCADE,
    UNIQUE KEY unique_odp_odc_connection (odp_item_id, odc_item_id, odc_output_port)
);

-- =====================================================
-- 5. CREATE ENHANCED PON-INTERFACE MAPPING TABLE
-- =====================================================

CREATE TABLE IF NOT EXISTS olt_pon_interfaces (
    id INT PRIMARY KEY AUTO_INCREMENT,
    olt_item_id INT NOT NULL,
    pon_port VARCHAR(50) NOT NULL COMMENT 'PON port identifier (e.g., PON1/1/1)',
    interface_id INT NULL COMMENT 'Reference to server interface',
    vlan_id VARCHAR(10) NULL COMMENT 'VLAN ID for this PON',
    max_odcs INT DEFAULT 4 COMMENT 'Maximum ODCs that can connect to this PON',
    connected_odcs_count INT DEFAULT 0 COMMENT 'Current number of connected ODCs',
    bandwidth_profile VARCHAR(100) NULL COMMENT 'Bandwidth profile for this PON',
    status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (olt_item_id) REFERENCES ftth_items(id) ON DELETE CASCADE,
    UNIQUE KEY unique_olt_pon (olt_item_id, pon_port)
);

-- =====================================================
-- 6. CREATE INTEGRATION VIEWS FOR EASY QUERYING
-- =====================================================

-- View for complete OLT-ODC-ODP chain
CREATE OR REPLACE VIEW olt_odc_odp_chain AS
SELECT 
    olt.id as olt_id,
    olt.name as olt_name,
    olt.ip_address as olt_ip,
    opm.pon_port,
    opm.vlan_id as pon_vlan,
    odc.id as odc_id,
    odc.name as odc_name,
    odc.odc_type,
    odc.odc_capacity,
    odc.odc_output_ports,
    odp.id as odp_id,
    odp.name as odp_name,
    odp.odp_type,
    odp.odp_capacity,
    odp.odp_ports_used,
    oom.odc_output_port,
    oom.odp_input_port,
    oom.cable_length_m,
    oom.attenuation_dbm as total_attenuation
FROM ftth_items olt
JOIN odc_pon_mapping opm ON olt.id = opm.olt_item_id AND olt.item_type_id = 1
JOIN ftth_items odc ON opm.odc_item_id = odc.id AND odc.item_type_id IN (4, 12)
LEFT JOIN odp_odc_mapping oom ON odc.id = oom.odc_item_id  
LEFT JOIN ftth_items odp ON oom.odp_item_id = odp.id AND odp.item_type_id = 3
WHERE olt.status = 'active' AND odc.status = 'active';

-- View for available ODC output ports
CREATE OR REPLACE VIEW available_odc_ports AS
SELECT 
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
WHERE odc.item_type_id IN (4, 12) AND odc.status = 'active';

-- =====================================================
-- 7. ADD INDEXES FOR PERFORMANCE
-- =====================================================

CREATE INDEX IF NOT EXISTS idx_upstream_interface ON ftth_items(upstream_interface_id);
CREATE INDEX IF NOT EXISTS idx_odp_type ON ftth_items(odp_type);
CREATE INDEX IF NOT EXISTS idx_odp_parent_odc ON ftth_items(odp_parent_odc_id);
CREATE INDEX IF NOT EXISTS idx_odp_ports_item ON odp_ports(odp_item_id);
CREATE INDEX IF NOT EXISTS idx_odp_ports_status ON odp_ports(port_status);
CREATE INDEX IF NOT EXISTS idx_odp_odc_mapping_odp ON odp_odc_mapping(odp_item_id);
CREATE INDEX IF NOT EXISTS idx_odp_odc_mapping_odc ON odp_odc_mapping(odc_item_id);
CREATE INDEX IF NOT EXISTS idx_olt_pon_interfaces_olt ON olt_pon_interfaces(olt_item_id);

-- =====================================================
-- 8. ADD TRIGGERS FOR DATA CONSISTENCY
-- =====================================================

-- Trigger to update ODC ports used count
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS update_odc_ports_used 
AFTER INSERT ON odp_odc_mapping
FOR EACH ROW
BEGIN
    UPDATE ftth_items 
    SET odc_ports_used = (
        SELECT COUNT(*) 
        FROM odp_odc_mapping 
        WHERE odc_item_id = NEW.odc_item_id AND status = 'active'
    )
    WHERE id = NEW.odc_item_id;
END$$

CREATE TRIGGER IF NOT EXISTS update_odc_ports_used_delete
AFTER DELETE ON odp_odc_mapping
FOR EACH ROW
BEGIN
    UPDATE ftth_items 
    SET odc_ports_used = (
        SELECT COUNT(*) 
        FROM odp_odc_mapping 
        WHERE odc_item_id = OLD.odc_item_id AND status = 'active'
    )
    WHERE id = OLD.odc_item_id;
END$$

-- Trigger to update ODP ports used count
CREATE TRIGGER IF NOT EXISTS update_odp_ports_used
AFTER INSERT ON odp_ports
FOR EACH ROW
BEGIN
    IF NEW.port_type = 'output' AND NEW.port_status = 'connected' THEN
        UPDATE ftth_items 
        SET odp_ports_used = (
            SELECT COUNT(*) 
            FROM odp_ports 
            WHERE odp_item_id = NEW.odp_item_id 
            AND port_type = 'output' 
            AND port_status = 'connected'
        )
        WHERE id = NEW.odp_item_id;
    END IF;
END$$

CREATE TRIGGER IF NOT EXISTS update_odp_ports_used_update
AFTER UPDATE ON odp_ports
FOR EACH ROW
BEGIN
    IF NEW.port_type = 'output' THEN
        UPDATE ftth_items 
        SET odp_ports_used = (
            SELECT COUNT(*) 
            FROM odp_ports 
            WHERE odp_item_id = NEW.odp_item_id 
            AND port_type = 'output' 
            AND port_status = 'connected'
        )
        WHERE id = NEW.odp_item_id;
    END IF;
END$$

DELIMITER ;

-- =====================================================
-- 9. INSERT SAMPLE DATA FOR TESTING
-- =====================================================

-- Sample ODP items
INSERT IGNORE INTO ftth_items (
    item_type_id, odp_type, odp_capacity, odp_splitter_ratio,
    name, description, latitude, longitude, address,
    odp_input_ports, odp_output_ports, status
) VALUES 
(3, 'pole_mounted', 8, '1:8', 'ODP-Central-001', 'ODP Area Central Blok A', -0.937800, 119.854400, 'Jl. Sultan Hasanuddin No. 15', 1, 8, 'active'),
(3, 'wall_mounted', 16, '1:16', 'ODP-Central-002', 'ODP Area Central Blok B', -0.937850, 119.854450, 'Jl. Sultan Hasanuddin No. 20', 1, 16, 'active'),
(3, 'pole_mounted', 8, '1:8', 'ODP-East-001', 'ODP Area East Side', -0.937900, 119.854500, 'Jl. Ahmad Yani No. 5', 1, 8, 'active');

-- Sample OLT PON Interface mappings (assuming some existing OLT and interfaces)
-- This will be populated through the API when OLT forms are submitted

COMMIT;

-- =====================================================
-- 10. VERIFICATION QUERIES
-- =====================================================

-- Verify new columns
SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_COMMENT 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'ftthnms' 
AND TABLE_NAME = 'ftth_items' 
AND COLUMN_NAME LIKE '%odp%' OR COLUMN_NAME LIKE '%upstream%' OR COLUMN_NAME LIKE '%pon_interface%';

-- Verify new tables
SELECT TABLE_NAME, TABLE_COMMENT 
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = 'ftthnms' 
AND TABLE_NAME IN ('odp_ports', 'odp_odc_mapping', 'olt_pon_interfaces');

-- Verify views
SELECT TABLE_NAME, VIEW_DEFINITION 
FROM INFORMATION_SCHEMA.VIEWS 
WHERE TABLE_SCHEMA = 'ftthnms';

SELECT 'Database integration schema created successfully!' as status;
