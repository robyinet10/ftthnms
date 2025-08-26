-- =====================================================
-- SYNC PON DATA - Sinkronkan data PON ke pon_config
-- FTTH Network Monitoring System
-- =====================================================
-- File: sync_pon_data.sql
-- Description: Sinkronkan data dari tabel olt_pons dan pon_vlans ke field pon_config
-- Date: 2025-01-17
-- =====================================================

-- Set foreign key checks off untuk menghindari constraint issues
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- 1. UPDATE PON_CONFIG FIELD FOR OLT DEVICES
-- =====================================================

-- Update OLT Central pon_config
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
                'port', 'PON1/1/2',
                'description', 'PON Port 2 untuk Area Central',
                'vlans', JSON_ARRAY(
                    JSON_OBJECT(
                        'vlan_id', '101',
                        'description', 'VLAN 101 (IP: 172.0.101.0/24) [MikroTik]'
                    )
                )
            ),
            JSON_OBJECT(
                'port', 'PON1/1/3',
                'description', 'PON Port 3 untuk Area Central',
                'vlans', JSON_ARRAY(
                    JSON_OBJECT(
                        'vlan_id', '102',
                        'description', 'VLAN 102 (IP: 172.0.102.0/24) [MikroTik]'
                    )
                )
            ),
            JSON_OBJECT(
                'port', 'PON1/1/4',
                'description', 'PON Port 4 untuk Area Central',
                'vlans', JSON_ARRAY(
                    JSON_OBJECT(
                        'vlan_id', '103',
                        'description', 'VLAN 103 (IP: 172.0.103.0/24) [MikroTik]'
                    )
                )
            )
        )
    )
WHERE name = 'OLT Central' AND item_type_id = 1;

-- Update OLT North pon_config
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
            ),
            JSON_OBJECT(
                'port', 'PON2/2/2',
                'description', 'PON Port 2 untuk Area North',
                'vlans', JSON_ARRAY(
                    JSON_OBJECT(
                        'vlan_id', '201',
                        'description', 'VLAN 201 (IP: 172.0.201.0/24) [MikroTik]'
                    )
                )
            ),
            JSON_OBJECT(
                'port', 'PON2/2/3',
                'description', 'PON Port 3 untuk Area North',
                'vlans', JSON_ARRAY(
                    JSON_OBJECT(
                        'vlan_id', '202',
                        'description', 'VLAN 202 (IP: 172.0.202.0/24) [MikroTik]'
                    )
                )
            ),
            JSON_OBJECT(
                'port', 'PON2/2/4',
                'description', 'PON Port 4 untuk Area North',
                'vlans', JSON_ARRAY(
                    JSON_OBJECT(
                        'vlan_id', '203',
                        'description', 'VLAN 203 (IP: 172.0.203.0/24) [MikroTik]'
                    )
                )
            )
        )
    )
WHERE name = 'OLT North' AND item_type_id = 1;

-- =====================================================
-- 2. VERIFICATION - Check updated data
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

-- =====================================================
-- 3. ALTERNATIVE: Dynamic sync from tables to pon_config
-- =====================================================

-- Function to sync data from olt_pons and pon_vlans tables to pon_config
-- This can be used for future updates

/*
-- Example of how to dynamically generate pon_config from tables:
SELECT 
    olt.id as olt_id,
    olt.name as olt_name,
    JSON_OBJECT(
        'pons', JSON_ARRAYAGG(
            JSON_OBJECT(
                'port', op.pon_port,
                'description', op.description,
                'vlans', (
                    SELECT JSON_ARRAYAGG(
                        JSON_OBJECT(
                            'vlan_id', pv.vlan_id,
                            'description', CONCAT(pv.description, ' [MikroTik]')
                        )
                    )
                    FROM pon_vlans pv 
                    WHERE pv.pon_id = op.id
                )
            )
        )
    ) as pon_config
FROM ftth_items olt
JOIN olt_pons op ON olt.id = op.item_id
WHERE olt.item_type_id = 1
GROUP BY olt.id, olt.name;
*/

-- =====================================================
-- 4. FINAL VERIFICATION
-- =====================================================

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Verify data integrity
SELECT 'PON Data Sync Complete' as status;

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
AND pon_config IS NOT NULL;

-- =====================================================
-- END OF SYNC SCRIPT
-- =====================================================
