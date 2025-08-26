-- =====================================================
-- INTEGRATE PON-ODC CONNECTION
-- FTTH Network Monitoring System
-- =====================================================
-- File: integrate_pon_odc_connection.sql
-- Description: Integrasikan data konfigurasi PON dari OLT dengan dropdown PON Connection di ODC
-- Date: 2025-01-17
-- =====================================================

-- Set foreign key checks off untuk menghindari constraint issues
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- 1. UPDATE PON_CONFIG FIELD FOR OLT DEVICES (3 PORTS)
-- =====================================================

-- Update OLT Central pon_config dengan 3 PON ports sesuai gambar
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
WHERE name = 'OLT Central' AND item_type_id = 1;

-- Update OLT North pon_config dengan 3 PON ports
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
WHERE name = 'OLT North' AND item_type_id = 1;

-- =====================================================
-- 2. UPDATE OLT_PONS TABLE (3 PORTS PER OLT)
-- =====================================================

-- Clear existing PON ports
DELETE FROM olt_pons;

-- Get OLT IDs
SET @olt_central_id = (SELECT id FROM ftth_items WHERE name = 'OLT Central' AND item_type_id = 1 LIMIT 1);
SET @olt_north_id = (SELECT id FROM ftth_items WHERE name = 'OLT North' AND item_type_id = 1 LIMIT 1);

-- Insert OLT Central PON ports (3 ports)
INSERT INTO olt_pons (item_id, pon_port, description, status) VALUES
(@olt_central_id, 'PON1/1/1', 'PON Port 1 untuk Area Central', 'active'),
(@olt_central_id, 'PON2/2/2', 'PON Port 2 untuk Area Central', 'active'),
(@olt_central_id, 'PON3/3/3', 'PON Port 3 untuk Area Central', 'active');

-- Insert OLT North PON ports (3 ports)
INSERT INTO olt_pons (item_id, pon_port, description, status) VALUES
(@olt_north_id, 'PON1/1/1', 'PON Port 1 untuk Area North', 'active'),
(@olt_north_id, 'PON2/2/2', 'PON Port 2 untuk Area North', 'active'),
(@olt_north_id, 'PON3/3/3', 'PON Port 3 untuk Area North', 'active');

-- =====================================================
-- 3. UPDATE PON_VLANS TABLE (1 VLAN PER PON)
-- =====================================================

-- Clear existing VLANs
DELETE FROM pon_vlans;

-- Get PON IDs for OLT Central
SET @pon1_central_id = (SELECT id FROM olt_pons WHERE pon_port = 'PON1/1/1' AND item_id = @olt_central_id LIMIT 1);
SET @pon2_central_id = (SELECT id FROM olt_pons WHERE pon_port = 'PON2/2/2' AND item_id = @olt_central_id LIMIT 1);
SET @pon3_central_id = (SELECT id FROM olt_pons WHERE pon_port = 'PON3/3/3' AND item_id = @olt_central_id LIMIT 1);

-- Get PON IDs for OLT North
SET @pon1_north_id = (SELECT id FROM olt_pons WHERE pon_port = 'PON1/1/1' AND item_id = @olt_north_id LIMIT 1);
SET @pon2_north_id = (SELECT id FROM olt_pons WHERE pon_port = 'PON2/2/2' AND item_id = @olt_north_id LIMIT 1);
SET @pon3_north_id = (SELECT id FROM olt_pons WHERE pon_port = 'PON3/3/3' AND item_id = @olt_north_id LIMIT 1);

-- Insert VLANs for OLT Central
INSERT INTO pon_vlans (pon_id, vlan_id, description) VALUES
(@pon1_central_id, '100', 'VLAN 100 (IP: 172.0.100.0/24)'),
(@pon2_central_id, '200', 'VLAN 200 (IP: 172.0.200.0/24)'),
(@pon3_central_id, '300', 'VLAN 300 (IP: 172.0.300.0/24)');

