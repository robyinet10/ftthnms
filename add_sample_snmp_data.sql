-- Add Sample SNMP Data untuk Testing Dashboard
-- Tambahkan sample devices dengan SNMP enabled dan metrics

USE ftthnms;

-- 1. Insert sample devices dengan SNMP configuration
INSERT INTO ftth_items (
    name, 
    item_type_id, 
    description, 
    address, 
    latitude, 
    longitude, 
    ip_address,
    port_http,
    port_https,
    snmp_enabled,
    snmp_version,
    snmp_community,
    snmp_port,
    status,
    created_at
) VALUES 
-- Sample Server
(
    'Core Server Jakarta', 
    7, -- Server/Router type ID
    'Main server untuk monitoring jaringan Jakarta', 
    'Jl. Sudirman No. 123, Jakarta Pusat',
    -6.2088, 
    106.8456, 
    '192.168.1.100',
    80,
    443,
    1, -- SNMP enabled
    '2c',
    'public',
    161,
    'active',
    NOW()
),
-- Sample OLT
(
    'OLT Huawei MA5800-X2', 
    1, -- OLT type ID
    'OLT utama untuk coverage area Jakarta Selatan', 
    'Jl. Gatot Subroto No. 456, Jakarta Selatan',
    -6.2297, 
    106.8253, 
    '192.168.1.200',
    80,
    443,
    1, -- SNMP enabled
    '2c',
    'public',
    161,
    'active',
    NOW()
),
-- Sample Access Point
(
    'AP MikroTik Campus A', 
    9, -- Access Point type ID  
    'Access Point untuk coverage WiFi area kampus A', 
    'Jl. Thamrin No. 789, Jakarta Pusat',
    -6.1944, 
    106.8229, 
    '192.168.1.50',
    80,
    443,
    1, -- SNMP enabled
    '2c',
    'public',
    161,
    'active',
    NOW()
),
-- Sample ONT
(
    'ONT Customer Premium-001', 
    6, -- ONT type ID
    'ONT pelanggan premium di area residential elite', 
    'Jl. Kemang Raya No. 100, Jakarta Selatan',
    -6.2615, 
    106.8106, 
    '192.168.1.150',
    80,
    443,
    1, -- SNMP enabled
    '2c',
    'public',
    161,
    'active',
    NOW()
),
-- Additional Server
(
    'Backup Server Bandung', 
    7, -- Server/Router type ID
    'Backup server untuk disaster recovery', 
    'Jl. Asia Afrika No. 200, Bandung',
    -6.9175, 
    107.6191, 
    '192.168.2.100',
    80,
    443,
    1, -- SNMP enabled
    '2c',
    'monitoring',
    161,
    'active',
    NOW()
),
-- Additional Access Point
(
    'AP Ubiquiti Office B', 
    9, -- Access Point type ID
    'Access Point Ubiquiti untuk office coverage', 
    'Jl. HR Rasuna Said No. 300, Jakarta Selatan',
    -6.2219, 
    106.8439, 
    '192.168.1.75',
    80,
    443,
    1, -- SNMP enabled
    '2c',
    'public',
    161,
    'active',
    NOW()
);

-- 2. Insert sample SNMP metrics data untuk devices yang baru dibuat
-- Get the IDs dari devices yang baru diinsert
SET @server_id = (SELECT id FROM ftth_items WHERE name = 'Core Server Jakarta' LIMIT 1);
SET @olt_id = (SELECT id FROM ftth_items WHERE name = 'OLT Huawei MA5800-X2' LIMIT 1);
SET @ap_id = (SELECT id FROM ftth_items WHERE name = 'AP MikroTik Campus A' LIMIT 1);
SET @ont_id = (SELECT id FROM ftth_items WHERE name = 'ONT Customer Premium-001' LIMIT 1);
SET @backup_server_id = (SELECT id FROM ftth_items WHERE name = 'Backup Server Bandung' LIMIT 1);
SET @ap2_id = (SELECT id FROM ftth_items WHERE name = 'AP Ubiquiti Office B' LIMIT 1);

