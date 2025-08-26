-- Update database untuk menambahkan tabel VLAN
-- Untuk FTTH Network Monitoring System v2.1

-- Tabel untuk menyimpan konfigurasi VLAN Server/Router
CREATE TABLE IF NOT EXISTS server_vlans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_id INT NOT NULL,
    vlan_id INT NOT NULL,
    ip_address VARCHAR(45),
    subnet_mask VARCHAR(15),
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES ftth_items(id) ON DELETE CASCADE,
    UNIQUE KEY unique_item_vlan (item_id, vlan_id)
);

-- Index untuk performa
CREATE INDEX idx_server_vlans_item ON server_vlans(item_id);
CREATE INDEX idx_server_vlans_vlan_id ON server_vlans(vlan_id);

-- Contoh data untuk testing (opsional)
-- INSERT INTO server_vlans (item_id, vlan_id, ip_address, subnet_mask, description) VALUES
-- (1, 100, '192.168.100.1', '255.255.255.0', 'Management VLAN'),
-- (1, 200, '192.168.200.1', '255.255.255.0', 'User VLAN');

-- Update existing ftth_items untuk menambahkan field vlan_config
ALTER TABLE ftth_items 
ADD COLUMN vlan_config JSON NULL COMMENT 'JSON configuration for Server VLANs' 
AFTER monitoring_status;

-- Memberikan komentar pada perubahan
-- Schema version: 2.1.1 - Server VLAN Support Added
