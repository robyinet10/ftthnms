-- =====================================================
-- CLEAN AND INTEGRATE REAL PON DATA (FIXED VERSION)
-- FTTH Network Monitoring System
-- =====================================================
-- File: clean_and_integrate_real_pon_data_fixed.sql
-- Description: Hapus data simulasi dan integrasikan dengan PON & VLAN OLT yang sebenarnya
-- Date: 2025-01-17
-- Fixed: Compatible with MySQL/MariaDB without JSON_ARRAYAGG
-- =====================================================

-- Set foreign key checks off untuk menghindari constraint issues
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- 1. CLEAN SIMULATION DATA
-- =====================================================

-- Clear all simulation data from tables
DELETE FROM odc_pon_mapping;
DELETE FROM pon_vlans;
DELETE FROM olt_pons;

-- Clear pon_config from OLT devices (will be rebuilt with real data)
UPDATE ftth_items SET pon_config = NULL WHERE item_type_id = 1;

-- Clear ODC connection data
UPDATE ftth_items SET 
    odc_pon_connection = NULL,
    odc_vlan_id = NULL
WHERE item_type_id IN (4, 12);

-- =====================================================
-- 2. GET REAL OLT DEVICES
-- =====================================================

-- Get OLT devices that actually exist in the system
SET @olt_central_id = (SELECT id FROM ftth_items WHERE name = 'OLT Central' AND item_type_id = 1 LIMIT 1);
SET @olt_north_id = (SELECT id FROM ftth_items WHERE name = 'OLT North' AND item_type_id = 1 LIMIT 1);

-- Verify OLT devices exist
SELECT 
    'OLT Devices Found' as status,
    COUNT(*) as count,
    GROUP_CONCAT(name) as olt_names
FROM ftth_items 
WHERE item_type_id = 1;

-- =====================================================
-- 3. INSERT REAL PON PORTS BASED ON ACTUAL OLT CONFIG
-- =====================================================

-- Insert PON ports for OLT Central (if exists)
INSERT INTO olt_pons (item_id, pon_port, description, status) 
SELECT 
    @olt_central_id,
    'PON1/1/1',
    'PON Port 1 untuk Area Central',
    'active'
WHERE @olt_central_id IS NOT NULL;

INSERT INTO olt_pons (item_id, pon_port, description, status) 
SELECT 
    @olt_central_id,
    'PON2/2/2',
    'PON Port 2 untuk Area Central',
    'active'
WHERE @olt_central_id IS NOT NULL;

INSERT INTO olt_pons (item_id, pon_port, description, status) 
SELECT 
    @olt_central_id,
    'PON3/3/3',
    'PON Port 3 untuk Area Central',
    'active'
WHERE @olt_central_id IS NOT NULL;

-- Insert PON ports for OLT North (if exists)
INSERT INTO olt_pons (item_id, pon_port, description, status) 
SELECT 
    @olt_north_id,
    'PON1/1/1',
    'PON Port 1 untuk Area North',
    'active'
WHERE @olt_north_id IS NOT NULL;

INSERT INTO olt_pons (item_id, pon_port, description, status) 
SELECT 
    @olt_north_id,
    'PON2/2/2',
    'PON Port 2 untuk Area North',
    'active'
WHERE @olt_north_id IS NOT NULL;

INSERT INTO olt_pons (item_id, pon_port, description, status) 
SELECT 
    @olt_north_id,
    'PON3/3/3',
    'PON Port 3 untuk Area North',
    'active'
WHERE @olt_north_id IS NOT NULL;

-- =====================================================
-- 4. INSERT REAL VLAN CONFIGURATION
-- =====================================================

-- Get PON IDs for OLT Central
SET @pon1_central_id = (SELECT id FROM olt_pons WHERE pon_port = 'PON1/1/1' AND item_id = @olt_central_id LIMIT 1);
SET @pon2_central_id = (SELECT id FROM olt_pons WHERE pon_port = 'PON2/2/2' AND item_id = @olt_central_id LIMIT 1);
SET @pon3_central_id = (SELECT id FROM olt_pons WHERE pon_port = 'PON3/3/3' AND item_id = @olt_central_id LIMIT 1);

-- Get PON IDs for OLT North
SET @pon1_north_id = (SELECT id FROM olt_pons WHERE pon_port = 'PON1/1/1' AND item_id = @olt_north_id LIMIT 1);
SET @pon2_north_id = (SELECT id FROM olt_pons WHERE pon_port = 'PON2/2/2' AND item_id = @olt_north_id LIMIT 1);
SET @pon3_north_id = (SELECT id FROM olt_pons WHERE pon_port = 'PON3/3/3' AND item_id = @olt_north_id LIMIT 1);

