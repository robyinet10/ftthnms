-- Fix ODC Output Ports
-- Update ODC Central dengan output ports yang benar

USE ftthnms;

-- Update ODC Central dengan output ports default
UPDATE ftth_items 
SET odc_output_ports = 4,
    odc_capacity = 32,
    odc_ports_used = 0,
    odc_type = 'pole_mounted',
    odc_installation_type = 'pole',
    odc_main_splitter_ratio = '1:4',
    odc_odp_splitter_ratio = '1:8',
    odc_input_ports = 1
WHERE id = 3 AND name = 'ODC Central';

-- Verify the update
SELECT id, name, odc_output_ports, odc_capacity, odc_ports_used, status 
FROM ftth_items 
WHERE item_type_id = 4;

-- Show all ODC items after update
SELECT 
    id,
    name,
    description,
    odc_output_ports,
    odc_capacity,
    odc_ports_used,
    odc_type,
    status
FROM ftth_items 
WHERE item_type_id IN (4, 12)
ORDER BY name;
