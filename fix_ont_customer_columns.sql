-- =====================================================
-- FIX ONT CUSTOMER COLUMNS - FTTHNMS Database
-- =====================================================
-- Date: 2025-08-23
-- Issue: Application expects ont_customer_name and ont_customer_address
-- Error: SQLSTATE[42S22]: Column not found: 1054 Unknown column 'ont_customer_name'

USE ftthnms;

-- 1. Add ont_customer_name column (application expects this)
ALTER TABLE ftth_items 
ADD COLUMN ont_customer_name VARCHAR(255) NULL COMMENT 'ONT Customer name (specific for ONT/HTB)' 
AFTER ont_serial_number;

-- 2. Add ont_customer_address column (application expects this)  
ALTER TABLE ftth_items 
ADD COLUMN ont_customer_address TEXT NULL COMMENT 'ONT Customer address (specific for ONT/HTB)' 
AFTER ont_customer_name;

-- 3. Verify the columns were added
SELECT 'ONT Customer columns added successfully!' as status;
SHOW COLUMNS FROM ftth_items LIKE '%ont_customer%';

-- 4. Check total columns now
SELECT COUNT(*) as total_columns FROM information_schema.columns 
WHERE table_schema='ftthnms' AND table_name='ftth_items';

SELECT 'Database schema updated for ONT customer fields!' as result;