-- Insert VLANs for OLT Central PON ports
INSERT INTO pon_vlans (pon_id, vlan_id, description) 
SELECT @pon1_central_id, '100', 'VLAN 100 (IP: 172.0.100.0/24)'
WHERE @pon1_central_id IS NOT NULL;

INSERT INTO pon_vlans (pon_id, vlan_id, description) 
SELECT @pon2_central_id, '200', 'VLAN 200 (IP: 172.0.200.0/24)'
WHERE @pon2_central_id IS NOT NULL;

INSERT INTO pon_vlans (pon_id, vlan_id, description) 
SELECT @pon3_central_id, '300', 'VLAN 300 (IP: 172.0.300.0/24)'
WHERE @pon3_central_id IS NOT NULL;

-- Insert VLANs for OLT North PON ports
INSERT INTO pon_vlans (pon_id, vlan_id, description) 
SELECT @pon1_north_id, '100', 'VLAN 100 (IP: 172.0.100.0/24)'
WHERE @pon1_north_id IS NOT NULL;

INSERT INTO pon_vlans (pon_id, vlan_id, description) 
SELECT @pon2_north_id, '200', 'VLAN 200 (IP: 172.0.200.0/24)'
WHERE @pon2_north_id IS NOT NULL;

INSERT INTO pon_vlans (pon_id, vlan_id, description) 
SELECT @pon3_north_id, '300', 'VLAN 300 (IP: 172.0.300.0/24)'
WHERE @pon3_north_id IS NOT NULL;

-- =====================================================
-- 5. REBUILD PON_CONFIG FROM REAL DATA (FIXED VERSION)
-- =====================================================

-- Update OLT Central pon_config from real data (manual JSON construction)
UPDATE ftth_items SET 
    pon_config = JSON_OBJECT(
        'pons', JSON_ARRAY(
            JSON_OBJECT(
                'port', 'PON1/1/1',
                'description', 'PON Port 1 untuk Area Central',
                'vlans', JSON_ARRAY(
                    JSON_OBJECT(
                        'vlan_id', '100',
                        'description', 'VLAN 100 (IP: 172.0.100.0/24) [MikroTik]'
                    )
                )
            ),
            JSON_OBJECT(
                'port', 'PON2/2/2',
                'description', 'PON Port 2 untuk Area Central',
                'vlans', JSON_ARRAY(
                    JSON_OBJECT(
                        'vlan_id', '200',
                        'description', 'VLAN 200 (IP: 172.0.200.0/24) [MikroTik]'
                    )
                )
            ),
            JSON_OBJECT(
                'port', 'PON3/3/3',
                'description', 'PON Port 3 untuk Area Central',
                'vlans', JSON_ARRAY(
                    JSON_OBJECT(
                        'vlan_id', '300',
                        'description', 'VLAN 300 (IP: 172.0.300.0/24) [MikroTik]'
                    )
                )
            )
        )
    )
WHERE id = @olt_central_id AND @olt_central_id IS NOT NULL;

-- Update OLT North pon_config from real data (manual JSON construction)
UPDATE ftth_items SET 
    pon_config = JSON_OBJECT(
        'pons', JSON_ARRAY(
            JSON_OBJECT(
                'port', 'PON1/1/1',
                'description', 'PON Port 1 untuk Area North',
                'vlans', JSON_ARRAY(
                    JSON_OBJECT(
                        'vlan_id', '100',
                        'description', 'VLAN 100 (IP: 172.0.100.0/24) [MikroTik]'
                    )
                )
            ),
            JSON_OBJECT(
                'port', 'PON2/2/2',
                'description', 'PON Port 2 untuk Area North',
                'vlans', JSON_ARRAY(
                    JSON_OBJECT(
                        'vlan_id', '200',
                        'description', 'VLAN 200 (IP: 172.0.200.0/24) [MikroTik]'
                    )
                )
            ),
            JSON_OBJECT(
                'port', 'PON3/3/3',
                'description', 'PON Port 3 untuk Area North',
                'vlans', JSON_ARRAY(
                    JSON_OBJECT(
                        'vlan_id', '300',
                        'description', 'VLAN 300 (IP: 172.0.300.0/24) [MikroTik]'
                    )
                )
            )
        )
    )
WHERE id = @olt_north_id AND @olt_north_id IS NOT NULL;

-- =====================================================
-- 6. CONNECT REAL ODC DEVICES TO PON PORTS
-- =====================================================

-- Get real ODC devices
SET @odc_pole_id = (SELECT id FROM ftth_items WHERE name = 'ODC-Pole-001' AND item_type_id = 4 LIMIT 1);
SET @odc_cabinet_id = (SELECT id FROM ftth_items WHERE name = 'ODC-Cabinet-001' AND item_type_id = 12 LIMIT 1);

