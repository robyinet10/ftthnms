-- SNMP Integration Upgrade untuk FTTHNMS
-- Menambahkan SNMP monitoring capabilities untuk Server, OLT, Access Point, dan ONT

USE ftthnms;

-- 1. Extend ftth_items table dengan SNMP configuration
ALTER TABLE ftth_items ADD COLUMN snmp_enabled TINYINT(1) DEFAULT 0 AFTER ip_address;
ALTER TABLE ftth_items ADD COLUMN snmp_version VARCHAR(10) DEFAULT '2c' AFTER snmp_enabled;
ALTER TABLE ftth_items ADD COLUMN snmp_community VARCHAR(100) DEFAULT 'public' AFTER snmp_version;
ALTER TABLE ftth_items ADD COLUMN snmp_port INT DEFAULT 161 AFTER snmp_community;
ALTER TABLE ftth_items ADD COLUMN snmp_username VARCHAR(100) NULL AFTER snmp_port;  -- For SNMPv3
ALTER TABLE ftth_items ADD COLUMN snmp_auth_protocol VARCHAR(20) NULL AFTER snmp_username; -- MD5, SHA
ALTER TABLE ftth_items ADD COLUMN snmp_auth_password VARCHAR(100) NULL AFTER snmp_auth_protocol;
ALTER TABLE ftth_items ADD COLUMN snmp_priv_protocol VARCHAR(20) NULL AFTER snmp_auth_password; -- DES, AES
ALTER TABLE ftth_items ADD COLUMN snmp_priv_password VARCHAR(100) NULL AFTER snmp_priv_protocol;

-- 2. Create SNMP metrics tracking table
CREATE TABLE IF NOT EXISTS snmp_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    metric_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Device Basic Info
    device_name VARCHAR(255) NULL,
    device_description VARCHAR(500) NULL,
    device_contact VARCHAR(255) NULL,
    device_location VARCHAR(255) NULL,
    device_uptime BIGINT NULL, -- in hundredths of seconds
    
    -- System Performance
    cpu_usage_percent DECIMAL(5,2) NULL,
    memory_total_mb BIGINT NULL,
    memory_used_mb BIGINT NULL,
    memory_usage_percent DECIMAL(5,2) NULL,
    
    -- Interface Statistics (untuk port utama)
    interface_name VARCHAR(100) NULL,
    interface_status VARCHAR(20) NULL, -- up, down, testing
    interface_speed_mbps BIGINT NULL,
    interface_mtu INT NULL,
    
    -- Traffic Statistics
    bytes_in_total BIGINT NULL,
    bytes_out_total BIGINT NULL,
    packets_in_total BIGINT NULL,
    packets_out_total BIGINT NULL,
    errors_in_total BIGINT NULL,
    errors_out_total BIGINT NULL,
    
    -- Temperature (for equipment monitoring)
    temperature_celsius DECIMAL(5,2) NULL,
    
    -- Power Status
    power_status VARCHAR(50) NULL,
    power_consumption_watts DECIMAL(8,2) NULL,
    
    -- Signal Quality (for OLT/ONT)
    optical_power_tx_dbm DECIMAL(5,2) NULL,
    optical_power_rx_dbm DECIMAL(5,2) NULL,
    
    -- Custom OIDs results (JSON format)
    custom_oids JSON NULL,
    
    FOREIGN KEY (item_id) REFERENCES ftth_items(id) ON DELETE CASCADE,
    INDEX idx_item_time (item_id, metric_time),
    INDEX idx_metric_time (metric_time)
);

-- 3. Create SNMP OID mapping table untuk custom monitoring
CREATE TABLE IF NOT EXISTS snmp_oid_mapping (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_type VARCHAR(50) NOT NULL, -- 'server', 'olt', 'access_point', 'ont'
    vendor VARCHAR(100) NULL, -- 'huawei', 'cisco', 'mikrotik', etc
    model VARCHAR(100) NULL,
    oid_name VARCHAR(100) NOT NULL, -- 'cpu_usage', 'memory_usage', etc
    oid_value VARCHAR(200) NOT NULL, -- actual OID string
    oid_type VARCHAR(20) DEFAULT 'integer', -- integer, string, gauge, counter
    multiplier DECIMAL(10,4) DEFAULT 1.0, -- untuk konversi unit
    description TEXT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_device_oid (device_type, vendor, model, oid_name)
);

-- 4. Extend monitoring_logs untuk include SNMP status
ALTER TABLE monitoring_logs ADD COLUMN monitoring_type ENUM('ping', 'snmp', 'both') DEFAULT 'ping' AFTER item_id;
ALTER TABLE monitoring_logs ADD COLUMN snmp_status ENUM('success', 'failed', 'timeout', 'auth_failed') NULL AFTER error_message;
ALTER TABLE monitoring_logs ADD COLUMN snmp_error TEXT NULL AFTER snmp_status;

-- 5. Insert default SNMP OID mappings untuk common devices
INSERT INTO snmp_oid_mapping (device_type, vendor, model, oid_name, oid_value, oid_type, description) VALUES
-- Standard MIB-2 OIDs (Universal)
('server', 'generic', 'generic', 'system_name', '1.3.6.1.2.1.1.5.0', 'string', 'System Name'),
('server', 'generic', 'generic', 'system_description', '1.3.6.1.2.1.1.1.0', 'string', 'System Description'),
('server', 'generic', 'generic', 'system_uptime', '1.3.6.1.2.1.1.3.0', 'integer', 'System Uptime (hundredths of seconds)'),
('server', 'generic', 'generic', 'system_contact', '1.3.6.1.2.1.1.4.0', 'string', 'System Contact'),
('server', 'generic', 'generic', 'system_location', '1.3.6.1.2.1.1.6.0', 'string', 'System Location'),

