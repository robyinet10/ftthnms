# 📡 **FTTH schematic NETWORK MANAGEMENT SYSTEM - Tutorial Lengkap**

**Panduan Komprehensif Instalasi dan Penggunaan Aplikasi FTTH schematic NMS**

---

## 📋 **Daftar Isi**

1. [Tentang Aplikasi](#tentang-aplikasi)
2. [Instalasi Windows (XAMPP)](#instalasi-windows-xampp)
3. [Instalasi Debian/Ubuntu](#instalasi-debianubuntu)
4. [Instalasi di Hosting](#instalasi-di-hosting)
5. [Konfigurasi Database](#konfigurasi-database)
6. [Authentication & User Management](#authentication--user-management)
7. [Panduan Penggunaan Fitur](#panduan-penggunaan-fitur)
8. [SNMP Monitoring](#snmp-monitoring)
9. [Export/Import KMZ](#exportimport-kmz)
10. [Troubleshooting](#troubleshooting)
11. [FAQ](#faq)

---

## 🎯 **Tentang Aplikasi**

**FTTH schematic NETWORK MANAGEMENT SYSTEM (FTTH schematic NMS)** adalah aplikasi web-based untuk monitoring dan manajemen infrastruktur jaringan FTTH (Fiber to the Home) yang menyediakan:

### **🌟 Fitur Utama:**

#### **📍 Manajemen Item FTTH Lengkap (11 Kategori):**
- **🖥️ Server/Router** - Core network equipment
- **🏢 OLT (Optical Line Terminal)** - PON head-end equipment
- **📡 Tiang Tumpu** - Infrastructure poles
- **🔗 Tiang ODP** - Optical Distribution Point
- **🌐 Tiang ODC** - Optical Distribution Cabinet (Pole Mounted & Cabinet)
- **🏠 ONT (Optical Network Terminal)** - Customer premises equipment
- **🔌 Tiang Joint Closure** - Fiber optic connections
- **📶 HTB & Access Points** - Additional network devices
- **👥 Pelanggan** - End customers

#### **🗺️ Peta Interaktif Canggih:**
- **Multi-Layer Maps**: OpenStreetMap, Satellite, Terrain, CartoDB
- **Drag & Drop Items**: Real-time position updates
- **Interactive Routing**: Cable routing dengan auto-path following
- **Export/Import KMZ**: Google Earth integration
- **Auto-Generate Tiang Tumpu**: Otomatis generate tiang setiap 30m

#### **🔐 Authentication & Security:**
- **Role-Based Access Control**: Admin (full CRUD) dan Teknisi (read-only)
- **Session Management**: Secure login/logout dengan auto-timeout
- **Password Encryption**: bcrypt hashing untuk keamanan
- **API Protection**: Semua endpoint dilindungi authentication

#### **📊 SNMP Monitoring:**
- **Real-time Device Monitoring**: CPU, Memory, Interface status
- **Bandwidth Monitoring**: Traffic in/out dengan grafik
- **Optical Power Levels**: Tx/Rx monitoring untuk fiber
- **Network Topology Detection**: Otomatis detect koneksi antar device
- **Interface Storage**: Persistent interface data di database

#### **💰 Accounting & Pricing:**
- **Item Pricing Management**: Tracking harga per item
- **Financial Reports**: Total investment dan cost analysis
- **Export Data**: Excel, CSV, PowerShell scripts
- **Custom Items**: Tambah item custom untuk inventory

---

## 💻 **Instalasi Windows (XAMPP)**

### **🔧 Requirement System:**
- **OS**: Windows 7/8/10/11 (32-bit & 64-bit)
- **RAM**: Minimum 4GB (Recommended 8GB)
- **Storage**: 2GB free space
- **Browser**: Chrome, Firefox, Edge, Safari
- **Internet**: Required untuk loading maps

### **📦 Step 1: Download & Install XAMPP**

1. **Download XAMPP**
   ```
   URL: https://www.apachefriends.org/
   Pilih: XAMPP untuk Windows dengan PHP 8.0+
   ```

2. **Install XAMPP**
   ```
   - Jalankan installer sebagai Administrator
   - Pilih komponen: Apache, MySQL, PHP, phpMyAdmin
   - Install di C:\xampp (default recommended)
   - Allow firewall access untuk Apache dan MySQL
   ```

3. **Start Services**
   ```
   - Buka XAMPP Control Panel
   - Start Apache (port 80, 443)
   - Start MySQL (port 3306)
   - Pastikan status "Running" berwarna hijau
   ```

### **📂 Step 2: Deploy Aplikasi**

1. **Download Source Code**
   ```
   - Extract file FTTH_schematic_NMS.zip
   - Copy folder ke: C:\xampp\htdocs\ftthnms\
   ```

2. **Verify Structure**
   ```
   C:\xampp\htdocs\ftthnms\
   ├── index.php              # Dashboard utama
   ├── login.php              # Login page
   ├── users.php              # User management
   ├── accounting.php          # Accounting page
   ├── snmp_dashboard.php      # SNMP monitoring
   ├── config/
   │   └── database.php        # Database configuration
   ├── api/                    # REST API endpoints
   │   ├── auth.php           # Authentication
   │   ├── items.php          # CRUD items
   │   ├── routes.php         # Cable routing
   │   ├── snmp.php           # SNMP monitoring
   │   ├── statistics.php     # Statistics
   │   └── users.php          # User management
   ├── assets/
   │   ├── css/               # Stylesheets
   │   └── js/                # JavaScript files
   ├── database.sql           # Database schema
   └── Various guides...
   ```

### **💾 Step 3: Setup Database**

#### **Method A: Via phpMyAdmin (Recommended)**
1. **Buka phpMyAdmin**: `http://localhost/phpmyadmin`
2. **Create Database**:
   - Klik "New" → Database name: `ftthnms`
   - Collation: `utf8mb4_general_ci`
   - Klik "Create"
3. **Import Schema**:
   - Select database `ftthnms`
   - Tab "Import" → Choose file: `database.sql`
   - Klik "Go"

#### **Method B: Via Command Line**
```bash
# Buka Command Prompt di folder aplikasi
cd C:\xampp\mysql\bin
mysql -u root -p
CREATE DATABASE ftthnms CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE ftthnms;
source C:\xampp\htdocs\ftthnms\database.sql;
EXIT;
```

### **⚙️ Step 4: Konfigurasi**

1. **Edit Database Configuration**
   ```php
   # Edit: config/database.php
   
   <?php
   class Database {
       private $host = "localhost";
       private $db_name = "ftthnms";
       private $username = "root";
       private $password = "";    // Kosong untuk XAMPP default
       private $port = "3306";
       
       // ... rest of configuration
   }
   ?>
   ```

2. **Test Installation**
   ```
   1. Buka browser: http://localhost/ftthnms
   2. Akan redirect ke login page
   3. Login dengan:
      Username: admin
      Password: password
   4. Dashboard akan terbuka dengan peta dan statistics
   ```

---

## 🐧 **Instalasi Debian/Ubuntu**

### **🔧 System Requirements:**
- **OS**: Debian 9+ atau Ubuntu 18.04+ LTS
- **RAM**: Minimum 1GB (Recommended 2GB+)
- **Storage**: 5GB free space
- **Network**: Public IP dengan domain/subdomain

### **📦 Step 1: Update System & Install LAMP**

```bash
# Update system packages
sudo apt update && sudo apt upgrade -y

# Install required packages
sudo apt install curl wget unzip git -y

# Install Apache web server
sudo apt install apache2 -y
sudo systemctl start apache2
sudo systemctl enable apache2

# Install MySQL/MariaDB
sudo apt install mariadb-server -y
sudo systemctl start mariadb
sudo systemctl enable mariadb
sudo mysql_secure_installation

# Install PHP 8.0+
sudo apt install software-properties-common -y
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

sudo apt install php8.1 php8.1-mysql php8.1-curl php8.1-json \
php8.1-mbstring php8.1-xml php8.1-zip php8.1-gd php8.1-snmp \
libapache2-mod-php8.1 -y

# Enable Apache modules
sudo a2enmod rewrite
sudo a2enmod ssl
sudo systemctl restart apache2
```

### **🗄️ Step 2: Database Setup**

```bash
# Login to MySQL
sudo mysql -u root -p

# Create database and user
CREATE DATABASE ftthnms CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE USER 'ftthnms_user'@'localhost' IDENTIFIED BY 'secure_password_here';
GRANT ALL PRIVILEGES ON ftthnms.* TO 'ftthnms_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### **📂 Step 3: Deploy Application**

```bash
# Navigate to web directory
cd /var/www/html

# Download atau upload source code
# Via Git (jika repository tersedia):
sudo git clone https://github.com/your-repo/ftthnms.git

# Via upload (jika dari file):
sudo mkdir ftthnms
# Upload semua file aplikasi ke /var/www/html/ftthnms/

# Set permissions
sudo chown -R www-data:www-data /var/www/html/ftthnms/
sudo chmod -R 755 /var/www/html/ftthnms/
sudo chmod -R 777 /var/www/html/ftthnms/assets/
```

### **⚙️ Step 4: Configure Database**

```bash
# Edit database configuration
sudo nano /var/www/html/ftthnms/config/database.php
```

```php
<?php
class Database {
    private $host = "localhost";
    private $db_name = "ftthnms";
    private $username = "ftthnms_user";
    private $password = "secure_password_here";
    private $port = "3306";
    
    // ... rest of configuration
}
?>
```

### **🌐 Step 5: Apache Virtual Host**

```bash
# Create virtual host configuration
sudo nano /etc/apache2/sites-available/ftthnms.conf
```

```apache
<VirtualHost *:80>
    ServerAdmin admin@yourdomain.com
    ServerName yourdomain.com
    ServerAlias www.yourdomain.com
    DocumentRoot /var/www/html/ftthnms
    
    <Directory /var/www/html/ftthnms>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    # SNMP monitoring requires these
    <Directory /var/www/html/ftthnms/api>
        Options -Indexes
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/ftthnms_error.log
    CustomLog ${APACHE_LOG_DIR}/ftthnms_access.log combined
</VirtualHost>
```

```bash
# Enable site and restart Apache
sudo a2ensite ftthnms.conf
sudo a2dissite 000-default.conf
sudo systemctl restart apache2
```

### **🔒 Step 6: SSL Certificate (Recommended)**

```bash
# Install Certbot for Let's Encrypt
sudo apt install certbot python3-certbot-apache -y

# Get SSL certificate
sudo certbot --apache -d yourdomain.com -d www.yourdomain.com

# Auto-renewal setup
sudo crontab -e
# Add line: 0 12 * * * /usr/bin/certbot renew --quiet
```

### **💾 Step 7: Import Database**

```bash
# Import database schema
mysql -u ftthnms_user -p ftthnms < /var/www/html/ftthnms/database.sql

# Verify installation
mysql -u ftthnms_user -p -e "USE ftthnms; SHOW TABLES;"
```

### **🔥 Step 8: PHP & System Optimization**

```bash
# Edit PHP configuration
sudo nano /etc/php/8.1/apache2/php.ini
```

```ini
# Recommended settings for FTTHNMS
memory_limit = 256M
upload_max_filesize = 50M
post_max_size = 50M
max_execution_time = 300
max_input_vars = 3000

# For SNMP monitoring
extension=snmp
snmp.cache_dir = "/tmp"
```

```bash
# Install SNMP tools
sudo apt install snmp snmp-mibs-downloader -y

# Configure firewall
sudo ufw allow OpenSSH
sudo ufw allow 'Apache Full'
sudo ufw enable

# Restart services
sudo systemctl restart apache2
sudo systemctl restart mariadb
```

---

## ☁️ **Instalasi di Hosting**

### **🎯 Shared Hosting Requirements:**
- **PHP**: 8.0+ dengan extensions (PDO, JSON, SNMP, GD)
- **MySQL**: 5.7+ atau MariaDB 10.2+
- **Storage**: Minimum 500MB
- **Memory**: 256MB+ PHP memory limit
- **Execution Time**: 300+ seconds

### **📂 Step 1: Upload Files**

1. **Via cPanel File Manager:**
   ```
   1. Login to cPanel
   2. Open File Manager
   3. Navigate to public_html/
   4. Create folder: ftthnms/
   5. Upload semua file aplikasi
   6. Extract jika dalam format zip
   ```

2. **Via FTP:**
   ```
   FTP Client: FileZilla, WinSCP, etc.
   Upload semua file ke: /public_html/ftthnms/
   Set permissions: 755 untuk folders, 644 untuk files
   ```

### **🗄️ Step 2: Database Setup**

1. **Via cPanel MySQL Wizard:**
   ```
   1. cPanel → MySQL Database Wizard
   2. Database name: yourusername_ftthnms
   3. Create database user
   4. Assign user to database dengan All Privileges
   5. Note down database details
   ```

2. **Import Database:**
   ```
   1. cPanel → phpMyAdmin
   2. Select database
   3. Import → Choose database.sql
   4. Execute import
   ```

### **⚙️ Step 3: Configuration**

```php
# Edit: config/database.php

<?php
class Database {
    private $host = "localhost";  // atau IP server hosting
    private $db_name = "yourusername_ftthnms";
    private $username = "yourusername_dbuser";
    private $password = "your_db_password";
    private $port = "3306";
    
    // ... rest of configuration
}
?>
```

### **🌐 Step 4: Domain Setup**

1. **Subdomain Setup:**
   ```
   1. cPanel → Subdomains
   2. Create: ftthnms.yourdomain.com
   3. Document Root: public_html/ftthnms
   ```

2. **Add-on Domain:**
   ```
   1. cPanel → Addon Domains
   2. New Domain: ftthnms-yourdomain.com
   3. Document Root: public_html/ftthnms
   ```

### **🔒 Step 5: Security Setup**

1. **File Permissions:**
   ```bash
   # Via cPanel File Manager atau FTP
   Folders: 755
   Files: 644
   config/: 750
   config/database.php: 640
   ```

2. **.htaccess Security:**
   ```apache
   # Create: /public_html/ftthnms/.htaccess
   
   # Disable directory browsing
   Options -Indexes
   
   # Protect config directory
   <Files "config/*">
       Deny from all
   </Files>
   
   # Protect database files
   <Files "*.sql">
       Deny from all
   </Files>
   
   # Enable mod_rewrite
   RewriteEngine On
   
   # Force HTTPS (optional)
   RewriteCond %{HTTPS} off
   RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   ```

### **🎯 Step 6: Test Installation**

```
1. Buka browser: https://ftthnms.yourdomain.com
2. Akan redirect ke login page
3. Login dengan default credentials:
   Username: admin
   Password: password
4. Verify semua fitur berfungsi
```

---

## 🗄️ **Konfigurasi Database**

### **📊 Database Schema Overview:**

#### **Core Tables:**
- **`ftth_items`** - Data infrastruktur FTTH (items, coordinates, properties)
- **`cable_routes`** - Routing kabel antar items dengan coordinates
- **`users`** - User accounts dan roles (admin/teknisi)
- **`item_types`** - Jenis-jenis item (11 categories dengan icons)
- **`monitoring_logs`** - Log monitoring ping status

#### **Master Data Tables:**
- **`tube_colors`** - 32 warna tube dengan hex codes
- **`splitter_types`** - Jenis splitter (main & ODP) dengan ratio
- **`server_vlans`** - Konfigurasi VLAN untuk Server/Router
- **`olt_pons`** - Konfigurasi PON ports untuk OLT
- **`pon_vlans`** - Relasi PON-VLAN mapping

#### **SNMP Monitoring Tables:**
- **`snmp_metrics`** - Real-time SNMP monitoring data
- **`snmp_oid_mapping`** - OID mapping per device type
- **`device_interfaces`** - Persistent interface information
- **`interface_ip_addresses`** - IP addresses per interface
- **`interface_vlans`** - VLAN configuration per interface
- **`interface_wireless`** - Wireless interface information
- **`interface_bridges`** - Bridge interface information
- **`interface_tunnels`** - Tunnel interface information
- **`network_topology`** - Device connection mapping
- **`interface_traffic_history`** - Traffic history untuk trend analysis

#### **Enhanced ODC Management Tables:**
- **`odc_ports`** - ODC port management (input/output ports)
- **`odc_pon_mapping`** - ODC-PON connection mapping dengan VLAN

#### **Database Views:**
- **`latest_snmp_metrics`** - Latest SNMP metrics per device
- **`interface_summary`** - Interface summary dengan IP addresses
- **`topology_view`** - Network topology yang readable

### **🔧 Database Optimization:**

```sql
-- Performance Indexes (dari database.sql)
-- Core Item Indexes
CREATE INDEX idx_core_color ON ftth_items(core_color_id);
CREATE INDEX idx_cable_type ON ftth_items(item_cable_type);
CREATE INDEX idx_core_usage ON ftth_items(core_used, total_core_capacity);
CREATE INDEX idx_monitoring_status ON ftth_items(monitoring_status);
CREATE INDEX idx_ip_address ON ftth_items(ip_address);
CREATE INDEX idx_item_type ON ftth_items(item_type);
CREATE INDEX idx_item_price ON ftth_items(item_price);
CREATE INDEX idx_snmp_enabled ON ftth_items(snmp_enabled);
CREATE INDEX idx_snmp_status ON ftth_items(snmp_enabled, monitoring_status);

-- ODC Enhancement Indexes
CREATE INDEX idx_odc_type ON ftth_items(odc_type);
CREATE INDEX idx_odc_capacity ON ftth_items(odc_capacity);
CREATE INDEX idx_odc_pon_connection ON ftth_items(odc_pon_connection);

-- User & Monitoring Indexes
CREATE INDEX idx_monitoring_logs_item ON monitoring_logs(item_id, ping_time);
CREATE INDEX idx_username ON users(username);
CREATE INDEX idx_user_role ON users(role);

-- SNMP Interface Indexes
CREATE INDEX idx_device_interfaces_device ON device_interfaces(device_id);
CREATE INDEX idx_device_interfaces_status ON device_interfaces(oper_status, admin_status);
CREATE INDEX idx_interface_ips_interface ON interface_ip_addresses(interface_id);
CREATE INDEX idx_topology_source ON network_topology(source_device_id, source_interface_id);
CREATE INDEX idx_topology_active ON network_topology(is_active, verified, last_seen);

-- Traffic History Index
CREATE INDEX idx_traffic_history_interface_time ON interface_traffic_history(interface_id, sample_time);

-- Latest SNMP Metrics View
CREATE VIEW latest_snmp_metrics AS
SELECT sm1.*
FROM snmp_metrics sm1
INNER JOIN (
    SELECT item_id, MAX(metric_time) as max_time
    FROM snmp_metrics GROUP BY item_id
) sm2 ON sm1.item_id = sm2.item_id AND sm1.metric_time = sm2.max_time;
```

### **🔄 Update Scripts:**

FTTH schematic NMS menyediakan multiple database scripts untuk setup dan upgrade:

**💡 Default Data yang Tersedia:**
- **Item Types:** 11 kategori (OLT, Tiang Tumpu, ODP, ODC, ONT, Server, Access Point, dll)
- **Tube Colors:** 32 warna standard dengan hex codes  
- **Splitter Types:** Main (1:2, 1:3, 1:4) dan ODP (1:2, 1:4, 1:8, 1:16)
- **SNMP OID Mapping:** Universal, Server, OLT, Access Point, ONT OIDs
- **Default Users:** admin, teknisi1, teknisi2, supervisor (password: "password")

```sql
-- Database Scripts Available:
database.sql                            -- 🎯 Main Schema - Complete database schema
update_database.sql                     -- Main update script untuk upgrade existing
update_database_users.sql               -- Add authentication system (admin/teknisi roles) 
update_database_odc_enhancement.sql     -- ODC enhancements (pole/cabinet, ports, PON mapping)
update_database_pon_integration.sql     -- PON integration dengan VLAN configuration
snmp_integration_upgrade.sql            -- SNMP monitoring upgrade dengan interface discovery
update_database_auto_generate_tiang_tumpu.sql -- Auto-generate features untuk routing
```

---

## 🔐 **Authentication & User Management**

### **👥 User Roles & Permissions:**

#### **Role: Admin**
- ✅ **Full CRUD Access**: Create, Read, Update, Delete semua items
- ✅ **User Management**: Kelola user accounts
- ✅ **SNMP Configuration**: Setup dan monitoring SNMP devices
- ✅ **Accounting Access**: View financial reports dan pricing
- ✅ **System Configuration**: Semua system settings

#### **Role: Teknisi**
- ✅ **Read-Only Access**: View semua data dan maps
- ✅ **Export Data**: Export KMZ, reports
- ❌ **No Create/Edit/Delete**: Tidak bisa modify data
- ❌ **No User Management**: Tidak bisa kelola users
- ❌ **No System Config**: Tidak bisa ubah settings

### **🔒 Default User Accounts:**

| Username | Password | Role | Full Name |
|----------|----------|------|-----------|
| admin | password | admin | Administrator System |
| teknisi1 | password | teknisi | Teknisi Lapangan 1 |
| teknisi2 | password | teknisi | Teknisi Lapangan 2 |
| supervisor | password | admin | Supervisor Jaringan |

### **⚙️ User Management Operations:**

#### **Adding New Users:**
1. **Login sebagai Admin**
2. **Navigate**: Dashboard → User Management
3. **Add User**:
   ```
   Username: [unique_username]
   Password: [secure_password]
   Role: admin / teknisi
   Full Name: [User Display Name]
   Email: [user@email.com]
   Status: active
   ```

#### **Password Security:**
- **Hashing**: bcrypt dengan salt
- **Minimum Length**: 8 characters (recommended)
- **Change Password**: User bisa change own password
- **Admin Reset**: Admin bisa reset password users

#### **Session Management:**
- **Session Timeout**: 30 menit inactivity
- **Warning Alert**: 5 menit before timeout
- **Auto-logout**: Automatic logout setelah timeout
- **Remember Login**: Optional remember untuk 7 hari

---

## 🎛️ **Panduan Penggunaan Fitur**

### **📍 Manajemen Item FTTH**

#### **Menambah Item Baru:**

**Method 1: Via Sidebar Menu**
```
1. Login ke system
2. Sidebar → "Tambah [Item Type]" (e.g., Tambah OLT)
3. Form modal akan terbuka
4. Isi semua field required:
   - Name: Nama item
   - Description: Deskripsi detail
   - Address: Alamat lokasi
   - Klik di peta untuk set koordinat
5. Isi field specific sesuai item type
6. Klik "Simpan"
```

**Method 2: Via Map Click**
```
1. Right-click di peta pada lokasi yang diinginkan
2. Pilih "Add Item Here"
3. Select item type dari dropdown
4. Form akan ter-fill coordinates otomatis
5. Lengkapi data lainnya
6. Simpan
```

#### **Item Types & Specific Fields:**

**🖥️ Server/Router:**
- Management Info: IP address, port, username, password
- SNMP Config: Version, community, authentication
- Interface Monitoring: Network interfaces

**🏢 OLT (Optical Line Terminal):**
- PON Configuration: 3 PON ports per OLT
- VLAN Settings: VLAN 100, 200, 300
- Upstream Server: Connection ke backbone
- SNMP Monitoring: Interface status, optical power

**📡 Tiang Tumpu:**
- Installation Type: Pole, ground, wall mounted
- Height: Tinggi tiang dalam meter
- Material: Concrete, steel, wood
- Auto-generate: Otomatis generate setiap 30m pada routing

**🌐 ODC (Optical Distribution Cabinet):**
- Type: Pole Mounted vs Ground Mounted (Cabinet)
- Capacity Planning: Berdasarkan splitter ratio
- PON Connection: Link ke OLT PON ports
- Port Management: Input/output ports tracking

**🔗 ODP (Optical Distribution Point):**
- Splitter Configuration: 1:8, 1:16, 1:32
- Service Area: Coverage area radius
- Customer Capacity: Max customers per ODP

**🏠 ONT (Optical Network Terminal):**
- Customer Info: Nama pelanggan, kontak
- Service Plan: Bandwidth package
- HTB Configuration: Traffic shaping
- Connection Status: Online/offline monitoring

#### **Edit & Update Items:**
```
1. Klik item marker di peta
2. Popup detail akan muncul
3. Klik tombol "Edit Item"
4. Form akan pre-filled dengan data existing
5. Update field yang diinginkan
6. Klik "Update" untuk simpan changes
```

#### **Drag & Drop Movement:**
```
1. Klik dan drag marker ke posisi baru
2. System otomatis update coordinates
3. Notifikasi konfirmasi akan muncul
4. Data tersimpan real-time ke database
```

### **🛣️ Routing Kabel**

#### **Membuat Route Baru:**

**Method 1: Via Item Popup**
```
1. Klik item source di peta
2. Klik "Route Jalan" atau "Route Garis Lurus"
3. Modal routing options akan muncul:
   - Auto Generate Tiang Tumpu: [checkbox]
   - Interval Jarak: 30 meter [slider: 10-100m]
   - Generate di Tikungan: [checkbox]
4. Klik "Simpan Pengaturan"
5. Klik item destination
6. Route otomatis terbuat dengan specifications
```

**Method 2: Via Mode Routing**
```
1. Klik tombol "Mode Routing" di header
2. Mode routing akan aktif (cursor berubah)
3. Klik item source (starting point)
4. Klik item destination (ending point)
5. Route terbuat dengan settings default
```

#### **Route Types:**

**🛣️ Route Jalan (Road Following):**
- Menggunakan OSRM routing engine
- Mengikuti jalan yang ada
- Coordinate points mengikuti path jalan
- Ideal untuk backbone dan distribution cables

**➖ Route Garis Lurus (Straight Line):**
- Point-to-point direct connection
- Minimal coordinate points
- Ideal untuk short distances
- Faster calculation

#### **Route Properties:**
- **Cable Type**: Fiber Optic, Copper, Hybrid
- **Core Count**: 2, 4, 8, 12, 24, 48, 96, 144, 288 cores
- **Status**: Planned (kuning), Installed (hijau), Maintenance (merah)
- **Distance**: Otomatis calculated dalam meter
- **Auto-Generated Tiang**: Track generated infrastructure

#### **Route Management:**
```
1. View Routes: Sidebar → "Routing Kabel"
2. Route List Modal akan terbuka dengan:
   - Table semua routes
   - Filter by status, cable type
   - Bulk operations (multiple delete)
   - Individual actions (edit, focus, delete)
3. Edit Route: Klik route line → Edit properties
4. Delete Route: Select routes → "Hapus Terpilih"
```

### **🎨 Auto-Generate Tiang Tumpu**

#### **Configuration:**
```
Setting otomatis generate tiang tumpu:
1. Enable checkbox: "Auto Generate Tiang Tumpu"
2. Set interval: 10-100 meter (default: 30m)
3. Enable "Generate di Tikungan" untuk turns
4. Generate otomatis saat create route
```

#### **Generated Tiang Properties:**
- **Naming**: "Tiang Tumpu Auto" atau "Tiang Tumpu Tikungan"
- **Type**: Marked as auto-generated
- **Behavior**: Display-only, tidak bisa diedit
- **Tracking**: Linked ke route yang generate
- **Cleanup**: Auto-delete saat route dihapus

### **📊 Dashboard & Statistics**

#### **Horizontal Statistics Cards:**
Layout 1 row dengan 10 compact cards:
```
[Server][OLT][Tiang][ODP][ONT][Routes][Joint][HTB][Access][Total Items]
```

**Real-time Updates:**
- Statistics update otomatis saat data berubah
- Color-coded berdasarkan item types
- Click card untuk quick navigation

#### **Statistics Detail:**
- **Total Items**: Jumlah total semua item
- **Per Type Count**: Breakdown per kategori
- **Auto-Generated**: Track auto-generated items
- **Route Statistics**: Total routes, distance
- **Investment Summary**: Total harga (di Accounting page)

---

## 📡 **SNMP Monitoring**

### **🔧 SNMP Configuration**

#### **Supported Devices:**
- **OLT**: Optical Line Terminal monitoring
- **Server/Router**: Network equipment monitoring
- **ONT**: Customer premises equipment
- **Access Point**: Wireless equipment

#### **SNMP Versions:**
- **SNMPv1**: Community-based, less secure
- **SNMPv2c**: Community-based, more features
- **SNMPv3**: User-based security (recommended)

#### **Configuration Fields:**
```
SNMP Settings:
├── SNMP Enabled: [checkbox]
├── Version: v1/v2c/v3 [dropdown]
├── Community: public/private [text]
├── Port: 161 [number]
├── Username: [text] (v3 only)
├── Auth Protocol: MD5/SHA [dropdown] (v3 only)
├── Auth Password: [password] (v3 only)
├── Priv Protocol: DES/AES [dropdown] (v3 only)
└── Priv Password: [password] (v3 only)
```

### **📊 Monitoring Capabilities**

#### **Real-time Metrics:**
- **System Info**: Hostname, uptime, description
- **CPU Utilization**: Processor usage percentage
- **Memory Usage**: RAM utilization
- **Interface Status**: Up/down, admin/operational status
- **Bandwidth Utilization**: In/out traffic dengan grafik

#### **Interface Monitoring:**
- **Interface Discovery**: Auto-discover network interfaces
- **Persistent Storage**: Interface data disimpan di database
- **IP Address Mapping**: Track IP per interface
- **Topology Detection**: Auto-detect device connections
- **Traffic Monitoring**: Real-time bandwidth usage

#### **Optical Monitoring (for OLT/ONT):**
- **Tx Power**: Transmit optical power (dBm)
- **Rx Power**: Receive optical power (dBm)
- **Signal Quality**: Link quality assessment
- **Fiber Status**: Cable integrity monitoring

### **🎛️ SNMP Dashboard**

#### **Access SNMP Dashboard:**
```
1. Login as Admin
2. Navigate: http://localhost/ftthnms/snmp_dashboard.php
3. atau Sidebar → "SNMP Dashboard"
```

#### **Dashboard Features:**
- **Device Overview**: Summary semua SNMP-enabled devices
- **Real-time Status**: Current status semua devices
- **Interface Summary**: Network interfaces overview
- **Topology Map**: Visual device connections
- **Alert Summary**: Critical issues notification

#### **Interface Monitoring Workflow:**
```
1. Click device marker di peta
2. Click "Discover" button
3. System perform SNMP discovery:
   - Discover all network interfaces
   - Get IP addresses per interface
   - Store data to database
   - Detect topology connections
4. Click "Interfaces" untuk view results:
   - Tab "Stored": Persistent interface data
   - Tab "Real-time": Live traffic monitoring
   - Tab "Topology": Device connections
```

### **🔧 SNMP Troubleshooting**

#### **Common Issues:**

**SNMP Connection Failed:**
```
Causes:
- SNMP service not running on device
- Firewall blocking port 161
- Wrong community string
- Network connectivity issues

Solutions:
1. Check device SNMP configuration
2. Verify network connectivity (ping test)
3. Test SNMP walk manually
4. Check firewall rules
```

**No Interface Data:**
```
Causes:
- SNMP OIDs not supported
- Access permissions limited
- Device-specific MIB requirements

Solutions:
1. Try different SNMP version
2. Check device documentation
3. Use vendor-specific community
4. Test with SNMP tools
```

**Performance Issues:**
```
Optimization:
- Use SNMP v2c for better performance
- Implement SNMP caching
- Limit discovery frequency
- Monitor system resources
```

---

## 📥📤 **Export/Import KMZ**

### **📤 Export KMZ untuk Google Earth**

#### **Export Process:**
```
1. Dashboard → Tombol "Export KMZ" (hijau)
   atau Sidebar → "Export Data" → "Export ke KMZ"
2. System generate KMZ file dengan:
   - Semua FTTH items dengan styling
   - Cable routes dengan color coding
   - Complete information dalam popup
   - Professional GIS formatting
3. File auto-download dengan timestamp:
   Format: "FTTH_Planner_Export_YYYY-MM-DD-HHMMSS.kmz"
```

#### **KMZ Content:**
- **Items**: Semua 11 kategori item dengan marker styling
- **Routes**: Cable routing dengan status-based colors
- **Metadata**: Complete item information
- **Styling**: Professional icons dan colors

#### **Google Earth Viewing:**
```
1. Download file KMZ
2. Open Google Earth Pro atau Google Earth Web
3. File → Open → Select KMZ file
4. Data akan muncul di layer panel
5. Click item untuk view detail popup
6. Use Google Earth tools untuk measurement, etc.
```

### **📥 Import KMZ/KML**

#### **Supported Formats:**
- **KMZ**: Compressed KML files
- **KML**: Standard Google Earth format
- **Geometries**: Point, LineString, Polygon, MultiGeometry

#### **Import Process:**
```
1. Sidebar → "Import Data" → "Import dari KMZ/KML"
2. Upload file browser atau drag & drop
3. System parsing dan validation:
   - Auto-detect format
   - Parse coordinates
   - Validate data integrity
   - Preview import data
4. Review preview:
   - Check items to be imported
   - Verify coordinates
   - Resolve any conflicts
5. Confirm import:
   - Batch processing untuk large files
   - Progress indicator
   - Error reporting jika ada
6. Import complete:
   - Items appear on map
   - Statistics updated
   - Import summary report
```

#### **Data Mapping:**
```
Import Mapping:
├── Point → FTTH Item (auto-detect type)
├── LineString → Cable Route
├── Polygon → Convert to Point (centroid)
├── Name → Item name
├── Description → Item description
└── Coordinates → Latitude/longitude
```

#### **Smart Data Processing:**
- **Coordinate Validation**: Ensure valid lat/lng
- **Duplicate Detection**: Prevent duplicate items
- **Auto Type Detection**: Intelligent item type assignment
- **Batch Processing**: Handle large datasets efficiently
- **Error Recovery**: Robust error handling

### **🌍 Professional GIS Integration**

#### **Compatible Software:**
- **Google Earth Pro**: Desktop application
- **Google Earth Web**: Browser-based
- **QGIS**: Open-source GIS
- **ArcGIS**: Professional GIS
- **AutoCAD Map 3D**: CAD dengan GIS
- **Avenza Maps**: Mobile GIS

#### **Workflow Examples:**

**Survey & Planning:**
```
1. Export current infrastructure ke KMZ
2. Load di Google Earth untuk site survey
3. Plan new installations
4. Export planned items dari Google Earth
5. Import back ke FTTHNMS
6. Execute deployment plan
```

**Data Integration:**
```
1. Export FTTHNMS data
2. Combine dengan:
   - Cadastral data
   - Utility maps
   - Customer data
   - Site surveys
3. Analysis di GIS software
4. Import updated/additional data
```

---

## 🔧 **Troubleshooting**

### **⚠️ Installation Issues**

#### **XAMPP Issues:**

**Apache Won't Start:**
```
Causes:
- Port 80/443 already in use (Skype, IIS)
- Antivirus blocking Apache
- Permission issues

Solutions:
1. Stop conflicting services:
   - Skype: Settings → Advanced → Connection → Uncheck port 80
   - IIS: Control Panel → Programs → Turn off IIS
2. Run XAMPP as Administrator
3. Add XAMPP to antivirus exceptions
4. Change Apache ports if needed (httpd.conf)
```

**MySQL Won't Start:**
```
Causes:
- Port 3306 in use by another MySQL service
- Corrupt MySQL data
- Permission issues

Solutions:
1. Stop other MySQL services
2. Check Windows Services for MySQL
3. Reset MySQL data directory
4. Run XAMPP as Administrator
```

#### **Database Issues:**

**Connection Failed:**
```
Error: "Connection failed: Access denied"

Solutions:
1. Check database credentials in config/database.php
2. Verify MySQL service running
3. Test connection:
   mysql -u root -p
4. Check user permissions:
   SHOW GRANTS FOR 'user'@'localhost';
```

**Import Failed:**
```
Error: "Error importing database"

Solutions:
1. Check SQL file format (UTF-8)
2. Increase PHP limits:
   upload_max_filesize = 50M
   max_execution_time = 300
3. Import via command line:
   mysql -u root -p database_name < file.sql
4. Check for SQL syntax errors
```

### **🌐 Application Issues**

#### **Login Problems:**

**Invalid Credentials:**
```
Solutions:
1. Use default accounts:
   admin/password
   teknisi1/password
2. Reset password manually:
   UPDATE users SET password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' WHERE username = 'admin';
   (Password becomes: password)
3. Check user status:
   SELECT * FROM users WHERE username = 'admin';
```

**Session Expired:**
```
Solutions:
1. Clear browser cookies dan cache
2. Check PHP session configuration
3. Extend session timeout di php.ini:
   session.gc_maxlifetime = 3600
4. Restart web server
```

#### **Map Issues:**

**Map Not Loading:**
```
Causes:
- No internet connection
- JavaScript errors
- Tile server issues

Solutions:
1. Check internet connectivity
2. Open browser console (F12) untuk errors
3. Try different map layer:
   - OpenStreetMap
   - Satellite view
   - CartoDB
4. Clear browser cache
```

**Items Not Appearing:**
```
Causes:
- Database connection issues
- API errors
- Coordinate problems

Solutions:
1. Check browser console untuk API errors
2. Verify database connectivity
3. Check coordinates format:
   Latitude: -90 to 90
   Longitude: -180 to 180
4. Test API directly:
   http://localhost/ftthnms/api/items.php
```

#### **SNMP Issues:**

**SNMP Discovery Failed:**
```
Causes:
- SNMP service disabled on device
- Wrong community string
- Network connectivity
- Firewall blocking

Solutions:
1. Enable SNMP on device
2. Check community string (case-sensitive)
3. Test connectivity: ping device_ip
4. Test SNMP manually:
   snmpwalk -v2c -c public device_ip 1.3.6.1.2.1.1.1.0
5. Check firewall rules
```

**No Interface Data:**
```
Solutions:
1. Try different SNMP version
2. Check device supports standard MIBs
3. Verify SNMP permissions on device
4. Use vendor-specific community strings
```

### **🚀 Performance Issues**

#### **Slow Loading:**

**Database Optimization:**
```sql
-- Add missing indexes
CREATE INDEX idx_coordinates ON ftth_items(latitude, longitude);
CREATE INDEX idx_item_type ON ftth_items(item_type_id);
CREATE INDEX idx_route_items ON cable_routes(from_item_id, to_item_id);

-- Optimize tables
OPTIMIZE TABLE ftth_items;
OPTIMIZE TABLE cable_routes;

-- Check slow queries
SHOW FULL PROCESSLIST;
```

**Server Optimization:**
```
1. Increase PHP memory:
   memory_limit = 256M
2. Enable opcache:
   opcache.enable = 1
   opcache.memory_consumption = 128
3. Enable compression:
   zlib.output_compression = On
4. Browser caching:
   Add Expires headers for static files
```

#### **Large Dataset Handling:**

**For 1000+ Items:**
```
1. Enable pagination untuk item lists
2. Implement map clustering
3. Use lazy loading for details
4. Optimize database queries
5. Consider caching strategies
```

### **🔐 Security Issues**

#### **Unauthorized Access:**

**Secure Installation:**
```
1. Change default passwords
2. Restrict database access:
   - Create dedicated database user
   - Limit permissions
   - Use strong passwords
3. Enable HTTPS:
   - Install SSL certificate
   - Force HTTPS redirects
4. Regular updates:
   - Keep PHP/MySQL updated
   - Update application when available
```

#### **File Permissions:**
```bash
# Secure file permissions
chmod 755 directories
chmod 644 files
chmod 750 config/
chmod 640 config/database.php

# Web server ownership
chown -R www-data:www-data /var/www/html/ftthnms/
```

---

## ❓ **FAQ**

### **💡 General Questions**

**Q: Apa system requirements minimum untuk FTTHNMS?**
A: Windows/Linux dengan PHP 8.0+, MySQL 5.7+, 4GB RAM, dan browser modern dengan JavaScript enabled.

**Q: Apakah bisa diinstall di shared hosting?**
A: Ya, asalkan hosting support PHP 8.0+, MySQL, dan extension yang diperlukan (PDO, JSON, SNMP).

**Q: Berapa banyak item yang bisa dihandle?**
A: System bisa handle 10,000+ items dengan performa optimal jika database dan server dikonfigurasi dengan baik.

**Q: Apakah support mobile access?**
A: Ya, interface responsive dan optimized untuk tablet dan mobile devices.

### **🔧 Technical Questions**

**Q: Bagaimana cara backup data?**
A: 
```sql
-- Database backup
mysqldump -u username -p database_name > backup.sql

-- File backup  
tar -czf ftthnms_backup.tar.gz /path/to/ftthnms/
```

**Q: Bagaimana cara migrate ke server baru?**
A:
```
1. Backup database dan files
2. Install FTTHNMS di server baru
3. Restore database
4. Copy files dan update configuration
5. Test functionality
```

**Q: Apakah bisa integrate dengan system lain?**
A: Ya, aplikasi menyediakan REST API yang bisa diintegrasikan dengan system external.

**Q: Bagaimana cara custom item types?**
A:
```sql
-- Add custom item type
INSERT INTO item_types (name, icon, color) VALUES
('Custom Type', 'fas fa-custom', '#123456');
```

### **🔒 Security Questions**

**Q: Bagaimana cara change password?**
A: Login → Profile → Change Password, atau admin bisa reset password user lain di User Management.

**Q: Apakah data encrypted?**
A: Password di-hash dengan bcrypt, session data protected, dan HTTPS recommended untuk production.

**Q: Bagaimana cara limit access berdasarkan IP?**
A:
```apache
# .htaccess
<RequireAll>
    Require ip 192.168.1.0/24
    Require ip 10.0.0.0/8
</RequireAll>
```

### **📊 SNMP Questions**

**Q: Device mana yang support SNMP monitoring?**
A: OLT, Server/Router, ONT, dan Access Point dengan SNMP enabled.

**Q: SNMP version mana yang paling baik?**
A: SNMPv3 untuk security, SNMPv2c untuk compatibility, hindari SNMPv1.

**Q: Bagaimana cara troubleshoot SNMP issues?**
A: Check connectivity, verify community string, test dengan snmpwalk command, dan check device SNMP configuration.

### **🗺️ Maps & Export Questions**

**Q: Apakah perlu internet untuk maps?**
A: Ya, maps tiles dimuat dari internet. Untuk offline usage, consider caching solutions.

**Q: Format apa yang support untuk import?**
A: KMZ, KML, dan berbagai geometry types (Point, LineString, Polygon).

**Q: Bagaimana cara integrate dengan Google Earth?**
A: Export data ke KMZ format, kemudian open di Google Earth Pro atau Web.

**Q: Bisakah export ke format lain?**
A: Ya, dari Accounting page bisa export ke Excel, CSV, dan PowerShell scripts.

---

## 🎯 **Kesimpulan**

**FTTH schematic NETWORK MANAGEMENT SYSTEM (FTTH schematic NMS)** adalah solusi comprehensive untuk monitoring dan manajemen infrastruktur FTTH dengan fitur:

### **🌟 Key Benefits:**
- ✅ **Complete Infrastructure Management** - 11 kategori item FTTH
- ✅ **Interactive Mapping** - Drag & drop, routing, visualization
- ✅ **SNMP Monitoring** - Real-time device monitoring
- ✅ **Authentication System** - Role-based security
- ✅ **Professional Export** - Google Earth integration
- ✅ **Auto-Generation** - Smart infrastructure planning
- ✅ **Responsive Design** - Works on all devices
- ✅ **Open Source** - Full source code ownership

### **🎯 Perfect For:**
- 🏢 **ISP & Telco Companies** - Network infrastructure planning
- 🏗️ **FTTH Contractors** - Site survey dan installation
- 🏛️ **Government Projects** - Infrastructure development
- 🎓 **Educational Institutions** - Network learning
- 👨‍💼 **Network Consultants** - Professional planning tools

### **🚀 Production Ready:**
- **Enterprise-grade architecture** dengan robust database design
- **Scalable untuk large deployments** (10K+ items)
- **Professional security** dengan encryption dan role-based access
- **Comprehensive documentation** dengan training materials
- **Active support** dan regular updates

---

**🎉 SELAMAT! Anda sekarang memiliki panduan lengkap untuk menggunakan FTTH schematic NETWORK MANAGEMENT SYSTEM. Mulai planning infrastruktur FTTH Anda dengan tools professional ini!**

---

*Last Updated: 2025-01-18*  
*Version: 4.1.0*  
*Documentation: Complete User Guide*
