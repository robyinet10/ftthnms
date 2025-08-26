-- Update Database untuk Pricing Management Feature
-- FTTHNMS v4.2.0 - Default Pricing Management untuk Auto-Generate Tiang Tumpu
-- Tanggal: 2025-01-15

USE ftthnms;

-- 1. Buat table untuk menyimpan default pricing settings
CREATE TABLE IF NOT EXISTS default_pricing (
    id INT(11) NOT NULL AUTO_INCREMENT,
    setting_type VARCHAR(50) NOT NULL COMMENT 'Tipe setting: tiang_tumpu, kabel, etc',
    price_value DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Harga default dalam Rupiah',
    auto_calculate TINYINT(1) DEFAULT 1 COMMENT 'Flag auto calculate cost saat generate',
    description TEXT NULL COMMENT 'Deskripsi setting pricing',
    created_by INT(11) NULL COMMENT 'User yang create setting',
    updated_by INT(11) NULL COMMENT 'User yang terakhir update',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_setting_type (setting_type),
    KEY idx_setting_type (setting_type),
    KEY idx_created_by (created_by),
    CONSTRAINT fk_pricing_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_pricing_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci 
COMMENT='Table untuk menyimpan default pricing settings';

-- 2. Insert default pricing untuk tiang tumpu
INSERT INTO default_pricing (setting_type, price_value, auto_calculate, description, created_by, updated_by) 
VALUES ('tiang_tumpu', 750000.00, 1, 'Default harga untuk auto-generate tiang tumpu (per unit)', 1, 1)
ON DUPLICATE KEY UPDATE 
    price_value = 750000.00,
    description = 'Default harga untuk auto-generate tiang tumpu (per unit)',
    updated_at = CURRENT_TIMESTAMP;

-- 3. Tambah kolom total_generated_cost di cable_routes untuk tracking biaya
ALTER TABLE cable_routes 
ADD COLUMN total_generated_cost DECIMAL(12,2) DEFAULT 0 COMMENT 'Total biaya tiang tumpu yang di-generate (Rupiah)' AFTER generate_at_turns;

-- 4. Update existing auto-generated tiang tumpu dengan default price jika belum ada
UPDATE ftth_items 
SET item_price = 750000.00 
WHERE item_type_id = 2 
  AND is_auto_generated = 1 
  AND (item_price IS NULL OR item_price = 0);

-- 5. Function untuk calculate total cost dari route
DELIMITER //
DROP FUNCTION IF EXISTS calculate_route_tiang_cost//
CREATE FUNCTION calculate_route_tiang_cost(route_id INT) 
RETURNS DECIMAL(12,2)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE total_cost DECIMAL(12,2) DEFAULT 0;
    
    SELECT COALESCE(SUM(item_price), 0) INTO total_cost
    FROM ftth_items 
    WHERE auto_generated_by_route_id = route_id 
      AND is_auto_generated = 1 
      AND item_type_id = 2;
    
    RETURN total_cost;
END//
DELIMITER ;

-- 6. Trigger untuk auto-update total_generated_cost di cable_routes
DELIMITER //
DROP TRIGGER IF EXISTS update_route_generated_cost//
CREATE TRIGGER update_route_generated_cost
AFTER INSERT ON ftth_items
FOR EACH ROW
BEGIN
    IF NEW.is_auto_generated = 1 AND NEW.auto_generated_by_route_id IS NOT NULL AND NEW.item_type_id = 2 THEN
        UPDATE cable_routes 
        SET total_generated_cost = calculate_route_tiang_cost(NEW.auto_generated_by_route_id)
        WHERE id = NEW.auto_generated_by_route_id;
    END IF;
END//

DROP TRIGGER IF EXISTS update_route_generated_cost_update//
CREATE TRIGGER update_route_generated_cost_update
AFTER UPDATE ON ftth_items
FOR EACH ROW
BEGIN
    IF NEW.is_auto_generated = 1 AND NEW.auto_generated_by_route_id IS NOT NULL AND NEW.item_type_id = 2 THEN
        UPDATE cable_routes 
        SET total_generated_cost = calculate_route_tiang_cost(NEW.auto_generated_by_route_id)
        WHERE id = NEW.auto_generated_by_route_id;
    END IF;
END//

DROP TRIGGER IF EXISTS update_route_generated_cost_delete//
CREATE TRIGGER update_route_generated_cost_delete
AFTER DELETE ON ftth_items
FOR EACH ROW
BEGIN
    IF OLD.is_auto_generated = 1 AND OLD.auto_generated_by_route_id IS NOT NULL AND OLD.item_type_id = 2 THEN
        UPDATE cable_routes 
        SET total_generated_cost = calculate_route_tiang_cost(OLD.auto_generated_by_route_id)
        WHERE id = OLD.auto_generated_by_route_id;
    END IF;
END//
DELIMITER ;

-- 7. Create indexes untuk performance
CREATE INDEX idx_pricing_settings ON default_pricing(setting_type, auto_calculate);
CREATE INDEX idx_cable_routes_cost ON cable_routes(total_generated_cost);

-- 8. View untuk pricing statistics
CREATE OR REPLACE VIEW pricing_statistics AS
SELECT 
    'tiang_tumpu' as item_category,
    COUNT(CASE WHEN is_auto_generated = 1 THEN 1 END) as auto_generated_count,
    COUNT(CASE WHEN is_auto_generated = 0 THEN 1 END) as manual_count,
    COUNT(*) as total_count,
    COALESCE(SUM(CASE WHEN is_auto_generated = 1 THEN item_price END), 0) as auto_generated_cost,
    COALESCE(SUM(CASE WHEN is_auto_generated = 0 THEN item_price END), 0) as manual_cost,
    COALESCE(SUM(item_price), 0) as total_cost,
    COALESCE(AVG(CASE WHEN is_auto_generated = 1 THEN item_price END), 0) as avg_auto_price,
    COALESCE(AVG(CASE WHEN is_auto_generated = 0 THEN item_price END), 0) as avg_manual_price,
    COALESCE(AVG(item_price), 0) as avg_total_price
FROM ftth_items 
WHERE item_type_id = 2;

-- 9. Sample data untuk testing cost calculation
INSERT INTO default_pricing (setting_type, price_value, auto_calculate, description, created_by, updated_by) 
VALUES 
    ('kabel_fiber', 15000.00, 1, 'Default harga kabel fiber per meter', 1, 1),
    ('splitter_1x4', 250000.00, 1, 'Default harga splitter 1:4', 1, 1),
    ('splitter_1x8', 350000.00, 1, 'Default harga splitter 1:8', 1, 1)
ON DUPLICATE KEY UPDATE 
    updated_at = CURRENT_TIMESTAMP;

-- 10. Verifikasi hasil update
SELECT 'default_pricing table created' as status, COUNT(*) as total_settings
FROM default_pricing;

SELECT 'cable_routes enhanced' as status, 
       COUNT(*) as total_routes,
       SUM(CASE WHEN total_generated_cost > 0 THEN 1 ELSE 0 END) as routes_with_cost
FROM cable_routes;

SELECT 'pricing statistics ready' as status,
       auto_generated_count,
       manual_count,
       total_count,
       CONCAT('Rp ', FORMAT(total_cost, 0)) as total_cost_formatted
FROM pricing_statistics;

-- Tampilkan default pricing settings
SELECT setting_type, 
       CONCAT('Rp ', FORMAT(price_value, 0)) as price_formatted,
       auto_calculate,
       description,
       created_at
FROM default_pricing 
ORDER BY setting_type;
