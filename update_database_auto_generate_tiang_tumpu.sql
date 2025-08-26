-- Update Database untuk Auto Generate Tiang Tumpu Feature
-- FTTHNMS v4.1.0 - Auto Generate Tiang Tumpu setiap 30 meter dan tikungan
-- Tanggal: 2025-01-15

USE ftthnms;

-- 1. Tambah kolom ke cable_routes untuk tracking generated tiang tumpu
ALTER TABLE cable_routes 
ADD COLUMN auto_generate_tiang_tumpu TINYINT(1) DEFAULT 0 COMMENT 'Flag untuk auto generate tiang tumpu',
ADD COLUMN generated_tiang_tumpu_ids TEXT NULL COMMENT 'JSON array dari ID tiang tumpu yang di-generate otomatis',
ADD COLUMN tiang_tumpu_interval_meters INT DEFAULT 30 COMMENT 'Interval jarak untuk generate tiang tumpu dalam meter',
ADD COLUMN generate_at_turns TINYINT(1) DEFAULT 1 COMMENT 'Generate tiang tumpu di tikungan';

-- 2. Tambah kolom ke ftth_items untuk marking auto-generated items
ALTER TABLE ftth_items
ADD COLUMN is_auto_generated TINYINT(1) DEFAULT 0 COMMENT 'Flag untuk item yang di-generate otomatis',
ADD COLUMN auto_generated_by_route_id INT NULL COMMENT 'ID route yang generate item ini',
ADD COLUMN auto_generated_type ENUM('interval', 'turn', 'manual') NULL COMMENT 'Tipe auto generation: interval=setiap 30m, turn=di tikungan';

-- 3. Tambah foreign key untuk auto_generated_by_route_id
ALTER TABLE ftth_items
ADD CONSTRAINT fk_auto_generated_route 
FOREIGN KEY (auto_generated_by_route_id) REFERENCES cable_routes(id) ON DELETE SET NULL;

-- 4. Tambah index untuk performance
CREATE INDEX idx_auto_generated ON ftth_items(is_auto_generated);
CREATE INDEX idx_auto_generated_route ON ftth_items(auto_generated_by_route_id);
CREATE INDEX idx_auto_generate_tiang ON cable_routes(auto_generate_tiang_tumpu);

-- 5. Update item_types untuk memastikan Tiang Tumpu ada
UPDATE item_types SET 
    name = 'Tiang Tumpu',
    icon = 'fas fa-tower-broadcast',
    color = '#4ECDC4'
WHERE id = 2;

-- 6. Insert default configuration jika diperlukan
INSERT INTO item_types (id, name, icon, color) VALUES (2, 'Tiang Tumpu', 'fas fa-tower-broadcast', '#4ECDC4')
ON DUPLICATE KEY UPDATE 
    name = 'Tiang Tumpu',
    icon = 'fas fa-tower-broadcast',
    color = '#4ECDC4';

-- 7. Verifikasi hasil update
SELECT 'cable_routes columns added' as status, 
       COUNT(*) as total_routes,
       SUM(auto_generate_tiang_tumpu) as routes_with_auto_generate
FROM cable_routes;

SELECT 'ftth_items columns added' as status,
       COUNT(*) as total_items,
       SUM(is_auto_generated) as auto_generated_items
FROM ftth_items;

SELECT 'item_types updated' as status,
       id,
       name,
       icon,
       color
FROM item_types 
WHERE id = 2;

-- 8. Sample data untuk testing (optional)
-- INSERT INTO cable_routes (from_item_id, to_item_id, route_coordinates, distance, cable_type, core_count, route_type, status, auto_generate_tiang_tumpu, tiang_tumpu_interval_meters, generate_at_turns) 
-- VALUES (1, 2, '[{"lat": -0.937783, "lng": 119.854373}, {"lat": -0.938000, "lng": 119.854600}]', 150.5, 'Fiber Optic', 24, 'road', 'planned', 1, 30, 1);
