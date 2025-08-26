-- Update Database untuk Mengubah Pelanggan menjadi ONT dan Menambahkan HTB
-- Versi: 2.1.6 (Updated Item Names and Added HTB)
-- Tanggal: 2025-01-17

USE ftthnms;

-- Mengubah nama "Pelanggan" menjadi "ONT"
UPDATE item_types 
SET name = 'ONT' 
WHERE name = 'Pelanggan';

-- Menambahkan item type "HTB" (Home Terminal Box)
INSERT INTO item_types (name, icon, color) VALUES
('HTB', 'fas fa-home', '#FF6B9D');

-- Menampilkan hasil update
SELECT 
    id,
    name,
    icon,
    color,
    created_at
FROM item_types 
ORDER BY id;

-- Update default values untuk item_type dan item_price untuk HTB
-- (akan dijalankan setelah ada data HTB)
-- UPDATE ftth_items SET item_type = 'Home Terminal Box Standard' WHERE item_type_id = (SELECT id FROM item_types WHERE name = 'HTB');
-- UPDATE ftth_items SET item_price = 300000.00 WHERE item_type_id = (SELECT id FROM item_types WHERE name = 'HTB');
