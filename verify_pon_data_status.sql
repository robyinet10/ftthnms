-- =====================================================
-- VERIFY PON DATA STATUS
-- FTTH Network Monitoring System
-- =====================================================
-- File: verify_pon_data_status.sql
-- Description: Verifikasi status data PON saat ini di database
-- Date: 2025-01-17
-- =====================================================

-- =====================================================
-- 1. CHECK CURRENT PON DATA STATUS
-- =====================================================

-- Check OLT devices with pon_config
SELECT 
    'OLT Devices with pon_config' as status,
    COUNT(*) as count,
    GROUP_CONCAT(name) as olt_names
FROM ftth_items 
WHERE item_type_id = 1 
AND pon_config IS NOT NULL;

-- Check olt_pons table
SELECT 
    'PON Ports in olt_pons table' as status,
    COUNT(*) as count,
    GROUP_CONCAT(CONCAT(olt.name, ' - ', op.pon_port)) as pon_ports
FROM olt_pons op
JOIN ftth_items olt ON op.item_id = olt.id;

-- Check pon_vlans table
SELECT 
    'VLANs in pon_vlans table' as status,
    COUNT(*) as count,
    GROUP_CONCAT(CONCAT(olt.name, ' - ', op.pon_port, ' - VLAN ', pv.vlan_id)) as vlans
FROM pon_vlans pv
JOIN olt_pons op ON pv.pon_id = op.id
JOIN ftth_items olt ON op.item_id = olt.id;

-- Check odc_pon_mapping table
SELECT 
    'ODC-PON Mappings' as status,
    COUNT(*) as count,
    GROUP_CONCAT(CONCAT(odc.name, ' -> ', olt.name, ' - ', opm.pon_port)) as mappings
FROM odc_pon_mapping opm
JOIN ftth_items odc ON opm.odc_item_id = odc.id
JOIN ftth_items olt ON opm.olt_item_id = olt.id;

-- Check ODC devices with PON connection
SELECT 
    'ODC Devices with PON Connection' as status,
    COUNT(*) as count,
    GROUP_CONCAT(CONCAT(name, ' -> ', odc_pon_connection)) as connections
FROM ftth_items 
WHERE item_type_id IN (4, 12)
AND odc_pon_connection IS NOT NULL;

-- =====================================================
-- 2. DETAILED PON DATA ANALYSIS
-- =====================================================

-- Show all OLT devices
SELECT 
    id,
    name,
    ip_address,
    CASE 
        WHEN pon_config IS NULL THEN 'NULL'
        ELSE 'HAS CONFIG'
    END as pon_config_status,
    JSON_LENGTH(JSON_EXTRACT(pon_config, '$.pons')) as total_pon_ports
FROM ftth_items 
WHERE item_type_id = 1
ORDER BY name;

-- Show all PON ports
SELECT 
    op.id,
    olt.name as olt_name,
    op.pon_port,
    op.description,
    op.status,
    COUNT(pv.id) as vlan_count
FROM olt_pons op
JOIN ftth_items olt ON op.item_id = olt.id
LEFT JOIN pon_vlans pv ON op.id = pv.pon_id
GROUP BY op.id, olt.name, op.pon_port, op.description, op.status
ORDER BY olt.name, op.pon_port;

-- Show all VLANs
SELECT 
    pv.id,
    olt.name as olt_name,
    op.pon_port,
    pv.vlan_id,
    pv.description
FROM pon_vlans pv
JOIN olt_pons op ON pv.pon_id = op.id
JOIN ftth_items olt ON op.item_id = olt.id
ORDER BY olt.name, op.pon_port, pv.vlan_id;

-- Show all ODC-PON mappings
SELECT 
    opm.id,
    odc.name as odc_name,
    olt.name as olt_name,
    opm.pon_port,
    opm.vlan_id,
    opm.description,
    opm.status
FROM odc_pon_mapping opm
JOIN ftth_items odc ON opm.odc_item_id = odc.id
JOIN ftth_items olt ON opm.olt_item_id = olt.id
ORDER BY odc.name;

-- =====================================================
-- 3. API SIMULATION CHECK
-- =====================================================

-- Simulate what api/olt_pons.php would return
SELECT 
    'API Simulation - Available PON Ports' as api_response,
    CONCAT(olt.name, ' (', olt.ip_address, ') - ', op.pon_port, ' (', 
           COALESCE(connected_odcs.count, 0), '/4 ODCs)') as dropdown_option
FROM olt_pons op
JOIN ftth_items olt ON op.item_id = olt.id
LEFT JOIN (
    SELECT 
        pon_port,
        COUNT(*) as count
    FROM odc_pon_mapping 
    WHERE status = 'active'
    GROUP BY pon_port
) connected_odcs ON op.pon_port = connected_odcs.pon_port
ORDER BY olt.name, op.pon_port;

-- =====================================================
-- 4. SUMMARY STATUS
-- =====================================================

SELECT 
    'SUMMARY' as section,
    'Current PON Data Status' as description;

SELECT 
    'Total OLT Devices' as metric,
    COUNT(*) as value
FROM ftth_items 
WHERE item_type_id = 1
UNION ALL
SELECT 
    'OLT with pon_config' as metric,
    COUNT(*) as value
FROM ftth_items 
WHERE item_type_id = 1 
AND pon_config IS NOT NULL
UNION ALL
SELECT 
    'Total PON Ports' as metric,
    COUNT(*) as value
FROM olt_pons
UNION ALL
SELECT 
    'Total VLANs' as metric,
    COUNT(*) as value
FROM pon_vlans
UNION ALL
SELECT 
    'Total ODC-PON Mappings' as metric,
    COUNT(*) as value
FROM odc_pon_mapping
UNION ALL
SELECT 
    'ODC with PON Connection' as metric,
    COUNT(*) as value
FROM ftth_items 
WHERE item_type_id IN (4, 12)
AND odc_pon_connection IS NOT NULL;

-- =====================================================
-- END OF VERIFICATION SCRIPT
-- =====================================================