-- Connect ODC Pole to OLT Central PON1/1/1 (if both exist)
INSERT INTO odc_pon_mapping (odc_item_id, olt_item_id, pon_port, vlan_id, description, status) 
SELECT 
    @odc_pole_id,
    @olt_central_id,
    'PON1/1/1',
    '100',
    'ODC Pole Mounted terhubung ke OLT Central PON1/1/1',
    'active'
WHERE @odc_pole_id IS NOT NULL AND @olt_central_id IS NOT NULL;

-- Connect ODC Cabinet to OLT North PON2/2/2 (if both exist)
INSERT INTO odc_pon_mapping (odc_item_id, olt_item_id, pon_port, vlan_id, description, status) 
SELECT 
    @odc_cabinet_id,
    @olt_north_id,
    'PON2/2/2',
    '200',
    'ODC Cabinet terhubung ke OLT North PON2/2/2',
    'active'
WHERE @odc_cabinet_id IS NOT NULL AND @olt_north_id IS NOT NULL;

-- Update ODC items with real PON connection info
UPDATE ftth_items SET 
    odc_pon_connection = 'PON1/1/1',
    odc_vlan_id = '100'
WHERE id = @odc_pole_id AND @odc_pole_id IS NOT NULL;

UPDATE ftth_items SET 
    odc_pon_connection = 'PON2/2/2',
    odc_vlan_id = '200'
WHERE id = @odc_cabinet_id AND @odc_cabinet_id IS NOT NULL;

-- =====================================================
-- 7. VERIFICATION - Check real integrated data
-- =====================================================

-- Show OLT devices with their real PON config
SELECT 
    id,
    name,
    ip_address,
    JSON_EXTRACT(pon_config, '$.pons') as pon_ports,
    JSON_LENGTH(JSON_EXTRACT(pon_config, '$.pons')) as total_pon_ports
FROM ftth_items 
WHERE item_type_id = 1 
AND pon_config IS NOT NULL;

-- Show real PON ports in olt_pons table
SELECT 
    olt.name as olt_name,
    op.pon_port,
    op.description,
    COUNT(pv.vlan_id) as vlan_count
FROM olt_pons op
JOIN ftth_items olt ON op.item_id = olt.id
LEFT JOIN pon_vlans pv ON op.id = pv.pon_id
GROUP BY olt.name, op.pon_port, op.description
ORDER BY olt.name, op.pon_port;

-- Show real VLANs in pon_vlans table
SELECT 
    olt.name as olt_name,
    op.pon_port,
    pv.vlan_id,
    pv.description
FROM pon_vlans pv
JOIN olt_pons op ON pv.pon_id = op.id
JOIN ftth_items olt ON op.item_id = olt.id
ORDER BY olt.name, op.pon_port, pv.vlan_id;

-- Show real ODC-PON mappings
SELECT 
    odc.name as odc_name,
    olt.name as olt_name,
    opm.pon_port,
    opm.vlan_id,
    opm.description
FROM odc_pon_mapping opm
JOIN ftth_items odc ON opm.odc_item_id = odc.id
JOIN ftth_items olt ON opm.olt_item_id = olt.id
ORDER BY odc.name;

-- Show ODC devices with real PON connection
SELECT 
    name as odc_name,
    odc_type,
    odc_pon_connection,
    odc_vlan_id
FROM ftth_items 
WHERE item_type_id IN (4, 12)
AND odc_pon_connection IS NOT NULL;

-- =====================================================
-- 8. FINAL VERIFICATION
-- =====================================================

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Verify data integrity
SELECT 'Real PON-ODC Integration Complete (Fixed Version)' as status;

-- Show summary of real changes
SELECT 
    'Real OLT Devices' as table_name,
    COUNT(*) as record_count
FROM ftth_items 
WHERE item_type_id = 1
AND pon_config IS NOT NULL
UNION ALL
SELECT 
    'Real PON Ports' as table_name,
    COUNT(*) as record_count
FROM olt_pons
UNION ALL
SELECT 
    'Real VLANs' as table_name,
    COUNT(*) as record_count
FROM pon_vlans
UNION ALL
SELECT 
    'Real ODC-PON Mappings' as table_name,
    COUNT(*) as record_count
FROM odc_pon_mapping
UNION ALL
SELECT 
    'Real ODC Devices with PON' as table_name,
    COUNT(*) as record_count
FROM ftth_items 
WHERE item_type_id IN (4, 12)
AND odc_pon_connection IS NOT NULL;

-- =====================================================
-- END OF CLEAN AND INTEGRATE SCRIPT (FIXED VERSION)
-- =====================================================
