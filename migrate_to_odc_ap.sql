-- Migration script untuk menambahkan ODC dan Access Point support
-- Dari versi 2.1.2 ke 2.1.3
-- Tanggal: 2024

USE ftthnms;

-- 1. Tambahkan field attenuation_notes untuk ODC
ALTER TABLE ftth_items ADD COLUMN IF NOT EXISTS attenuation_notes TEXT NULL COMMENT 'Catatan redaman dalam dB untuk ODC' AFTER pon_config;

-- 2. Tambahkan item types baru
INSERT IGNORE INTO item_types (id, name, icon, color) VALUES 
(8, 'ODC', 'fas fa-box', '#F39C12'),
(9, 'Access Point', 'fas fa-wifi', '#3498DB');

-- 3. Verifikasi hasil migration
SELECT 'attenuation_notes field check' as verification_step;
SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_COMMENT 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'ftthnms' 
AND TABLE_NAME = 'ftth_items' 
AND COLUMN_NAME = 'attenuation_notes';

SELECT 'item_types check' as verification_step;
SELECT id, name, icon, color 
FROM item_types 
WHERE id IN (8, 9) 
ORDER BY id;

SELECT 'Migration completed successfully' as status;
