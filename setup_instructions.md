# Instruksi Setup FTTH Planner

## Langkah 1: Persiapan XAMPP

1. **Download XAMPP**: Unduh dari https://www.apachefriends.org/
2. **Install XAMPP**: Install di `C:\xampp\`
3. **Start Services**: 
   - Buka XAMPP Control Panel
   - Start **Apache** dan **MySQL**

## Langkah 2: Setup Database

1. **Buka phpMyAdmin**: 
   - Browser → `http://localhost/phpmyadmin`
   - Login tanpa password (default)

2. **Buat Database**:
   - Klik "New" di sidebar kiri
   - Nama database: `ftth_planner`
   - Collation: `utf8_general_ci`
   - Klik "Create"

3. **Import Database**:
   - Pilih database `ftth_planner`
   - Tab "Import"
   - Choose file: pilih `database.sql`
   - Klik "Go"

## Langkah 3: Copy File Aplikasi

1. **Copy folder**: Copy semua file ke `C:\xampp\htdocs\ftthplanner\`
2. **Struktur folder harus**:
   ```
   C:\xampp\htdocs\ftthplanner\
   ├── index.php
   ├── config/database.php
   ├── api/ (semua file API)
   ├── assets/ (CSS & JS)
   ├── database.sql
   └── README.md
   ```

## Langkah 4: Test Aplikasi

1. **Buka Browser**: `http://localhost/ftthplanner`
2. **Cek Map**: Pastikan peta OpenStreetMaps muncul
3. **Test Add Item**: Klik di peta → isi form → simpan
4. **Test Drag**: Drag marker yang sudah dibuat
5. **Test Route**: Klik "Mode Routing" → pilih 2 item

## Troubleshooting Umum

### Error: "Connection failed"
- Pastikan MySQL service berjalan di XAMPP
- Cek username/password di `config/database.php`

### Error: "Table doesn't exist"
- Import ulang `database.sql`
- Pastikan database name = `ftth_planner`

### Map tidak muncul
- Cek koneksi internet
- Allow JavaScript di browser
- Cek console browser (F12) untuk error

### Item tidak bisa disimpan
- Cek permission folder (777 di Linux/Mac)
- Pastikan semua field required diisi
- Lihat Network tab di browser untuk error API

## Konfigurasi Lanjutan

### Ganti Default Location Map
Edit di `assets/js/map.js` baris 11:
```javascript
map = L.map('map').setView([-6.2088, 106.8456], 11);
```
Ganti koordinat dengan lokasi yang diinginkan.

### Tambah Warna Tube
Insert ke database:
```sql
INSERT INTO tube_colors (color_name, hex_code) VALUES ('Nama Warna', '#HEX_CODE');
```

### Tambah Jenis Splitter
Insert ke database:
```sql
INSERT INTO splitter_types (type, ratio) VALUES ('main', '1:6');
```

## Kontak Support

Jika mengalami masalah, dokumentasikan:
1. Versi Windows
2. Versi XAMPP
3. Error message lengkap
4. Screenshot jika perlu

Aplikasi sudah siap digunakan! 🎉