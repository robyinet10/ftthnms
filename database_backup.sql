-- Database untuk FTTH Network Management System (FTTHNMS) dengan Authentication System
-- Versi: 5.0.0 (Complete Version - Dilengkapi dari Export Database ftthnms.sql)
-- Tanggal: 2025-08-22
-- Fitur Baru: Auto Generate Tiang Tumpu, ODP Management, Enhanced ODC, Pricing Management, Triggers & Views
CREATE DATABASE IF NOT EXISTS ftthnms;
USE ftthnms;

-- Tabel untuk menyimpan jenis item FTTH
CREATE TABLE item_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    icon VARCHAR(100),
    color VARCHAR(20),
    supports_snmp TINYINT(1) DEFAULT 0 COMMENT 'Support SNMP monitoring',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel untuk menyimpan warna tube
CREATE TABLE tube_colors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    color_name VARCHAR(30) NOT NULL,
    hex_code VARCHAR(7),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel untuk menyimpan jenis splitter
CREATE TABLE splitter_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type VARCHAR(20) NOT NULL, -- 'main' untuk jaringan utama, 'odp' untuk ODP
    ratio VARCHAR(10) NOT NULL, -- 1:2, 1:3, 1:4, 1:8, 1:16
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ===== INTERFACE MONITORING & TOPOLOGY MAPPING TABLES =====
-- Moved here to resolve foreign key dependencies

-- Tabel untuk menyimpan interface information (persistent)
CREATE TABLE device_interfaces (
    id INT PRIMARY KEY AUTO_INCREMENT,
    device_id INT NOT NULL,
    interface_index INT NOT NULL,
    interface_name VARCHAR(255) NOT NULL,
    interface_description VARCHAR(255),
    interface_type VARCHAR(100),
    interface_type_id INT,
    mac_address VARCHAR(17),
    mtu INT,
    speed_bps BIGINT,
    admin_status ENUM('up', 'down', 'testing') DEFAULT 'down',
    oper_status ENUM('up', 'down', 'testing', 'unknown', 'dormant', 'notPresent', 'lowerLayerDown') DEFAULT 'unknown',
    last_change BIGINT,
    physical_address VARCHAR(255),
    alias_name VARCHAR(255),
    -- Interface capabilities
    duplex_mode ENUM('unknown', 'half', 'full') DEFAULT 'unknown',
    auto_negotiation TINYINT(1) DEFAULT 0,
    -- Status tracking
    is_active TINYINT(1) DEFAULT 1,
    first_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_device_interfaces_device (device_id),
    INDEX idx_device_interfaces_name (interface_name),
    INDEX idx_device_interfaces_type (interface_type),
    INDEX idx_device_interfaces_status (oper_status, admin_status),
    INDEX idx_device_interfaces_active (is_active, last_seen)
);

-- Tabel utama untuk menyimpan item-item FTTH di maps
CREATE TABLE ftth_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_type_id INT,
    -- ODC Enhancement Columns
    odc_type ENUM('pole_mounted', 'ground_mounted') DEFAULT 'pole_mounted',
    odc_capacity INT DEFAULT 32,
    odc_ports_used INT DEFAULT 0,
    odc_installation_type ENUM('pole', 'ground', 'wall') DEFAULT 'pole',
    odc_main_splitter_ratio VARCHAR(10) DEFAULT '1:4',
    odc_odp_splitter_ratio VARCHAR(10) DEFAULT '1:8',
    odc_input_ports INT DEFAULT 1,
    odc_output_ports INT DEFAULT 4,
    odc_pon_connection VARCHAR(50) DEFAULT NULL,
    odc_vlan_id VARCHAR(10) DEFAULT NULL COMMENT 'VLAN ID from OLT PON connection',
    -- Standard Columns
    item_type VARCHAR(100) NULL COMMENT 'Type/Model dari item (contoh: Huawei MA5800-X2, Tiang Beton 9m, dll)',
    item_price DECIMAL(12,2) NULL COMMENT 'Harga item dalam Rupiah',
    name VARCHAR(100) NOT NULL,
    description TEXT,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    address TEXT,
    tube_color_id INT,
    core_used INT COMMENT 'Core yang sedang digunakan dari total kapasitas',
    core_color_id INT NULL COMMENT 'Warna core yang digunakan (referensi ke tube_colors)',
    item_cable_type ENUM('backbone', 'distribution', 'drop_core', 'feeder', 'branch') NULL DEFAULT 'distribution' COMMENT 'Jenis kabel yang digunakan pada item ini',
    total_core_capacity INT NULL DEFAULT 24 COMMENT 'Total kapasitas core untuk item ini',
    splitter_main_id INT,
    splitter_odp_id INT,
    ip_address VARCHAR(45) NULL COMMENT 'IP Address untuk monitoring (IPv4/IPv6)',
    upstream_interface_id INT NULL COMMENT 'Reference to server interface for OLT',
    port_http INT NULL DEFAULT 80 COMMENT 'Port HTTP untuk monitoring web service',
    port_https INT NULL DEFAULT 443 COMMENT 'Port HTTPS untuk monitoring web service',
    monitoring_status ENUM('online', 'warning', 'offline') NULL DEFAULT 'offline' COMMENT 'Status monitoring real-time',
    vlan_config JSON NULL COMMENT 'JSON configuration for Server VLANs',
    pon_config JSON NULL COMMENT 'JSON configuration for OLT PON ports and VLANs',
    attenuation_notes TEXT NULL COMMENT 'Catatan redaman dalam dB untuk ODC',
    last_ping_time TIMESTAMP NULL COMMENT 'Waktu ping terakhir',
    response_time_ms INT NULL COMMENT 'Response time dalam milliseconds',
    -- SNMP Integration Columns
    snmp_enabled TINYINT(1) DEFAULT 0 COMMENT 'Enable SNMP monitoring untuk device ini',
    snmp_version ENUM('1', '2c', '3') DEFAULT '2c' COMMENT 'SNMP version yang digunakan',
    snmp_community VARCHAR(255) DEFAULT 'public' COMMENT 'SNMP community string untuk SNMPv1/v2c',
    snmp_port INT DEFAULT 161 COMMENT 'SNMP port (default 161)',
    snmp_username VARCHAR(255) NULL COMMENT 'Username untuk SNMPv3',
    snmp_auth_protocol ENUM('MD5', 'SHA') NULL COMMENT 'Authentication protocol untuk SNMPv3',
    snmp_auth_password VARCHAR(255) NULL COMMENT 'Authentication password untuk SNMPv3',
    snmp_priv_protocol ENUM('DES', 'AES') NULL COMMENT 'Privacy protocol untuk SNMPv3',
    snmp_priv_password VARCHAR(255) NULL COMMENT 'Privacy password untuk SNMPv3',
    cpu_usage DECIMAL(5,2) NULL COMMENT 'CPU usage percentage dari SNMP',
    memory_usage DECIMAL(5,2) NULL COMMENT 'Memory usage percentage dari SNMP',
    is_auto_generated TINYINT(1) DEFAULT 0 COMMENT 'Flag untuk item yang di-generate otomatis',
    auto_generated_by_route_id INT NULL COMMENT 'ID route yang generate item ini',
    auto_generated_type ENUM('interval','turn','manual') DEFAULT NULL COMMENT 'Tipe auto generation',
    pon_interface_mapping LONGTEXT NULL COMMENT 'JSON mapping PON ports to server interfaces for OLT' CHECK (JSON_VALID(pon_interface_mapping)),
    odp_type ENUM('pole_mounted','wall_mounted','underground') DEFAULT 'pole_mounted' COMMENT 'Type of ODP installation',
    odp_capacity INT DEFAULT 8 COMMENT 'Total customer capacity for ODP',
    odp_ports_used INT DEFAULT 0 COMMENT 'Number of ports currently in use',
    odp_main_splitter_ratio VARCHAR(10) DEFAULT '1:8' COMMENT 'Main splitter ratio for ODP',
    odp_input_ports INT DEFAULT 1 COMMENT 'Number of input ports for ODP',
    odp_output_ports INT DEFAULT 8 COMMENT 'Number of output ports for ODP',
    odp_parent_odc_id INT NULL COMMENT 'Parent ODC that feeds this ODP',
    ont_serial_number VARCHAR(50) NULL COMMENT 'ONT serial number for HTB items',
    ont_model VARCHAR(100) NULL COMMENT 'ONT model/type for HTB items',
    ont_connected_odp_id INT NULL COMMENT 'ODP that this ONT is connected to',
    ont_connected_port INT NULL COMMENT 'Port number on ODP that this ONT is connected to',
    ont_installation_type ENUM('indoor','outdoor') DEFAULT 'indoor' COMMENT 'ONT installation type',
    customer_name VARCHAR(255) NULL COMMENT 'Customer name for HTB items',
    customer_phone VARCHAR(20) NULL COMMENT 'Customer phone number',
    customer_address TEXT NULL COMMENT 'Customer address',
    package_type VARCHAR(100) NULL COMMENT 'Internet package type',
    bandwidth_profile VARCHAR(100) NULL COMMENT 'Bandwidth profile',
    connection_status ENUM('connected','disconnected','suspended','maintenance') DEFAULT 'connected' COMMENT 'Connection status for HTB',
    status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (item_type_id) REFERENCES item_types(id),
    FOREIGN KEY (tube_color_id) REFERENCES tube_colors(id),
    FOREIGN KEY (core_color_id) REFERENCES tube_colors(id),
    FOREIGN KEY (splitter_main_id) REFERENCES splitter_types(id),
    FOREIGN KEY (splitter_odp_id) REFERENCES splitter_types(id),
    FOREIGN KEY (upstream_interface_id) REFERENCES device_interfaces(id) ON DELETE SET NULL,
    FOREIGN KEY (auto_generated_by_route_id) REFERENCES cable_routes(id) ON DELETE SET NULL,
    FOREIGN KEY (odp_parent_odc_id) REFERENCES ftth_items(id) ON DELETE SET NULL,
    FOREIGN KEY (ont_connected_odp_id) REFERENCES ftth_items(id) ON DELETE SET NULL,
    CONSTRAINT chk_item_price_positive CHECK (item_price >= 0)
);

