-- Update Database Data Only (Tanpa Menambah Kolom)
-- Versi: 2.1.4 (Update Data Only)
-- Tanggal: 2025-01-17

USE ftthnms;

-- Update data existing dengan nilai default untuk item_type berdasarkan item_type_id
UPDATE ftth_items SET item_type = 'Huawei MA5800-X2' WHERE item_type_id = 1 AND (item_type IS NULL OR item_type = ''); -- OLT
UPDATE ftth_items SET item_type = 'Tiang Beton 9m' WHERE item_type_id = 2 AND (item_type IS NULL OR item_type = ''); -- Tiang Tumpu
UPDATE ftth_items SET item_type = 'Tiang ODP Standard' WHERE item_type_id = 3 AND (item_type IS NULL OR item_type = ''); -- Tiang ODP
UPDATE ftth_items SET item_type = 'Tiang ODC Standard' WHERE item_type_id = 4 AND (item_type IS NULL OR item_type = ''); -- Tiang ODC
UPDATE ftth_items SET item_type = 'Joint Closure 12 Core' WHERE item_type_id = 5 AND (item_type IS NULL OR item_type = ''); -- Tiang Joint Closure
UPDATE ftth_items SET item_type = 'ONU GPON Standard' WHERE item_type_id = 6 AND (item_type IS NULL OR item_type = ''); -- ONT
UPDATE ftth_items SET item_type = 'Router Mikrotik RB951G' WHERE item_type_id = 7 AND (item_type IS NULL OR item_type = ''); -- Server
UPDATE ftth_items SET item_type = 'ODC Cabinet 24 Port' WHERE item_type_id = 8 AND (item_type IS NULL OR item_type = ''); -- ODC
UPDATE ftth_items SET item_type = 'Access Point TP-Link EAP225' WHERE item_type_id = 9 AND (item_type IS NULL OR item_type = ''); -- Access Point
UPDATE ftth_items SET item_type = 'Home Terminal Box Standard' WHERE item_type_id = 10 AND (item_type IS NULL OR item_type = ''); -- HTB

-- Update data existing dengan nilai default untuk item_price berdasarkan item_type_id
UPDATE ftth_items SET item_price = 15000000.00 WHERE item_type_id = 1 AND (item_price IS NULL OR item_price = 0); -- OLT: Rp 15.000.000
UPDATE ftth_items SET item_price = 2500000.00 WHERE item_type_id = 2 AND (item_price IS NULL OR item_price = 0); -- Tiang Tumpu: Rp 2.500.000
UPDATE ftth_items SET item_price = 1800000.00 WHERE item_type_id = 3 AND (item_price IS NULL OR item_price = 0); -- Tiang ODP: Rp 1.800.000
UPDATE ftth_items SET item_price = 2000000.00 WHERE item_type_id = 4 AND (item_price IS NULL OR item_price = 0); -- Tiang ODC: Rp 2.000.000
UPDATE ftth_items SET item_price = 500000.00 WHERE item_type_id = 5 AND (item_price IS NULL OR item_price = 0); -- Joint Closure: Rp 500.000
UPDATE ftth_items SET item_price = 800000.00 WHERE item_type_id = 6 AND (item_price IS NULL OR item_price = 0); -- ONT ONU: Rp 800.000
UPDATE ftth_items SET item_price = 1200000.00 WHERE item_type_id = 7 AND (item_price IS NULL OR item_price = 0); -- Server Router: Rp 1.200.000
UPDATE ftth_items SET item_price = 3500000.00 WHERE item_type_id = 8 AND (item_price IS NULL OR item_price = 0); -- ODC Cabinet: Rp 3.500.000
UPDATE ftth_items SET item_price = 1500000.00 WHERE item_type_id = 9 AND (item_price IS NULL OR item_price = 0); -- Access Point: Rp 1.500.000
UPDATE ftth_items SET item_price = 300000.00 WHERE item_type_id = 10 AND (item_price IS NULL OR item_price = 0); -- HTB: Rp 300.000

-- Menampilkan hasil update
SELECT 
    it.name as item_type_name,
    COUNT(fi.id) as total_items,
    AVG(fi.item_price) as avg_price,
    MIN(fi.item_price) as min_price,
    MAX(fi.item_price) as max_price
FROM ftth_items fi
JOIN item_types it ON fi.item_type_id = it.id
GROUP BY fi.item_type_id, it.name
ORDER BY fi.item_type_id;

-- Menampilkan sample data yang sudah diupdate
SELECT 
    fi.id,
    fi.name,
    it.name as item_type_name,
    fi.item_type,
    fi.item_price,
    fi.status
FROM ftth_items fi
JOIN item_types it ON fi.item_type_id = it.id
LIMIT 10;
