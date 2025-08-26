# Changelog - Perubahan Nama Aplikasi

## 📋 Ringkasan Perubahan

**Tanggal:** 2025-01-27  
**Versi:** 5.1.1  
**Perubahan Utama:** Perubahan nama aplikasi dari "FTTH Network Monitoring System" menjadi "FTTH Schematic Network Management System"

## 🔄 Perubahan yang Dilakukan

### 1. **Judul Aplikasi**
- ✅ **index.php**: Title tag diubah menjadi "FTTH Schematic Network Management System | Dashboard"
- ✅ **accounting.php**: Title tag diubah menjadi "FTTH Schematic Network Management System - Accounting"  
- ✅ **login.php**: Title tag diubah menjadi "FTTH Schematic Network Management System | Login"

### 2. **Preloader Text**
- ✅ **index.php**: Text preloader diubah menjadi "FTTH Schematic Network Management System"

### 3. **Footer Copyright**
- ✅ **index.php**: Footer copyright diubah menjadi:
  ```
  Copyright © 2025 FTTH Schematic Network Management System by Saputra Budi. 
  Semua hak dilindungi undang-undang.
  ```
- ✅ **accounting.php**: Footer copyright diubah menjadi:
  ```
  Copyright © 2025 FTTH Schematic Network Management System by Saputra Budi. 
  Semua hak dilindungi undang-undang.
  ```
- ✅ **users.php**: Footer copyright diubah menjadi:
  ```
  Copyright © 2025 FTTH Schematic Network Management System by Saputra Budi. 
  Semua hak dilindungi undang-undang.
  ```

### 4. **Copyright Popup Function**
- ✅ **index.php**: Ditambahkan fungsi `showCopyrightPopup()` untuk menampilkan modal PDF
- ✅ **accounting.php**: Ditambahkan fungsi `showCopyrightPopup()` untuk menampilkan modal PDF
- ✅ **users.php**: Ditambahkan fungsi `showCopyrightPopup()` untuk menampilkan modal PDF
- ✅ **Fitur Popup**: Ketika mengklik "FTTH Schematic Network Management System" di footer, akan muncul popup dengan link ke file `SuratCiptaan_SaputraBudi.pdf`

### 5. **Console Log**
- ✅ **index.php**: Console log diubah menjadi "FTTH Schematic Network Management System"

## 🎯 Fitur Copyright Popup

### Cara Kerja:
1. User mengklik link "FTTH Schematic Network Management System" di footer
2. Muncul modal popup dengan informasi surat ciptaan
3. Modal berisi tombol untuk membuka file PDF `SuratCiptaan_SaputraBudi.pdf`
4. PDF akan dibuka dalam tab baru browser

### Struktur Modal:
```html
<div class="modal fade" id="copyrightModal">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5>Surat Ciptaan - FTTH Schematic Network Management System</h5>
      </div>
      <div class="modal-body">
        <p>Surat Ciptaan FTTH Schematic Network Management System</p>
        <p>oleh Saputra Budi</p>
        <a href="SuratCiptaan_SaputraBudi.pdf" target="_blank">
          Buka PDF Surat Ciptaan
        </a>
      </div>
    </div>
  </div>
</div>
```

## 📁 File yang Diubah

1. **index.php** - Judul, preloader, footer, dan fungsi popup
2. **accounting.php** - Judul, footer, dan fungsi popup  
3. **users.php** - Footer dan fungsi popup
4. **login.php** - Judul aplikasi

## 🔗 File PDF

- **SuratCiptaan_SaputraBudi.pdf** - File surat ciptaan yang akan dibuka saat mengklik link di footer

## ✅ Status Implementasi

- [x] Perubahan judul aplikasi di semua halaman
- [x] Update footer copyright dengan teks lengkap
- [x] Implementasi fungsi popup copyright
- [x] Link ke file PDF surat ciptaan
- [x] Update console log
- [x] Konsistensi nama di seluruh aplikasi

## 🎉 Hasil Akhir

Aplikasi sekarang menggunakan nama **"FTTH Schematic Network Management System"** secara konsisten di seluruh interface, dengan fitur copyright popup yang menampilkan surat ciptaan resmi dalam format PDF.
