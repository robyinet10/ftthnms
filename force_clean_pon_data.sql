-- =====================================================
-- FORCE CLEAN PON DATA
-- FTTH Network Monitoring System
-- =====================================================
-- File: force_clean_pon_data.sql
-- Description: Paksa pembersihan data PON yang lebih agresif
-- Date: 2025-01-17
-- =====================================================

-- Set foreign key checks off untuk menghindari constraint issues
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- 1. AGGRESSIVE CLEANING - HAPUS SEMUA DATA PON
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
-- 2. VERIFY CLEANING RESULTS
-- =====================================================

-- Check if all PON data is cleared
SELECT 
    'CLEANING VERIFICATION' as status,
    'Checking if all PON data is cleared' as description;

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
-- 3. RE-ENABLE FOREIGN KEY CHECKS
-- =====================================================

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- 4. FINAL STATUS
-- =====================================================

SELECT 
    'FORCE CLEAN COMPLETE' as status,
    'All PON data has been forcefully cleared' as description;

-- =====================================================
-- END OF FORCE CLEAN SCRIPT
-- =====================================================