-- Insert metrics untuk Core Server Jakarta
INSERT INTO snmp_metrics (
    item_id,
    metric_time,
    device_name,
    device_description,
    device_contact,
    device_location,
    device_uptime,
    cpu_usage_percent,
    memory_total_mb,
    memory_used_mb,
    memory_usage_percent,
    interface_name,
    interface_status,
    interface_speed_mbps,
    bytes_in_total,
    bytes_out_total,
    packets_in_total,
    packets_out_total
) VALUES (
    @server_id,
    NOW(),
    'srv-jakarta-core-01',
    'Ubuntu Server 22.04 LTS - Core Network Server',
    'admin@company.com',
    'Jakarta Data Center Rack A-15',
    15768432, -- ~43 hours uptime
    15.5, -- CPU usage
    16384, -- 16GB total memory
    6144, -- 6GB used memory  
    37.5, -- Memory usage percentage
    'eth0',
    'up',
    1000, -- 1Gbps
    15847392847, -- Bytes in
    8473829374, -- Bytes out
    12847362, -- Packets in
    8473829 -- Packets out
);

-- Insert metrics untuk OLT Huawei MA5800-X2
INSERT INTO snmp_metrics (
    item_id,
    metric_time,
    device_name,
    device_description,
    device_contact,
    device_location,
    device_uptime,
    cpu_usage_percent,
    memory_total_mb,
    memory_used_mb,
    memory_usage_percent,
    interface_name,
    interface_status,
    interface_speed_mbps,
    optical_power_tx_dbm,
    optical_power_rx_dbm,
    temperature_celsius
) VALUES (
    @olt_id,
    NOW(),
    'OLT-JKT-01-MA5800',
    'Huawei MA5800-X2 Optical Line Terminal',
    'noc@company.com',
    'Jakarta POP Sudirman Floor 15',
    25632847, -- ~7 days uptime
    8.2, -- CPU usage
    4096, -- 4GB total memory
    1024, -- 1GB used memory
    25.0, -- Memory usage percentage
    'GigabitEthernet0/1/0',
    'up',
    10000, -- 10Gbps
    2.5, -- TX power in dBm
    -15.3, -- RX power in dBm
    42.8 -- Temperature in Celsius
);

-- Insert metrics untuk AP MikroTik Campus A
INSERT INTO snmp_metrics (
    item_id,
    metric_time,
    device_name,
    device_description,
    device_contact,
    device_location,
    device_uptime,
    cpu_usage_percent,
    memory_total_mb,
    memory_used_mb,
    memory_usage_percent,
    interface_name,
    interface_status,
    interface_speed_mbps,
    temperature_celsius
) VALUES (
    @ap_id,
    NOW(),
    'AP-Campus-A-01',
    'MikroTik hAP ac² Wireless Access Point',
    'wifi-admin@company.com',
    'Campus A Building 1 Floor 3',
    8847362, -- ~24 hours uptime
    12.8, -- CPU usage
    256, -- 256MB total memory
    128, -- 128MB used memory
    50.0, -- Memory usage percentage
    'wlan1',
    'up',
    866, -- 866Mbps 802.11ac
    38.5 -- Temperature in Celsius
);

-- Insert metrics untuk ONT Customer Premium-001
INSERT INTO snmp_metrics (
    item_id,
    metric_time,
    device_name,
    device_description,
    device_contact,
    device_location,
    device_uptime,
    cpu_usage_percent,
    memory_total_mb,
    memory_used_mb,
    memory_usage_percent,
    interface_name,
    interface_status,
    interface_speed_mbps,
    optical_power_tx_dbm,
    optical_power_rx_dbm
) VALUES (
    @ont_id,
    NOW(),
    'ONT-PREM-001',
    'Huawei HG8245H5 Optical Network Terminal',
    'customer@premium.com',
    'Kemang Residence Premium Tower A Unit 1505',
    5847362, -- ~16 hours uptime
    5.2, -- CPU usage
    128, -- 128MB total memory
    64, -- 64MB used memory
    50.0, -- Memory usage percentage
    'eth0',
    'up',
    1000, -- 1Gbps
    1.8, -- TX power in dBm
    -18.7 -- RX power in dBm
);

