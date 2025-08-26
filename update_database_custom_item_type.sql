-- Update Database untuk Menambahkan Item Type "Custom"
-- Versi: 2.1.5 (Added Custom Item Type for Accounting)
-- Tanggal: 2025-01-17

USE ftthnms;

-- Menambahkan item type "Custom" untuk accounting
INSERT INTO item_types (name, icon, color) VALUES
('Custom', 'fas fa-tools', '#9B59B6');

-- Menampilkan hasil penambahan
SELECT 
    id,
    name,
    icon,
    color,
    created_at
FROM item_types 
WHERE name = 'Custom';

-- Menampilkan semua item types untuk verifikasi
SELECT 
    id,
    name,
    icon,
    color
FROM item_types 
ORDER BY id;
