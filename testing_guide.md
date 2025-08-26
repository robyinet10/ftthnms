# Panduan Testing FTTH Planner

## ✅ Masalah yang Telah Diperbaiki

### 1. **Item Pelanggan/End User**
- ✅ Tambah item type "Pelanggan" dengan icon rumah (🏠)
- ✅ Warna orange (#FFA500) untuk pembeda
- ✅ Menu sidebar "Tambah Pelanggan"
- ✅ Form dapat memilih "Pelanggan" 
- ✅ Statistik menampilkan jumlah pelanggan

### 2. **Routing Cable (From-To)**
- ✅ Perbaiki bug routing yang tidak berfungsi
- ✅ Tambah error handling dan logging
- ✅ Fallback ke garis lurus jika routing gagal
- ✅ Console log untuk debugging

## 🔧 Cara Setup & Update

### Jika Database Sudah Ada
Jalankan file `update_database.sql` di phpMyAdmin untuk menambah item type Pelanggan:
```sql
INSERT IGNORE INTO item_types (id, name, icon, color) VALUES (5, 'Pelanggan', 'fas fa-home', '#FFA500');
```

### Jika Database Baru
Import file `database.sql` yang sudah diupdate dengan item Pelanggan.

## 🧪 Testing Checklist

### Test 1: Item Pelanggan
1. **Buka aplikasi**: `http://localhost/ftthplanner`
2. **Cek sidebar**: Menu "Tambah Pelanggan" ada dengan icon 🏠
3. **Klik "Tambah Pelanggan"**:
   - Form modal terbuka
   - Item Type otomatis terisi "Pelanggan"
   - Isi nama: "Rumah Pak Budi"
   - Klik di peta untuk set lokasi
   - Klik "Simpan"
4. **Verifikasi**:
   - Marker orange dengan icon rumah muncul di peta
   - Statistik "Pelanggan" bertambah
   - Popup info benar saat diklik marker

### Test 2: Routing Cable
1. **Buat minimal 2 item** (misal: 1 OLT + 1 Pelanggan)
2. **Klik tombol "Mode Routing"** di header card
3. **Klik marker pertama** (misal OLT)
4. **Klik marker kedua** (misal Pelanggan)
5. **Verifikasi**:
   - Garis route muncul (kuning putus-putus)
   - Notifikasi "Route berhasil dibuat"
   - Statistik "Routes" bertambah

### Test 3: Routing Alternative
Jika routing normal gagal, akan otomatis fallback ke garis lurus:
1. **Buka Console Browser** (F12)
2. **Coba buat route**
3. **Cek console log**:
   - "Creating route from [lat,lng] to [lat,lng]"
   - Jika ada error: "Leaflet Routing Machine not available"
   - "Route sederhana berhasil dibuat"

### Test 4: Drag & Drop
1. **Drag marker** yang sudah dibuat ke lokasi baru
2. **Verifikasi**: Notifikasi "Posisi item berhasil dipindahkan"

### Test 5: Edit/Delete
1. **Klik marker → Edit**: Form terisi data lama
2. **Update** dan simpan: Data terupdate
3. **Klik marker → Hapus**: Item terhapus dari peta

## 🐛 Troubleshooting

### Routing Tidak Berfungsi
**Gejala**: Tidak ada garis route muncul setelah klik 2 marker

**Solusi**:
1. **Cek Console** (F12): 
   - Error loading Leaflet Routing Machine?
   - Network error ke OSRM service?
2. **Cek Internet**: Routing butuh koneksi untuk akses map service
3. **Fallback**: Sistem otomatis buat garis lurus jika routing gagal

**Test Manual**:
```javascript
// Di console browser
console.log(typeof L.Routing); // Should show 'object'
console.log(markers); // Should show object with marker IDs
```

### Item Pelanggan Tidak Muncul
**Solusi**:
1. **Cek Database**: Pastikan item_types id=5 ada
2. **Hard Refresh**: Ctrl+F5 untuk reload cache
3. **Cek Console**: Error JavaScript?

### Statistik Tidak Update
**Gejala**: Angka statistik tidak berubah setelah tambah item

**Solusi**:
1. **Refresh halaman**: F5
2. **Cek API**: `http://localhost/ftthplanner/api/statistics.php`
3. **Cek Database**: Data tersimpan di tabel ftth_items?

## 📊 Expected Results

### Statistik Dashboard
- **OLT**: Jumlah OLT yang dibuat
- **Tiang**: Jumlah Tiang Tumpu
- **ODP**: Jumlah ODP
- **ODC**: Jumlah ODC  
- **Pelanggan**: Jumlah Pelanggan ⭐ (BARU)
- **Routes**: Jumlah route cable ⭐ (BARU)

### Legend Map
Harus menampilkan 5 item:
- 🔴 OLT
- 🔵 Tiang Tumpu  
- 🔵 ODP
- 🟢 ODC
- 🟠 Pelanggan ⭐ (BARU)

### Routing Visual
- **Garis hijau solid**: Route terpasang
- **Garis kuning putus**: Route perencanaan ⭐
- **Garis merah putus**: Route maintenance

## ✅ Success Criteria

**✅ BERHASIL** jika:
1. Item Pelanggan bisa dibuat dan muncul di peta
2. Routing berfungsi (garis muncul antara 2 marker)
3. Statistik update otomatis
4. Drag & drop masih berfungsi
5. Edit/delete masih berfungsi

**❌ GAGAL** jika:
1. Error JavaScript di console
2. Marker tidak muncul setelah simpan
3. Routing sama sekali tidak ada response
4. Database error di API calls

Silakan test sesuai checklist di atas dan laporkan hasil atau error yang ditemukan! 🚀