-- Migration untuk menambahkan route_type field ke cable_routes table
-- Untuk database yang sudah ada

-- Menambahkan route_type field jika belum ada
ALTER TABLE cable_routes 
ADD COLUMN IF NOT EXISTS route_type ENUM('straight', 'road', 'direct') DEFAULT 'straight' 
COMMENT 'Jenis route: straight=garis lurus, road=ikuti jalan, direct=fallback'
AFTER core_count;

-- Update existing routes untuk set default route_type berdasarkan coordinate pattern
-- Routes dengan 2 coordinate points = straight line
-- Routes dengan > 2 coordinate points = road route
UPDATE cable_routes 
SET route_type = CASE 
    WHEN route_coordinates IS NULL OR route_coordinates = '' THEN 'straight'
    WHEN JSON_LENGTH(JSON_EXTRACT(route_coordinates, '$')) <= 2 THEN 'straight'
    ELSE 'road'
END
WHERE route_type IS NULL OR route_type = 'straight';

-- Verify migration
SELECT 
    id, 
    route_type,
    CASE 
        WHEN route_coordinates IS NULL THEN 'NULL'
        ELSE CONCAT(JSON_LENGTH(JSON_EXTRACT(route_coordinates, '$')), ' points')
    END as coordinate_info
FROM cable_routes 
ORDER BY id;
