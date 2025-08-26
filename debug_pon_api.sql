-- =====================================================
-- DEBUG PON API & DATA CLEANUP VERIFICATION
-- FTTH Network Monitoring System
-- =====================================================
-- File: debug_pon_api.sql
-- Description: Debug API PON dan verifikasi pembersihan data
-- Date: 2025-01-17
-- =====================================================

-- =====================================================
-- 1. CHECK CURRENT PON DATA STATUS
-- =====================================================

-- Check what API would return
SELECT 
    'API SIMULATION - Current Data' as status,
    'This is what api/olt_pons.php would return' as description;

-- Simulate the exact query from api/olt_pons.php
SELECT 
    op.id,
    op.pon_port,
    op.description,
    op.status,
    olt.id as olt_id,
    olt.name as olt_name,
    olt.ip_address as olt_ip,
    COALESCE(COUNT(opm.id), 0) as connected_odcs
FROM olt_pons op
INNER JOIN ftth_items olt ON op.item_id = olt.id
LEFT JOIN odc_pon_mapping opm ON op.pon_port = opm.pon_port AND opm.status = 'active'
WHERE op.status = 'active' AND olt.item_type_id = 1
GROUP BY op.id, op.pon_port, op.description, op.status, olt.id, olt.name, olt.ip_address
HAVING connected_odcs < 4
ORDER BY olt.name, op.pon_port;

-- =====================================================
-- 2. CHECK ALL PON TABLES
-- =====================================================

-- Check olt_pons table
SELECT 
    'olt_pons table' as table_name,
    COUNT(*) as total_records
FROM olt_pons;

-- Show all records in olt_pons
SELECT 
    op.id,
    olt.name as olt_name,
    op.pon_port,
    op.description,
    op.status
FROM olt_pons op
JOIN ftth_items olt ON op.item_id = olt.id
ORDER BY olt.name, op.pon_port;

-- Check pon_vlans table
SELECT 
    'pon_vlans table' as table_name,
    COUNT(*) as total_records
FROM pon_vlans;

-- Show all records in pon_vlans
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

-- Check odc_pon_mapping table
SELECT 
    'odc_pon_mapping table' as table_name,
    COUNT(*) as total_records
FROM odc_pon_mapping;

-- Show all records in odc_pon_mapping
SELECT 
    opm.id,
    odc.name as odc_name,
    olt.name as olt_name,
    opm.pon_port,
    opm.vlan_id,
    opm.status
FROM odc_pon_mapping opm
JOIN ftth_items odc ON opm.odc_item_id = odc.id
JOIN ftth_items olt ON opm.olt_item_id = olt.id
ORDER BY odc.name;

-- =====================================================
-- 3. CHECK OLT DEVICES
-- =====================================================

-- Check OLT devices
SELECT 
    'OLT Devices' as table_name,
    COUNT(*) as total_records
FROM ftth_items 
WHERE item_type_id = 1;

-- Show all OLT devices
SELECT 
    id,
    name,
    ip_address,
    CASE 
        WHEN pon_config IS NULL THEN 'NULL'
        ELSE 'HAS CONFIG'
    END as pon_config_status
FROM ftth_items 
WHERE item_type_id = 1
ORDER BY name;

-- =====================================================
-- 4. CHECK ODC DEVICES
-- =====================================================

-- Check ODC devices with PON connection
SELECT 
    'ODC Devices with PON' as table_name,
    COUNT(*) as total_records
FROM ftth_items 
WHERE item_type_id IN (4, 12)
AND odc_pon_connection IS NOT NULL;

-- Show all ODC devices with PON connection
SELECT 
    id,
    name,
    item_type_id,
    odc_pon_connection,
    odc_vlan_id
FROM ftth_items 
WHERE item_type_id IN (4, 12)
AND odc_pon_connection IS NOT NULL
ORDER BY name;

-- =====================================================
-- 5. FORCE CLEAN IF DATA EXISTS
-- =====================================================

-- Check if we need to clean
SELECT 
    'CLEANUP NEEDED' as status,
    CASE 
        WHEN (SELECT COUNT(*) FROM olt_pons) > 0 THEN 'YES - PON data exists'
        WHEN (SELECT COUNT(*) FROM pon_vlans) > 0 THEN 'YES - VLAN data exists'
        WHEN (SELECT COUNT(*) FROM odc_pon_mapping) > 0 THEN 'YES - Mapping data exists'
        ELSE 'NO - Data is clean'
    END as cleanup_status;

-- =====================================================
-- END OF DEBUG SCRIPT
-- =====================================================
