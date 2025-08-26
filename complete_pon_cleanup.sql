-- =====================================================
-- COMPLETE PON CLEANUP & REAL DATA INTEGRATION
-- FTTH Network Monitoring System
-- =====================================================
-- File: complete_pon_cleanup.sql
-- Description: Pembersihan komprehensif data PON dan integrasi data real
-- Date: 2025-01-17
-- =====================================================

-- Set foreign key checks off untuk menghindari constraint issues
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- 1. COMPLETE CLEANUP - HAPUS SEMUA DATA PON
-- =====================================================

-- Clear ALL PON-related data
DELETE FROM odc_pon_mapping;
DELETE FROM pon_vlans;
DELETE FROM olt_pons;

-- Clear ALL pon_config from ALL OLT devices
UPDATE ftth_items SET pon_config = NULL WHERE item_type_id = 1;

-- Clear ALL ODC connection data
UPDATE ftth_items SET 
    odc_pon_connection = NULL,
    odc_vlan_id = NULL
WHERE item_type_id IN (4, 12);

-- =====================================================
-- 2. VERIFY CLEANUP
-- =====================================================

-- Check if all PON data is cleared
SELECT 
    'CLEANUP VERIFICATION' as status,
    'All PON data should be cleared' as description;

-- Check olt_pons table
SELECT 
    'PON Ports remaining' as table_name,
    COUNT(*) as count
FROM olt_pons;

-- Check pon_vlans table
SELECT 
    'VLANs remaining' as table_name,
    COUNT(*) as count
FROM pon_vlans;

-- Check odc_pon_mapping table
SELECT 
    'ODC-PON Mappings remaining' as table_name,
    COUNT(*) as count
FROM odc_pon_mapping;

-- Check OLT devices with pon_config
SELECT 
    'OLT with pon_config remaining' as table_name,
    COUNT(*) as count
FROM ftth_items 
WHERE item_type_id = 1 
AND pon_config IS NOT NULL;

-- Check ODC devices with PON connection
SELECT 
    'ODC with PON connection remaining' as table_name,
    COUNT(*) as count
FROM ftth_items 
WHERE item_type_id IN (4, 12)
AND odc_pon_connection IS NOT NULL;

-- =====================================================
-- 3. GET REAL OLT DEVICES
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
-- 4. CREATE REAL PON PORTS (ONLY IF OLT EXISTS)
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
-- 5. CREATE REAL VLAN CONFIGURATION
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
-- 6. CREATE REAL PON_CONFIG JSON
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
-- 7. FINAL VERIFICATION
-- =====================================================

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Show final status
SELECT 
    'COMPLETE PON CLEANUP & INTEGRATION' as status,
    'Real PON data has been created' as description;

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

-- Show what API will return
SELECT 
    'API Response Simulation' as api_info,
    CONCAT(olt.name, ' (', olt.ip_address, ') - ', op.pon_port, ' (0/4 ODCs)') as dropdown_option
FROM olt_pons op
JOIN ftth_items olt ON op.item_id = olt.id
ORDER BY olt.name, op.pon_port;

-- =====================================================
-- END OF COMPLETE PON CLEANUP SCRIPT
-- =====================================================