-- CPU Usage (HOST-RESOURCES-MIB)
('server', 'generic', 'generic', 'cpu_usage', '1.3.6.1.2.1.25.3.3.1.2.1', 'integer', 'CPU Usage Percentage'),

-- Memory Usage (HOST-RESOURCES-MIB)
('server', 'generic', 'generic', 'memory_total', '1.3.6.1.2.1.25.2.3.1.5.1', 'integer', 'Total Memory in KB'),
('server', 'generic', 'generic', 'memory_used', '1.3.6.1.2.1.25.2.3.1.6.1', 'integer', 'Used Memory in KB'),

-- Interface Statistics
('server', 'generic', 'generic', 'interface_status', '1.3.6.1.2.1.2.2.1.8.1', 'integer', 'Interface Status (1=up, 2=down)'),
('server', 'generic', 'generic', 'interface_speed', '1.3.6.1.2.1.2.2.1.5.1', 'integer', 'Interface Speed in bps'),
('server', 'generic', 'generic', 'bytes_in', '1.3.6.1.2.1.2.2.1.10.1', 'counter', 'Bytes In'),
('server', 'generic', 'generic', 'bytes_out', '1.3.6.1.2.1.2.2.1.16.1', 'counter', 'Bytes Out'),

-- OLT Specific (Huawei)
('olt', 'huawei', 'generic', 'system_name', '1.3.6.1.2.1.1.5.0', 'string', 'OLT System Name'),
('olt', 'huawei', 'generic', 'system_uptime', '1.3.6.1.2.1.1.3.0', 'integer', 'OLT Uptime'),
('olt', 'huawei', 'generic', 'pon_port_status', '1.3.6.1.4.1.2011.6.128.1.1.2.43.1.9', 'integer', 'PON Port Status'),

-- Access Point Specific (MikroTik)
('access_point', 'mikrotik', 'generic', 'system_name', '1.3.6.1.2.1.1.5.0', 'string', 'AP System Name'),
('access_point', 'mikrotik', 'generic', 'cpu_usage', '1.3.6.1.2.1.25.3.3.1.2.1', 'integer', 'AP CPU Usage'),
('access_point', 'mikrotik', 'generic', 'memory_usage', '1.3.6.1.4.1.14988.1.1.1.2.1.0', 'integer', 'AP Memory Usage'),
('access_point', 'mikrotik', 'generic', 'wireless_clients', '1.3.6.1.4.1.14988.1.1.1.3.1.6', 'integer', 'Connected Wireless Clients'),

-- ONT Specific (Generic)
('ont', 'generic', 'generic', 'system_name', '1.3.6.1.2.1.1.5.0', 'string', 'ONT System Name'),
('ont', 'generic', 'generic', 'system_uptime', '1.3.6.1.2.1.1.3.0', 'integer', 'ONT Uptime'),
('ont', 'generic', 'generic', 'optical_power_rx', '1.3.6.1.4.1.2011.6.128.1.1.2.51.1.4', 'integer', 'Optical RX Power (dBm * 100)'),
('ont', 'generic', 'generic', 'optical_power_tx', '1.3.6.1.4.1.2011.6.128.1.1.2.51.1.5', 'integer', 'Optical TX Power (dBm * 100)');

-- 6. Create view untuk latest SNMP metrics
CREATE OR REPLACE VIEW latest_snmp_metrics AS
SELECT 
    sm.*,
    fi.name as item_name,
    fi.ip_address,
    it.name as item_type_name
FROM snmp_metrics sm
INNER JOIN (
    SELECT item_id, MAX(metric_time) as latest_time
    FROM snmp_metrics
    GROUP BY item_id
) latest ON sm.item_id = latest.item_id AND sm.metric_time = latest.latest_time
INNER JOIN ftth_items fi ON sm.item_id = fi.id
INNER JOIN item_types it ON fi.item_type_id = it.id;

-- 7. Create stored procedure untuk SNMP data cleanup (keep last 30 days)
DELIMITER //
CREATE PROCEDURE CleanupOldSNMPData()
BEGIN
    DELETE FROM snmp_metrics 
    WHERE metric_time < DATE_SUB(NOW(), INTERVAL 30 DAY);
    
    DELETE FROM monitoring_logs 
    WHERE ping_time < DATE_SUB(NOW(), INTERVAL 30 DAY);
END //
DELIMITER ;

-- 8. Update item_types dengan SNMP support flag
ALTER TABLE item_types ADD COLUMN supports_snmp TINYINT(1) DEFAULT 0 AFTER color;

-- Mark device types that support SNMP
UPDATE item_types SET supports_snmp = 1 WHERE name IN ('OLT', 'Server/Router', 'Access Point', 'ONT');

-- 9. Insert sample SNMP configuration for existing items (optional)
-- UPDATE ftth_items SET snmp_enabled = 1, snmp_community = 'public' 
-- WHERE item_type_id IN (SELECT id FROM item_types WHERE supports_snmp = 1);

COMMIT;

-- Display completion message
SELECT 'SNMP Integration database upgrade completed successfully!' as Status,
       'You can now configure SNMP monitoring for Server, OLT, Access Point, and ONT devices' as Info;