-- Insert VLANs for OLT North
INSERT INTO pon_vlans (pon_id, vlan_id, description) VALUES
(@pon1_north_id, '100', 'VLAN 100 (IP: 172.0.100.0/24)'),
(@pon2_north_id, '200', 'VLAN 200 (IP: 172.0.200.0/24)'),
(@pon3_north_id, '300', 'VLAN 300 (IP: 172.0.300.0/24)');

-- =====================================================
-- 4. UPDATE ODC-PON MAPPING (CONNECT ODCs TO PONS)
-- =====================================================

-- Clear existing ODC-PON mappings
DELETE FROM odc_pon_mapping;

-- Get ODC IDs
SET @odc_pole_id = (SELECT id FROM ftth_items WHERE name = 'ODC-Pole-001' AND item_type_id = 4 LIMIT 1);
SET @odc_cabinet_id = (SELECT id FROM ftth_items WHERE name = 'ODC-Cabinet-001' AND item_type_id = 12 LIMIT 1);

-- Connect ODC Pole to OLT Central PON1/1/1
INSERT INTO odc_pon_mapping (odc_item_id, olt_item_id, pon_port, vlan_id, description, status) VALUES
(@odc_pole_id, @olt_central_id, 'PON1/1/1', '100', 'ODC Pole Mounted terhubung ke OLT Central PON1/1/1', 'active');

-- Connect ODC Cabinet to OLT North PON2/2/2
INSERT INTO odc_pon_mapping (odc_item_id, olt_item_id, pon_port, vlan_id, description, status) VALUES
(@odc_cabinet_id, @olt_north_id, 'PON2/2/2', '200', 'ODC Cabinet terhubung ke OLT North PON2/2/2', 'active');

-- Update ODC items with PON connection info
UPDATE ftth_items SET 
    odc_pon_connection = 'PON1/1/1',
    odc_vlan_id = '100'
WHERE id = @odc_pole_id;

UPDATE ftth_items SET 
    odc_pon_connection = 'PON2/2/2',
    odc_vlan_id = '200'
WHERE id = @odc_cabinet_id;

-- =====================================================
-- 5. VERIFICATION - Check integrated data
-- =====================================================

-- Show OLT devices with their PON config
SELECT 
    id,
    name,
    ip_address,
    JSON_EXTRACT(pon_config, '$.pons') as pon_ports,
    JSON_LENGTH(JSON_EXTRACT(pon_config, '$.pons')) as total_pon_ports
FROM ftth_items 
WHERE item_type_id = 1 
AND pon_config IS NOT NULL;

-- Show PON ports in olt_pons table
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

-- Show VLANs in pon_vlans table
SELECT 
    olt.name as olt_name,
    op.pon_port,
    pv.vlan_id,
    pv.description
FROM pon_vlans pv
JOIN olt_pons op ON pv.pon_id = op.id
JOIN ftth_items olt ON op.item_id = olt.id
ORDER BY olt.name, op.pon_port, pv.vlan_id;

-- Show ODC-PON mappings
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

-- Show ODC devices with PON connection
SELECT 
    name as odc_name,
    odc_type,
    odc_pon_connection,
    odc_vlan_id
FROM ftth_items 
WHERE item_type_id IN (4, 12)
AND odc_pon_connection IS NOT NULL;

-- =====================================================
-- 6. FINAL VERIFICATION
-- =====================================================

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Verify data integrity
SELECT 'PON-ODC Integration Complete' as status;

-- Show summary of changes
SELECT 
    'OLT Devices' as table_name,
    COUNT(*) as record_count
FROM ftth_items 
WHERE item_type_id = 1
UNION ALL
SELECT 
    'PON Ports' as table_name,
    COUNT(*) as record_count
FROM olt_pons
UNION ALL
SELECT 
    'VLANs' as table_name,
    COUNT(*) as record_count
FROM pon_vlans
UNION ALL
SELECT 
    'ODC-PON Mappings' as table_name,
    COUNT(*) as record_count
FROM odc_pon_mapping
UNION ALL
SELECT 
    'ODC Devices with PON' as table_name,
    COUNT(*) as record_count
FROM ftth_items 
WHERE item_type_id IN (4, 12)
AND odc_pon_connection IS NOT NULL;

-- =====================================================
-- END OF INTEGRATION SCRIPT
-- =====================================================