-- Tabel untuk menyimpan routing kabel
CREATE TABLE cable_routes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    from_item_id INT,
    to_item_id INT,
    route_coordinates TEXT, -- JSON array of lat,lng coordinates
    distance DECIMAL(8,2), -- dalam meter
    cable_type VARCHAR(50),
    core_count INT,
    route_type ENUM('straight', 'road', 'direct') DEFAULT 'straight' COMMENT 'Jenis route: straight=garis lurus, road=ikuti jalan, direct=fallback',
    status ENUM('planned', 'installed', 'maintenance') DEFAULT 'planned',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    auto_generate_tiang_tumpu TINYINT(1) DEFAULT 0 COMMENT 'Flag untuk auto generate tiang tumpu',
    generated_tiang_tumpu_ids TEXT NULL COMMENT 'JSON array dari ID tiang tumpu yang di-generate otomatis',
    tiang_tumpu_interval_meters INT DEFAULT 30 COMMENT 'Interval jarak untuk generate tiang tumpu dalam meter',
    generate_at_turns TINYINT(1) DEFAULT 1 COMMENT 'Generate tiang tumpu di tikungan',
    total_generated_cost DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Total biaya tiang tumpu yang di-generate (Rupiah)',
    FOREIGN KEY (from_item_id) REFERENCES ftth_items(id),
    FOREIGN KEY (to_item_id) REFERENCES ftth_items(id)
);

-- Tabel untuk menyimpan user dan role
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'teknisi') NOT NULL DEFAULT 'teknisi',
    full_name VARCHAR(100),
    email VARCHAR(100),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabel untuk menyimpan log monitoring
CREATE TABLE monitoring_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_id INT NOT NULL,
    monitoring_type ENUM('ping', 'snmp', 'both') DEFAULT 'ping' COMMENT 'Type of monitoring performed',
    ping_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('online', 'warning', 'offline') NOT NULL,
    response_time_ms INT NULL,
    error_message TEXT NULL,
    snmp_status ENUM('success', 'failed', 'timeout', 'auth_failed') NULL COMMENT 'SNMP specific status',
    snmp_error TEXT NULL COMMENT 'SNMP specific error message',
    FOREIGN KEY (item_id) REFERENCES ftth_items(id) ON DELETE CASCADE
);

-- Tabel untuk menyimpan konfigurasi VLAN Server/Router
CREATE TABLE server_vlans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_id INT NOT NULL,
    vlan_id INT NOT NULL,
    ip_address VARCHAR(45),
    subnet_mask VARCHAR(15),
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES ftth_items(id) ON DELETE CASCADE,
    UNIQUE KEY unique_item_vlan (item_id, vlan_id)
);

-- Tabel untuk menyimpan konfigurasi PON OLT (alternative to JSON pon_config)
CREATE TABLE olt_pons (
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

-- Tabel untuk menyimpan relasi PON-VLAN
CREATE TABLE pon_vlans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    pon_id INT NOT NULL,
    vlan_id VARCHAR(10) NOT NULL COMMENT 'VLAN ID (e.g., 100, 200)',
    description VARCHAR(255) NULL COMMENT 'VLAN description',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (pon_id) REFERENCES olt_pons(id) ON DELETE CASCADE,
    UNIQUE KEY unique_pon_vlan (pon_id, vlan_id)
);