-- Insert metrics untuk Backup Server Bandung
INSERT INTO snmp_metrics (
    item_id,
    metric_time,
    device_name,
    device_description,
    device_contact,
    device_location,
    device_uptime,
    cpu_usage_percent,
    memory_total_mb,
    memory_used_mb,
    memory_usage_percent,
    interface_name,
    interface_status,
    interface_speed_mbps
) VALUES (
    @backup_server_id,
    NOW(),
    'srv-bandung-backup-01',
    'CentOS 8 - Backup & Disaster Recovery Server',
    'backup-admin@company.com',
    'Bandung Data Center Rack B-08',
    45847362, -- ~12 days uptime
    3.8, -- CPU usage - low karena backup server
    8192, -- 8GB total memory
    2048, -- 2GB used memory
    25.0, -- Memory usage percentage
    'ens192',
    'up',
    1000 -- 1Gbps
);

-- Insert metrics untuk AP Ubiquiti Office B
INSERT INTO snmp_metrics (
    item_id,
    metric_time,
    device_name,
    device_description,
    device_contact,
    device_location,
    device_uptime,
    cpu_usage_percent,
    memory_total_mb,
    memory_used_mb,
    memory_usage_percent,
    interface_name,
    interface_status,
    interface_speed_mbps,
    temperature_celsius
) VALUES (
    @ap2_id,
    NOW(),
    'AP-Office-B-01',
    'Ubiquiti UniFi U6-Pro Access Point',
    'it-support@company.com',
    'Office Building B Floor 8 Meeting Room',
    12847362, -- ~3.5 days uptime
    18.5, -- CPU usage - higher karena banyak client
    512, -- 512MB total memory
    256, -- 256MB used memory
    50.0, -- Memory usage percentage
    'ra0',
    'up',
    1201, -- 1.2Gbps WiFi 6
    35.2 -- Temperature in Celsius
);

-- 3. Insert beberapa historical metrics (1 jam yang lalu) untuk trending
INSERT INTO snmp_metrics (
    item_id,
    metric_time,
    device_name,
    device_description,
    device_uptime,
    cpu_usage_percent,
    memory_usage_percent,
    interface_status
) VALUES 
-- Server metrics 1 hour ago
(
    @server_id,
    DATE_SUB(NOW(), INTERVAL 1 HOUR),
    'srv-jakarta-core-01',
    'Ubuntu Server 22.04 LTS - Core Network Server',
    15404632, -- 1 hour ago uptime
    18.2, -- CPU was higher
    35.8, -- Memory was lower
    'up'
),
-- OLT metrics 1 hour ago
(
    @olt_id,
    DATE_SUB(NOW(), INTERVAL 1 HOUR),
    'OLT-JKT-01-MA5800',
    'Huawei MA5800-X2 Optical Line Terminal',
    25269047, -- 1 hour ago uptime
    12.5, -- CPU was higher
    22.3, -- Memory was lower
    'up'
);

-- 4. Update monitoring status untuk devices yang baru
UPDATE ftth_items SET 
    monitoring_status = 'online',
    last_ping_time = NOW(),
    response_time_ms = FLOOR(1 + RAND() * 50) -- Random 1-50ms response time
WHERE snmp_enabled = 1;

-- 5. Insert monitoring logs
INSERT INTO monitoring_logs (
    item_id,
    ping_time,
    status,
    response_time_ms,
    monitoring_type,
    snmp_status
)
SELECT 
    id,
    NOW(),
    'online',
    FLOOR(1 + RAND() * 50),
    'both',
    'success'
FROM ftth_items 
WHERE snmp_enabled = 1;

COMMIT;

-- Display summary
SELECT 'Sample SNMP data added successfully!' as Status,
       CONCAT(
           (SELECT COUNT(*) FROM ftth_items WHERE snmp_enabled = 1), 
           ' SNMP-enabled devices created'
       ) as Info,
       CONCAT(
           (SELECT COUNT(*) FROM snmp_metrics), 
           ' SNMP metrics records inserted'
       ) as MetricsInfo;
