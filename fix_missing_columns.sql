-- =====================================================
-- FIX MISSING COLUMNS - FTTHNMS Database
-- =====================================================
-- Date: 2025-08-23
-- Issue: Application expects columns that don't exist in database
-- Error: SQLSTATE[42S22]: Column not found: 1054 Unknown column

USE ftthnms;

-- 1. Add ont_service_plan column (missing)
ALTER TABLE ftth_items 
ADD COLUMN ont_service_plan VARCHAR(100) NULL COMMENT 'Internet service plan for ONT/HTB' 
AFTER customer_address;

-- 2. Add ont_connection_status column (application expects this specific name)
ALTER TABLE ftth_items 
ADD COLUMN ont_connection_status ENUM('connected','disconnected','suspended','maintenance') DEFAULT 'connected' COMMENT 'ONT specific connection status' 
AFTER ont_service_plan;

-- 3. Verify the columns were added
SELECT 'Columns added successfully!' as status;
SHOW COLUMNS FROM ftth_items LIKE '%service%';
SHOW COLUMNS FROM ftth_items LIKE '%ont_connection%';

-- 4. Check if odp_splitter_ratio exists (should exist from previous fix)
SHOW COLUMNS FROM ftth_items LIKE '%odp_splitter%';

SELECT 'Database schema fixed for items.php compatibility!' as result;