-- Tabel untuk ODC port management
CREATE TABLE odc_ports (
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

-- Tabel untuk ODC-PON mapping
CREATE TABLE odc_pon_mapping (
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

-- SNMP Integration Tables
CREATE TABLE snmp_metrics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_id INT NOT NULL,
    metric_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    device_name VARCHAR(255),
    device_description VARCHAR(500),
    device_contact VARCHAR(255),
    device_location VARCHAR(255),
    device_uptime BIGINT,
    cpu_usage_percent DECIMAL(5,2),
    memory_total_mb BIGINT,
    memory_used_mb BIGINT,
    memory_usage_percent DECIMAL(5,2),
    interface_name VARCHAR(100),
    interface_status VARCHAR(20),
    interface_speed_mbps BIGINT,
    interface_mtu INT,
    bytes_in_total BIGINT,
    bytes_out_total BIGINT,
    packets_in_total BIGINT,
    packets_out_total BIGINT,
    errors_in_total BIGINT,
    errors_out_total BIGINT,
    temperature_celsius DECIMAL(5,2),
    power_status VARCHAR(50),
    power_consumption_watts DECIMAL(8,2),
    optical_power_tx_dbm DECIMAL(5,2),
    optical_power_rx_dbm DECIMAL(5,2),
    custom_oids LONGTEXT,
    status ENUM('success', 'failed', 'timeout') DEFAULT 'success',
    error_message TEXT,
    FOREIGN KEY (item_id) REFERENCES ftth_items(id) ON DELETE CASCADE
);

CREATE TABLE snmp_oid_mapping (
    id INT PRIMARY KEY AUTO_INCREMENT,
    device_type VARCHAR(50) NOT NULL,
    oid_name VARCHAR(100) NOT NULL,
    oid_value VARCHAR(255) NOT NULL,
    data_type ENUM('string', 'integer', 'counter', 'gauge', 'timeticks') DEFAULT 'string',
    unit VARCHAR(20),
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_device_oid (device_type, oid_name)
);

-- Insert data default untuk item types
INSERT INTO item_types (name, icon, color, supports_snmp) VALUES
('OLT', 'fas fa-server', '#FF6B6B', 1),
('Tiang Tumpu', 'fas fa-tower-broadcast', '#4ECDC4', 0),
('Tiang ODP', 'fas fa-project-diagram', '#45B7D1', 0),
('ODC Pole Mounted', 'fas fa-network-wired', '#96CEB4', 0),
('Tiang Joint Closure', 'fas fa-link', '#E74C3C', 0),
('ONT', 'fas fa-home', '#FFA500', 1),
('Server', 'fas fa-server', '#8E44AD', 1),
('Access Point', 'fas fa-wifi', '#3498DB', 1),
('Custom', 'fas fa-tools', '#9B59B6', 0),
('HTB', 'fas fa-home', '#FF6B9D', 1),
('ODC Cabinet', 'fas fa-box', '#F39C12', 0);

-- Insert data default untuk warna tube (32 warna total)
INSERT INTO tube_colors (color_name, hex_code) VALUES
('Biru', '#0066CC'),
('Orange', '#FF6600'),
('Hijau', '#00CC66'),
('Coklat', '#996633'),
('Abu-abu', '#666666'),
('Putih', '#FFFFFF'),
('Merah', '#CC0000'),
('Hitam', '#000000'),
('Kuning', '#FFCC00'),
('Violet', '#9900CC'),
('Pink', '#FF6699'),
('Aqua', '#00CCCC'),
('Turquoise', '#40E0D0'),
('Lime', '#32CD32'),
('Magenta', '#FF00FF'),
('Cyan', '#00FFFF'),
('Indigo', '#4B0082'),
('Crimson', '#DC143C'),
('Gold', '#FFD700'),
('Silver', '#C0C0C0'),
('Teal', '#008080'),
('Navy', '#000080'),
('Coral', '#FF7F50'),
('Salmon', '#FA8072'),
('Lavender', '#E6E6FA'),
('Beige', '#F5F5DC'),
('Olive', '#808000'),
('Maroon', '#800000'),
('Khaki', '#F0E68C'),
('Plum', '#DDA0DD'),
('Bronze', '#CD7F32'),
('Emerald', '#50C878');

-- Insert data default untuk splitter types
INSERT INTO splitter_types (type, ratio) VALUES
('main', '1:2'),
('main', '1:3'),
('main', '1:4'),
('odp', '1:2'),
('odp', '1:4'),
('odp', '1:8'),
('odp', '1:16');

-- Tabel untuk backup item types yang dihapus
CREATE TABLE backup_removed_item_types (
    id INT NOT NULL,
    name VARCHAR(50) NOT NULL,
    icon VARCHAR(100),
    color VARCHAR(20),
    supports_snmp TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel untuk default pricing settings
CREATE TABLE default_pricing (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_type VARCHAR(50) NOT NULL COMMENT 'Tipe setting: tiang_tumpu, kabel, etc',
    price_value DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Harga default dalam Rupiah',
    auto_calculate TINYINT(1) DEFAULT 1 COMMENT 'Flag auto calculate cost saat generate',
    description TEXT NULL COMMENT 'Deskripsi setting pricing',
    created_by INT NULL COMMENT 'User yang create setting',
    updated_by INT NULL COMMENT 'User yang terakhir update',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Tabel untuk ODP-ODC mapping
CREATE TABLE odp_odc_mapping (
    id INT PRIMARY KEY AUTO_INCREMENT,
    odp_item_id INT NOT NULL,
    odc_item_id INT NOT NULL,
    odc_output_port INT NOT NULL COMMENT 'Output port number on ODC',
    odp_input_port INT NOT NULL COMMENT 'Input port number on ODP',
    cable_length_m INT NULL COMMENT 'Cable length in meters',
    attenuation_dbm DECIMAL(5,2) NULL COMMENT 'Total attenuation in dBm',
    cable_type VARCHAR(50) DEFAULT 'distribution' COMMENT 'Type of cable used',
    status ENUM('active','inactive','maintenance') DEFAULT 'active',
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (odp_item_id) REFERENCES ftth_items(id) ON DELETE CASCADE,
    FOREIGN KEY (odc_item_id) REFERENCES ftth_items(id) ON DELETE CASCADE
);

-- Tabel untuk ODP ports management
CREATE TABLE odp_ports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    odp_item_id INT NOT NULL,
    port_number INT NOT NULL,
    port_type ENUM('input','output') NOT NULL,
    port_status ENUM('available','connected','reserved','maintenance') DEFAULT 'available',
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

-- Tabel untuk OLT PON interfaces (alternative to JSON pon_config)
CREATE TABLE olt_pon_interfaces (
    id INT PRIMARY KEY AUTO_INCREMENT,
    olt_item_id INT NOT NULL,
    pon_port VARCHAR(50) NOT NULL COMMENT 'PON port identifier (e.g., PON1/1/1)',
    interface_id INT NULL COMMENT 'Reference to server interface',
    vlan_id VARCHAR(10) NULL COMMENT 'VLAN ID for this PON',
    max_odcs INT DEFAULT 4 COMMENT 'Maximum ODCs that can connect to this PON',
    connected_odcs_count INT DEFAULT 0 COMMENT 'Current number of connected ODCs',
    bandwidth_profile VARCHAR(100) NULL COMMENT 'Bandwidth profile for this PON',
    status ENUM('active','inactive','maintenance') DEFAULT 'active',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (olt_item_id) REFERENCES ftth_items(id) ON DELETE CASCADE,
    FOREIGN KEY (interface_id) REFERENCES device_interfaces(id) ON DELETE SET NULL,
    UNIQUE KEY unique_olt_pon (olt_item_id, pon_port)
);

-- Insert SNMP OID mapping untuk monitoring
INSERT INTO snmp_oid_mapping (device_type, oid_name, oid_value, data_type, unit, description, is_active) VALUES
-- Universal System OIDs
('universal', 'sysDescr', '1.3.6.1.2.1.1.1.0', 'string', '', 'System Description', 1),
('universal', 'sysName', '1.3.6.1.2.1.1.5.0', 'string', '', 'System Name', 1),
('universal', 'sysUpTime', '1.3.6.1.2.1.1.3.0', 'timeticks', 'seconds', 'System Uptime', 1),
('universal', 'sysContact', '1.3.6.1.2.1.1.4.0', 'string', '', 'System Contact', 1),
('universal', 'sysLocation', '1.3.6.1.2.1.1.6.0', 'string', '', 'System Location', 1),

-- Server-specific OIDs
('server', 'hrProcessorLoad', '1.3.6.1.2.1.25.3.3.1.2.1', 'gauge', 'percent', 'CPU Usage Percentage', 1),
('server', 'hrMemorySize', '1.3.6.1.2.1.25.2.2.0', 'gauge', 'KB', 'Total Memory Size', 1),
('server', 'hrStorageUsed', '1.3.6.1.2.1.25.2.3.1.6.1', 'gauge', 'KB', 'Used Memory', 1),

-- OLT-specific OIDs (Generic SNMP)
('olt', 'ifInOctets', '1.3.6.1.2.1.2.2.1.10', 'counter', 'bytes', 'Interface Input Bytes', 1),
('olt', 'ifOutOctets', '1.3.6.1.2.1.2.2.1.16', 'counter', 'bytes', 'Interface Output Bytes', 1),
('olt', 'ifOperStatus', '1.3.6.1.2.1.2.2.1.8', 'integer', '', 'Interface Operational Status', 1),

-- Access Point-specific OIDs
('access point', 'dot11AuthenticationAlgorithm', '1.3.6.1.2.1.10.127.1.1.1.1.2', 'integer', '', 'WiFi Authentication', 1),
('access point', 'dot11WEPDefaultKeyValue', '1.3.6.1.2.1.10.127.1.2.1.1.2', 'string', '', 'WiFi Security Status', 1),

-- ONT-specific OIDs
('ont', 'ifSpeed', '1.3.6.1.2.1.2.2.1.5', 'gauge', 'bps', 'Interface Speed', 1),
('ont', 'ifPhysAddress', '1.3.6.1.2.1.2.2.1.6', 'string', '', 'Physical MAC Address', 1);

-- Insert user default dengan sistem authentication
-- Password untuk semua user: "password" (hashed dengan bcrypt)
INSERT INTO users (username, password, role, full_name, email) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Administrator System', 'admin@ftthnms.com'),
('teknisi1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teknisi', 'Teknisi Lapangan 1', 'teknisi1@ftthnms.com'),
('teknisi2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teknisi', 'Teknisi Lapangan 2', 'teknisi2@ftthnms.com'),
('supervisor', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Supervisor Jaringan', 'supervisor@ftthnms.com');

-- Insert data default untuk default pricing
INSERT INTO default_pricing (setting_type, price_value, auto_calculate, description, created_by, updated_by) VALUES
('tiang_tumpu', 750000.00, 1, 'Default harga untuk auto-generate tiang tumpu (per unit)', 1, 1),
('kabel_fiber', 15000.00, 1, 'Default harga kabel fiber per meter', 1, 1),
('splitter_1x4', 250000.00, 1, 'Default harga splitter 1:4', 1, 1),
('splitter_1x8', 350000.00, 1, 'Default harga splitter 1:8', 1, 1);

-- Create indexes for performance optimization
CREATE INDEX idx_monitoring_logs_item_time ON monitoring_logs(item_id, ping_time);
CREATE INDEX idx_monitoring_logs_type ON monitoring_logs(monitoring_type);
CREATE INDEX idx_monitoring_logs_snmp_status ON monitoring_logs(snmp_status);
CREATE INDEX idx_core_color ON ftth_items(core_color_id);
CREATE INDEX idx_cable_type ON ftth_items(item_cable_type);
CREATE INDEX idx_core_usage ON ftth_items(core_used, total_core_capacity);
CREATE INDEX idx_monitoring_status ON ftth_items(monitoring_status);
CREATE INDEX idx_ip_address ON ftth_items(ip_address);
CREATE INDEX idx_item_type ON ftth_items(item_type);
CREATE INDEX idx_item_price ON ftth_items(item_price);
CREATE INDEX idx_snmp_enabled ON ftth_items(snmp_enabled);
CREATE INDEX idx_snmp_status ON ftth_items(snmp_enabled, monitoring_status);
-- ODC Enhancement Indexes
CREATE INDEX idx_odc_type ON ftth_items(odc_type);
CREATE INDEX idx_odc_capacity ON ftth_items(odc_capacity);
CREATE INDEX idx_odc_pon_connection ON ftth_items(odc_pon_connection);
-- Standard Indexes
CREATE INDEX idx_monitoring_logs_item ON monitoring_logs(item_id, ping_time);
CREATE INDEX idx_username ON users(username);
CREATE INDEX idx_user_role ON users(role);

-- Indexes for server_vlans table
CREATE INDEX idx_server_vlans_item ON server_vlans(item_id);
CREATE INDEX idx_server_vlans_vlan_id ON server_vlans(vlan_id);

-- Indexes for OLT PON tables
CREATE INDEX idx_olt_pons_item ON olt_pons(item_id);
CREATE INDEX idx_olt_pons_port ON olt_pons(pon_port);
CREATE INDEX idx_pon_vlans_pon ON pon_vlans(pon_id);
CREATE INDEX idx_pon_vlans_vlan_id ON pon_vlans(vlan_id);

-- Indexes for SNMP tables
CREATE INDEX idx_snmp_metrics_item ON snmp_metrics(item_id, metric_time);
CREATE INDEX idx_snmp_metrics_time ON snmp_metrics(metric_time);
CREATE INDEX idx_snmp_oid_device ON snmp_oid_mapping(device_type, is_active);

-- Indexes untuk tabel baru
CREATE INDEX idx_auto_generate_tiang ON cable_routes(auto_generate_tiang_tumpu);
CREATE INDEX idx_cable_routes_cost ON cable_routes(total_generated_cost);
CREATE INDEX idx_upstream_interface ON ftth_items(upstream_interface_id);
CREATE INDEX idx_auto_generated ON ftth_items(is_auto_generated);
CREATE INDEX idx_auto_generated_route ON ftth_items(auto_generated_by_route_id);
CREATE INDEX idx_odp_type ON ftth_items(odp_type);
CREATE INDEX idx_odp_parent_odc ON ftth_items(odp_parent_odc_id);
CREATE INDEX idx_ftth_items_odc_vlan ON ftth_items(odc_vlan_id);
CREATE INDEX idx_ont_connected_odp ON ftth_items(ont_connected_odp_id);
CREATE INDEX idx_ont_connected_port ON ftth_items(ont_connected_port);
CREATE INDEX idx_default_pricing_type ON default_pricing(setting_type);
CREATE INDEX idx_odp_odc_mapping_odp ON odp_odc_mapping(odp_item_id);
CREATE INDEX idx_odp_odc_mapping_odc ON odp_odc_mapping(odc_item_id);
CREATE INDEX idx_odp_ports_odp ON odp_ports(odp_item_id);
CREATE INDEX idx_odp_ports_type_status ON odp_ports(port_type, port_status);
CREATE INDEX idx_olt_pon_interfaces_olt ON olt_pon_interfaces(olt_item_id);
CREATE INDEX idx_olt_pon_interfaces_port ON olt_pon_interfaces(pon_port);

-- Create view for latest SNMP metrics
CREATE VIEW latest_snmp_metrics AS
SELECT 
    sm1.*
FROM snmp_metrics sm1
INNER JOIN (
    SELECT item_id, MAX(metric_time) as max_time
    FROM snmp_metrics 
    GROUP BY item_id
) sm2 ON sm1.item_id = sm2.item_id AND sm1.metric_time = sm2.max_time;

-- View untuk available ODC ports
CREATE VIEW available_odc_ports AS
SELECT 
    fi.id as odc_id,
    fi.name as odc_name,
    fi.odc_output_ports,
    COALESCE(COUNT(oom.id), 0) as used_ports,
    (fi.odc_output_ports - COALESCE(COUNT(oom.id), 0)) as available_ports
FROM ftth_items fi
LEFT JOIN odp_odc_mapping oom ON fi.id = oom.odc_item_id AND oom.status = 'active'
WHERE fi.item_type_id IN (4, 12) -- ODC Pole Mounted, ODC Cabinet
GROUP BY fi.id, fi.name, fi.odc_output_ports;

-- View untuk pricing statistics
CREATE VIEW pricing_statistics AS
SELECT 'tiang_tumpu' AS item_category,
    COUNT(CASE WHEN is_auto_generated = 1 THEN 1 END) AS auto_generated_count,
    COUNT(CASE WHEN is_auto_generated = 0 THEN 1 END) AS manual_count,
    COUNT(*) AS total_count,
    COALESCE(SUM(CASE WHEN is_auto_generated = 1 THEN item_price END), 0) AS auto_generated_cost,
    COALESCE(SUM(CASE WHEN is_auto_generated = 0 THEN item_price END), 0) AS manual_cost,
    COALESCE(SUM(item_price), 0) AS total_cost,
    COALESCE(AVG(CASE WHEN is_auto_generated = 1 THEN item_price END), 0) AS avg_auto_price,
    COALESCE(AVG(CASE WHEN is_auto_generated = 0 THEN item_price END), 0) AS avg_manual_price,
    COALESCE(AVG(item_price), 0) AS avg_total_price
FROM ftth_items
WHERE item_type_id = 2 -- Tiang Tumpu
UNION ALL
SELECT 'odc' AS item_category,
    COUNT(CASE WHEN is_auto_generated = 1 THEN 1 END) AS auto_generated_count,
    COUNT(CASE WHEN is_auto_generated = 0 THEN 1 END) AS manual_count,
    COUNT(*) AS total_count,
    COALESCE(SUM(CASE WHEN is_auto_generated = 1 THEN item_price END), 0) AS auto_generated_cost,
    COALESCE(SUM(CASE WHEN is_auto_generated = 0 THEN item_price END), 0) AS manual_cost,
    COALESCE(SUM(item_price), 0) AS total_cost,
    COALESCE(AVG(CASE WHEN is_auto_generated = 1 THEN item_price END), 0) AS avg_auto_price,
    COALESCE(AVG(CASE WHEN is_auto_generated = 0 THEN item_price END), 0) AS avg_manual_price,
    COALESCE(AVG(item_price), 0) AS avg_total_price
FROM ftth_items
WHERE item_type_id IN (4, 12); -- ODC

-- View untuk OLT-ODC-ODP chain
CREATE VIEW olt_odc_odp_chain AS
SELECT 
    olt.id AS olt_id,
    olt.name AS olt_name,
    olt.ip_address AS olt_ip,
    opm.pon_port,
    opm.vlan_id AS pon_vlan,
    odc.id AS odc_id,
    odc.name AS odc_name,
    odc.odc_type,
    odc.odc_capacity,
    odc.odc_output_ports,
    odp.id AS odp_id,
    odp.name AS odp_name,
    odp.odp_type,
    odp.odp_capacity,
    odp.odp_ports_used,
    oom.odc_output_port,
    oom.odp_input_port,
    oom.cable_length_m,
    oom.attenuation_dbm AS total_attenuation
FROM ftth_items olt
JOIN odc_pon_mapping opm ON olt.id = opm.olt_item_id AND olt.item_type_id = 1
JOIN ftth_items odc ON opm.odc_item_id = odc.id AND odc.item_type_id IN (4, 12)
JOIN odp_odc_mapping oom ON odc.id = oom.odc_item_id
JOIN ftth_items odp ON oom.odp_item_id = odp.id AND odp.item_type_id = 3
WHERE opm.status = 'active' AND oom.status = 'active';

-- ===== ADDITIONAL INTERFACE TABLES =====

-- Tabel untuk menyimpan IP addresses per interface (persistent)
CREATE TABLE interface_ip_addresses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    interface_id INT NOT NULL,
    device_id INT NOT NULL,
    ip_address VARCHAR(45) NOT NULL, -- Support IPv4 and IPv6
    netmask VARCHAR(45),
    prefix_length INT,
    ip_version ENUM('ipv4', 'ipv6') DEFAULT 'ipv4',
    address_type ENUM('primary', 'secondary', 'alias', 'anycast', 'multicast') DEFAULT 'primary',
    -- Network information
    network_address VARCHAR(45),
    broadcast_address VARCHAR(45),
    -- Status tracking
    is_active TINYINT(1) DEFAULT 1,
    first_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (interface_id) REFERENCES device_interfaces(id) ON DELETE CASCADE,
    FOREIGN KEY (device_id) REFERENCES ftth_items(id) ON DELETE CASCADE,
    UNIQUE KEY unique_interface_ip (interface_id, ip_address),
    INDEX idx_interface_ips_interface (interface_id),
    INDEX idx_interface_ips_device (device_id),
    INDEX idx_interface_ips_address (ip_address),
    INDEX idx_interface_ips_network (network_address),
    INDEX idx_interface_ips_active (is_active, last_seen)
);

-- Tabel untuk VLAN information (persistent)
CREATE TABLE interface_vlans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    interface_id INT NOT NULL,
    device_id INT NOT NULL,
    vlan_id INT NOT NULL,
    vlan_name VARCHAR(255),
    vlan_description TEXT,
    vlan_type ENUM('access', 'trunk', 'hybrid') DEFAULT 'access',
    native_vlan TINYINT(1) DEFAULT 0,
    -- Status tracking
    is_active TINYINT(1) DEFAULT 1,
    first_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (interface_id) REFERENCES device_interfaces(id) ON DELETE CASCADE,
    FOREIGN KEY (device_id) REFERENCES ftth_items(id) ON DELETE CASCADE,
    UNIQUE KEY unique_interface_vlan (interface_id, vlan_id),
    INDEX idx_interface_vlans_interface (interface_id),
    INDEX idx_interface_vlans_device (device_id),
    INDEX idx_interface_vlans_vlan_id (vlan_id),
    INDEX idx_interface_vlans_active (is_active, last_seen)
);

-- Tabel untuk Wireless interface information (persistent)
CREATE TABLE interface_wireless (
    id INT PRIMARY KEY AUTO_INCREMENT,
    interface_id INT NOT NULL,
    device_id INT NOT NULL,
    ssid VARCHAR(255),
    bssid VARCHAR(17),
    channel INT,
    frequency INT, -- in MHz
    signal_strength INT, -- in dBm
    noise_level INT, -- in dBm
    encryption_type VARCHAR(100),
    authentication_type VARCHAR(100),
    wireless_mode ENUM('ap', 'station', 'adhoc', 'mesh', 'monitor', 'repeater') DEFAULT 'ap',
    country_code VARCHAR(3),
    -- Status tracking
    is_active TINYINT(1) DEFAULT 1,
    first_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (interface_id) REFERENCES device_interfaces(id) ON DELETE CASCADE,
    FOREIGN KEY (device_id) REFERENCES ftth_items(id) ON DELETE CASCADE,
    UNIQUE KEY unique_interface_wireless (interface_id),
    INDEX idx_interface_wireless_interface (interface_id),
    INDEX idx_interface_wireless_device (device_id),
    INDEX idx_interface_wireless_ssid (ssid),
    INDEX idx_interface_wireless_active (is_active, last_seen)
);

-- Tabel untuk Bridge interface information (persistent)
CREATE TABLE interface_bridges (
    id INT PRIMARY KEY AUTO_INCREMENT,
    interface_id INT NOT NULL,
    device_id INT NOT NULL,
    bridge_name VARCHAR(255),
    bridge_id VARCHAR(255),
    root_bridge_id VARCHAR(255),
    bridge_priority INT,
    stp_enabled TINYINT(1) DEFAULT 0,
    -- Member ports
    member_ports JSON,
    -- Status tracking
    is_active TINYINT(1) DEFAULT 1,
    first_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (interface_id) REFERENCES device_interfaces(id) ON DELETE CASCADE,
    FOREIGN KEY (device_id) REFERENCES ftth_items(id) ON DELETE CASCADE,
    UNIQUE KEY unique_interface_bridge (interface_id),
    INDEX idx_interface_bridges_interface (interface_id),
    INDEX idx_interface_bridges_device (device_id),
    INDEX idx_interface_bridges_name (bridge_name),
    INDEX idx_interface_bridges_active (is_active, last_seen)
);

-- Tabel untuk Tunnel interface information (persistent)
CREATE TABLE interface_tunnels (
    id INT PRIMARY KEY AUTO_INCREMENT,
    interface_id INT NOT NULL,
    device_id INT NOT NULL,
    tunnel_type ENUM('gre', 'ipip', 'l2tp', 'pptp', 'ipsec', 'ovpn', 'wireguard', 'other') DEFAULT 'other',
    local_address VARCHAR(45),
    remote_address VARCHAR(45),
    tunnel_key VARCHAR(255),
    encapsulation_method VARCHAR(100),
    -- Status tracking
    is_active TINYINT(1) DEFAULT 1,
    first_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (interface_id) REFERENCES device_interfaces(id) ON DELETE CASCADE,
    FOREIGN KEY (device_id) REFERENCES ftth_items(id) ON DELETE CASCADE,
    UNIQUE KEY unique_interface_tunnel (interface_id),
    INDEX idx_interface_tunnels_interface (interface_id),
    INDEX idx_interface_tunnels_device (device_id),
    INDEX idx_interface_tunnels_type (tunnel_type),
    INDEX idx_interface_tunnels_addresses (local_address, remote_address),
    INDEX idx_interface_tunnels_active (is_active, last_seen)
);

-- Tabel untuk Network Topology Mapping (untuk membuat topology antar devices)
CREATE TABLE network_topology (
    id INT PRIMARY KEY AUTO_INCREMENT,
    source_device_id INT NOT NULL,
    source_interface_id INT NOT NULL,
    target_device_id INT NULL, -- NULL jika belum terdeteksi
    target_interface_id INT NULL, -- NULL jika belum terdeteksi
    connection_type ENUM('direct', 'switched', 'routed', 'wireless', 'tunnel', 'unknown') DEFAULT 'unknown',
    -- Discovery method
    discovery_method ENUM('snmp_bridge', 'snmp_arp', 'snmp_lldp', 'snmp_cdp', 'ip_subnet', 'manual') DEFAULT 'ip_subnet',
    confidence_level ENUM('high', 'medium', 'low') DEFAULT 'low',
    -- Network information
    shared_network VARCHAR(45), -- Network yang menghubungkan (e.g., 192.168.1.0/24)
    vlan_id INT NULL,
    -- Additional discovery data
    discovery_data JSON, -- Store additional discovery information
    -- Status tracking
    is_active TINYINT(1) DEFAULT 1,
    verified TINYINT(1) DEFAULT 0,
    first_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (source_device_id) REFERENCES ftth_items(id) ON DELETE CASCADE,
    FOREIGN KEY (source_interface_id) REFERENCES device_interfaces(id) ON DELETE CASCADE,
    FOREIGN KEY (target_device_id) REFERENCES ftth_items(id) ON DELETE SET NULL,
    FOREIGN KEY (target_interface_id) REFERENCES device_interfaces(id) ON DELETE SET NULL,
    INDEX idx_topology_source (source_device_id, source_interface_id),
    INDEX idx_topology_target (target_device_id, target_interface_id),
    INDEX idx_topology_network (shared_network),
    INDEX idx_topology_type (connection_type),
    INDEX idx_topology_method (discovery_method),
    INDEX idx_topology_active (is_active, verified, last_seen)
);

-- Tabel untuk Interface Traffic History (opsional, untuk trend analysis)
CREATE TABLE interface_traffic_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    interface_id INT NOT NULL,
    device_id INT NOT NULL,
    sample_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    -- Traffic counters (snapshot at sample_time)
    in_octets BIGINT UNSIGNED,
    out_octets BIGINT UNSIGNED,
    in_packets BIGINT UNSIGNED,
    out_packets BIGINT UNSIGNED,
    in_errors BIGINT UNSIGNED,
    out_errors BIGINT UNSIGNED,
    in_discards BIGINT UNSIGNED,
    out_discards BIGINT UNSIGNED,
    -- Calculated rates (per second)
    in_rate_bps BIGINT UNSIGNED,
    out_rate_bps BIGINT UNSIGNED,
    utilization_percent DECIMAL(5,2),
    FOREIGN KEY (interface_id) REFERENCES device_interfaces(id) ON DELETE CASCADE,
    FOREIGN KEY (device_id) REFERENCES ftth_items(id) ON DELETE CASCADE,
    INDEX idx_traffic_history_interface_time (interface_id, sample_time),
    INDEX idx_traffic_history_device_time (device_id, sample_time),
    INDEX idx_traffic_history_time (sample_time)
);

-- Create Views untuk kemudahan query

-- View untuk interface summary dengan IP addresses
CREATE VIEW interface_summary AS
SELECT 
    di.id as interface_id,
    di.device_id,
    fi.name as device_name,
    fi.ip_address as device_ip,
    di.interface_index,
    di.interface_name,
    di.interface_type,
    di.oper_status,
    di.admin_status,
    di.speed_bps,
    di.mtu,
    di.mac_address,
    GROUP_CONCAT(DISTINCT CONCAT(iia.ip_address, '/', iia.prefix_length) ORDER BY iia.ip_address SEPARATOR ', ') as ip_addresses,
    COUNT(DISTINCT iia.id) as ip_count,
    di.last_seen,
    di.is_active
FROM device_interfaces di
LEFT JOIN ftth_items fi ON di.device_id = fi.id
LEFT JOIN interface_ip_addresses iia ON di.id = iia.interface_id AND iia.is_active = 1
WHERE di.is_active = 1
GROUP BY di.id;

-- Update interface_summary view untuk kompatibilitas dengan struktur baru
DROP VIEW IF EXISTS interface_summary;
CREATE VIEW interface_summary AS
SELECT 
    di.id as interface_id,
    di.device_id,
    fi.name as device_name,
    fi.ip_address as device_ip,
    di.interface_index,
    di.interface_name,
    di.interface_type,
    di.oper_status,
    di.admin_status,
    di.speed_bps,
    di.mtu,
    di.mac_address,
    GROUP_CONCAT(DISTINCT CONCAT(iia.ip_address, '/', iia.prefix_length) ORDER BY iia.ip_address SEPARATOR ', ') as ip_addresses,
    COUNT(DISTINCT iia.id) as ip_count,
    di.last_seen,
    di.is_active
FROM device_interfaces di
LEFT JOIN ftth_items fi ON di.device_id = fi.id
LEFT JOIN interface_ip_addresses iia ON di.id = iia.interface_id AND iia.is_active = 1
WHERE di.is_active = 1
GROUP BY di.id;

-- View untuk network topology yang lebih readable
CREATE VIEW topology_view AS
SELECT 
    nt.id,
    nt.source_device_id,
    sd.name as source_device_name,
    nt.source_interface_id,
    si.interface_name as source_interface_name,
    nt.target_device_id,
    td.name as target_device_name,
    nt.target_interface_id,
    ti.interface_name as target_interface_name,
    nt.connection_type,
    nt.discovery_method,
    nt.confidence_level,
    nt.shared_network,
    nt.verified,
    nt.is_active,
    nt.last_seen
FROM network_topology nt
LEFT JOIN ftth_items sd ON nt.source_device_id = sd.id
LEFT JOIN device_interfaces si ON nt.source_interface_id = si.id
LEFT JOIN ftth_items td ON nt.target_device_id = td.id
LEFT JOIN device_interfaces ti ON nt.target_interface_id = ti.id
WHERE nt.is_active = 1;

-- ===== TRIGGERS DAN FUNCTIONS =====

-- Function untuk menghitung total cost tiang tumpu yang di-generate oleh route
DELIMITER $$
CREATE FUNCTION calculate_route_tiang_cost(route_id INT) 
RETURNS DECIMAL(12,2)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE total_cost DECIMAL(12,2) DEFAULT 0.00;
    
    SELECT COALESCE(SUM(item_price), 0.00) INTO total_cost
    FROM ftth_items
    WHERE auto_generated_by_route_id = route_id 
        AND is_auto_generated = 1 
        AND item_type_id = 2; -- Tiang Tumpu
    
    RETURN total_cost;
END$$
DELIMITER ;

-- ===== FOREIGN KEY CONSTRAINTS =====
-- Add foreign key constraints after all tables are created to avoid dependency issues

-- Add foreign key constraint for device_interfaces
ALTER TABLE device_interfaces 
ADD CONSTRAINT fk_device_interfaces_device 
FOREIGN KEY (device_id) REFERENCES ftth_items(id) ON DELETE CASCADE;

-- Add unique constraint for device_interfaces  
ALTER TABLE device_interfaces 
ADD CONSTRAINT unique_device_interface UNIQUE (device_id, interface_index);

-- Triggers untuk update cost otomatis saat ada perubahan tiang tumpu
DELIMITER $$
CREATE TRIGGER update_route_generated_cost AFTER INSERT ON ftth_items FOR EACH ROW 
BEGIN
    IF NEW.is_auto_generated = 1 AND NEW.auto_generated_by_route_id IS NOT NULL AND NEW.item_type_id = 2 THEN
        UPDATE cable_routes 
        SET total_generated_cost = calculate_route_tiang_cost(NEW.auto_generated_by_route_id)
        WHERE id = NEW.auto_generated_by_route_id;
    END IF;
END$$
DELIMITER ;

-- ===== FOREIGN KEY CONSTRAINTS =====
-- Add foreign key constraints after all tables are created to avoid dependency issues

-- Add foreign key constraint for device_interfaces
ALTER TABLE device_interfaces 
ADD CONSTRAINT fk_device_interfaces_device 
FOREIGN KEY (device_id) REFERENCES ftth_items(id) ON DELETE CASCADE;

-- Add unique constraint for device_interfaces  
ALTER TABLE device_interfaces 
ADD CONSTRAINT unique_device_interface UNIQUE (device_id, interface_index);

DELIMITER $$
CREATE TRIGGER update_route_generated_cost_delete AFTER DELETE ON ftth_items FOR EACH ROW 
BEGIN
    IF OLD.is_auto_generated = 1 AND OLD.auto_generated_by_route_id IS NOT NULL AND OLD.item_type_id = 2 THEN
        UPDATE cable_routes 
        SET total_generated_cost = calculate_route_tiang_cost(OLD.auto_generated_by_route_id)
        WHERE id = OLD.auto_generated_by_route_id;
    END IF;
END$$
DELIMITER ;

-- ===== FOREIGN KEY CONSTRAINTS =====
-- Add foreign key constraints after all tables are created to avoid dependency issues

-- Add foreign key constraint for device_interfaces
ALTER TABLE device_interfaces 
ADD CONSTRAINT fk_device_interfaces_device 
FOREIGN KEY (device_id) REFERENCES ftth_items(id) ON DELETE CASCADE;

-- Add unique constraint for device_interfaces  
ALTER TABLE device_interfaces 
ADD CONSTRAINT unique_device_interface UNIQUE (device_id, interface_index);

DELIMITER $$
CREATE TRIGGER update_route_generated_cost_update AFTER UPDATE ON ftth_items FOR EACH ROW 
BEGIN
    IF NEW.is_auto_generated = 1 AND NEW.auto_generated_by_route_id IS NOT NULL AND NEW.item_type_id = 2 THEN
        UPDATE cable_routes 
        SET total_generated_cost = calculate_route_tiang_cost(NEW.auto_generated_by_route_id)
        WHERE id = NEW.auto_generated_by_route_id;
    END IF;
END$$
DELIMITER ;

-- ===== FOREIGN KEY CONSTRAINTS =====
-- Add foreign key constraints after all tables are created to avoid dependency issues

-- Add foreign key constraint for device_interfaces
ALTER TABLE device_interfaces 
ADD CONSTRAINT fk_device_interfaces_device 
FOREIGN KEY (device_id) REFERENCES ftth_items(id) ON DELETE CASCADE;

-- Add unique constraint for device_interfaces  
ALTER TABLE device_interfaces 
ADD CONSTRAINT unique_device_interface UNIQUE (device_id, interface_index);

-- Triggers untuk update ODC ports used
DELIMITER $$
CREATE TRIGGER update_odc_ports_used AFTER INSERT ON odp_odc_mapping FOR EACH ROW 
BEGIN
    UPDATE ftth_items 
    SET odc_ports_used = (
        SELECT COUNT(*) 
        FROM odp_odc_mapping 
        WHERE odc_item_id = NEW.odc_item_id AND status = 'active'
    )
    WHERE id = NEW.odc_item_id;
END$$
DELIMITER ;

-- ===== FOREIGN KEY CONSTRAINTS =====
-- Add foreign key constraints after all tables are created to avoid dependency issues

-- Add foreign key constraint for device_interfaces
ALTER TABLE device_interfaces 
ADD CONSTRAINT fk_device_interfaces_device 
FOREIGN KEY (device_id) REFERENCES ftth_items(id) ON DELETE CASCADE;

-- Add unique constraint for device_interfaces  
ALTER TABLE device_interfaces 
ADD CONSTRAINT unique_device_interface UNIQUE (device_id, interface_index);

DELIMITER $$
CREATE TRIGGER update_odc_ports_used_delete AFTER DELETE ON odp_odc_mapping FOR EACH ROW 
BEGIN
    UPDATE ftth_items 
    SET odc_ports_used = (
        SELECT COUNT(*) 
        FROM odp_odc_mapping 
        WHERE odc_item_id = OLD.odc_item_id AND status = 'active'
    )
    WHERE id = OLD.odc_item_id;
END$$
DELIMITER ;

-- ===== FOREIGN KEY CONSTRAINTS =====
-- Add foreign key constraints after all tables are created to avoid dependency issues

-- Add foreign key constraint for device_interfaces
ALTER TABLE device_interfaces 
ADD CONSTRAINT fk_device_interfaces_device 
FOREIGN KEY (device_id) REFERENCES ftth_items(id) ON DELETE CASCADE;

-- Add unique constraint for device_interfaces  
ALTER TABLE device_interfaces 
ADD CONSTRAINT unique_device_interface UNIQUE (device_id, interface_index);

-- Triggers untuk update ODP ports used
DELIMITER $$
CREATE TRIGGER update_odp_ports_used AFTER INSERT ON odp_ports FOR EACH ROW 
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
DELIMITER ;

-- ===== FOREIGN KEY CONSTRAINTS =====
-- Add foreign key constraints after all tables are created to avoid dependency issues

-- Add foreign key constraint for device_interfaces
ALTER TABLE device_interfaces 
ADD CONSTRAINT fk_device_interfaces_device 
FOREIGN KEY (device_id) REFERENCES ftth_items(id) ON DELETE CASCADE;

-- Add unique constraint for device_interfaces  
ALTER TABLE device_interfaces 
ADD CONSTRAINT unique_device_interface UNIQUE (device_id, interface_index);

DELIMITER $$
CREATE TRIGGER update_odp_ports_used_update AFTER UPDATE ON odp_ports FOR EACH ROW 
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

-- ===== FOREIGN KEY CONSTRAINTS =====
-- Add foreign key constraints after all tables are created to avoid dependency issues

-- Add foreign key constraint for device_interfaces
ALTER TABLE device_interfaces 
ADD CONSTRAINT fk_device_interfaces_device 
FOREIGN KEY (device_id) REFERENCES ftth_items(id) ON DELETE CASCADE;

-- Add unique constraint for device_interfaces  
ALTER TABLE device_interfaces 
ADD CONSTRAINT unique_device_interface UNIQUE (device_id, interface_index);