-- =====================================================
-- SYNC PON DATA - 2 PON PORTS ONLY
-- FTTH Network Monitoring System
-- =====================================================
-- File: sync_pon_data_2_ports.sql
-- Description: Sinkronkan data PON dengan hanya 2 PON ports (1 per OLT)
-- Date: 2025-01-17
-- =====================================================

-- Set foreign key checks off untuk menghindari constraint issues
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- 1. UPDATE PON_CONFIG FIELD FOR OLT DEVICES (2 PORTS ONLY)
-- =====================================================

-- Update OLT Central pon_config (hanya 1 PON port)
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
            )
        )
    )
WHERE name = 'OLT Central' AND item_type_id = 1;

-- Update OLT North pon_config (hanya 1 PON port)
UPDATE ftth_items SET 
    pon_config = JSON_OBJECT(
        'pons', JSON_ARRAY(
            JSON_OBJECT(
                'port', 'PON2/2/1',
                'description', 'PON Port 1 untuk Area North',
                'vlans', JSON_ARRAY(
                    JSON_OBJECT(
                        'vlan_id', '200',
                        'description', 'VLAN 200 (IP: 172.0.200.0/24) [MikroTik]'
                    )
                )
            )
        )
    )
WHERE name = 'OLT North' AND item_type_id = 1;

-- =====================================================
-- 2. UPDATE OLT_PONS TABLE (REMOVE EXTRA PORTS)
-- =====================================================

-- Delete extra PON ports from olt_pons table
DELETE FROM olt_pons WHERE pon_port IN (
    'PON1/1/2', 'PON1/1/3', 'PON1/1/4',  -- Remove extra OLT Central ports
    'PON2/2/2', 'PON2/2/3', 'PON2/2/4'   -- Remove extra OLT North ports
);

-- =====================================================
-- 3. UPDATE PON_VLANS TABLE (REMOVE EXTRA VLANS)
-- =====================================================

-- Delete VLANs for removed PON ports
DELETE pv FROM pon_vlans pv
JOIN olt_pons op ON pv.pon_id = op.id
WHERE op.pon_port IN (
    'PON1/1/2', 'PON1/1/3', 'PON1/1/4',  -- Remove extra OLT Central ports
    'PON2/2/2', 'PON2/2/3', 'PON2/2/4'   -- Remove extra OLT North ports
);

-- =====================================================
-- 4. VERIFICATION - Check updated data
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

-- Show detailed PON configuration for each OLT
SELECT 
    name as olt_name,
    JSON_EXTRACT(pon_config, '$.pons[*].port') as pon_ports,
    JSON_EXTRACT(pon_config, '$.pons[*].vlans[*].vlan_id') as vlan_ids
FROM ftth_items 
WHERE item_type_id = 1 
AND pon_config IS NOT NULL;

-- Show remaining PON ports in olt_pons table
SELECT 
    olt.name as olt_name,
    op.pon_port,
    op.description
FROM olt_pons op
JOIN ftth_items olt ON op.item_id = olt.id
ORDER BY olt.name, op.pon_port;

-- Show remaining VLANs in pon_vlans table
SELECT 
    olt.name as olt_name,
    op.pon_port,
    pv.vlan_id,
    pv.description
FROM pon_vlans pv
JOIN olt_pons op ON pv.pon_id = op.id
JOIN ftth_items olt ON op.item_id = olt.id
ORDER BY olt.name, op.pon_port, pv.vlan_id;

-- =====================================================
-- 5. FINAL VERIFICATION
-- =====================================================

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Verify data integrity
SELECT 'PON Data Sync Complete - 2 Ports Only' as status;

-- Show summary of changes
SELECT 
    'OLT Devices Updated' as table_name,
    COUNT(*) as record_count
FROM ftth_items 
WHERE item_type_id = 1 
AND pon_config IS NOT NULL
UNION ALL
SELECT 
    'Total PON Ports' as table_name,
    SUM(JSON_LENGTH(JSON_EXTRACT(pon_config, '$.pons'))) as record_count
FROM ftth_items 
WHERE item_type_id = 1 
AND pon_config IS NOT NULL
UNION ALL
SELECT 
    'Total VLANs' as table_name,
    SUM(JSON_LENGTH(JSON_EXTRACT(pon_config, '$.pons[*].vlans'))) as record_count
FROM ftth_items 
WHERE item_type_id = 1 
AND pon_config IS NOT NULL
UNION ALL
SELECT 
    'Remaining PON Ports in Table' as table_name,
    COUNT(*) as record_count
FROM olt_pons
UNION ALL
SELECT 
    'Remaining VLANs in Table' as table_name,
    COUNT(*) as record_count
FROM pon_vlans;

-- =====================================================
-- END OF SYNC SCRIPT - 2 PORTS ONLY
-- =====================================================
