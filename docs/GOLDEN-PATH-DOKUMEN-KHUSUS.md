# Golden Path — Dokumen Khusus (Tahap 4)

## Persiapan

1. Jalankan migrasi:
   ```bash
   php artisan migrate
   ```
2. Jalankan dev server:
   ```bash
   npm run dev
   ```
3. Login sebagai **staf/petugas di Seksi Pemerintahan** (atau admin).

---

## 1. Buat Dokumen Khusus (Surat Tanah)

1. Buka **Arsip** → klik **Tambah Dokumen**.
2. Di form, pilih:
   - **Klasifikasi Dokumen**: `Surat Tanah` atau `Ahli Waris`
   - **Seksi**: pastikan tetap Seksi Pemerintahan
3. Panel **Data Profiling** muncul — isi:
   - Nama Pemilik (wajib)
   - NIK Pemilik
   - Alamat / Lokasi Objek
   - Nomor Alas Hak / Surat
   - Keterangan Tambahan
4. Upload lampiran — hanya file **PDF** yang diterima (bukan jpg/png).
5. Klik **Simpan**.
6. **Yang harus terjadi**: redirect ke daftar arsip, dokumen muncul dengan badge `Surat Tanah` / `Ahli Waris`.

---

## 2. Guard Seksi Non-Pemerintahan

1. Buka **Tambah Dokumen** lagi.
2. Ganti **Seksi** ke Ekonomi Pembangunan atau Kesejahteraan Rakyat.
3. Coba pilih Klasifikasi `Surat Tanah` atau `Ahli Waris`.
4. **Yang harus terjadi**: field klasifikasi otomatis reset ke `Umum`, panel profiling hilang.
5. Jika tetap coba submit dengan `jenis_dokumen=surat_tanah` di seksi lain (lewat browser dev tools), backend memaksa merge ke `umum` — dokumen tersimpan sebagai jenis umum.

---

## 3. Filter & Pencarian di Daftar Arsip

1. Buka **Arsip** (halaman daftar).
2. Gunakan dropdown filter baru:
   - **Jenis Dokumen**: pilih `Surat Tanah` / `Ahli Waris`
   - **Status Pengecekan**: pilih `Dalam Pengecekan` / `Terverifikasi` / dll
3. Ketik **nama pemilik**, **NIK**, atau **nomor alas hak** di kolom pencarian.
4. **Yang harus terjadi**: hanya dokumen yang cocok dengan filter muncul; pencarian juga mencocokkan kolom profiling.

---

## 4. Detail Dokumen & Data Profiling

1. Klik salah satu dokumen khusus di daftar.
2. **Yang harus terlihat di halaman detail**:
   - Badge status pengecekan (Belum Dicek / Dalam Pengecekan / Terverifikasi / Perlu Perbaikan)
   - Card **Data Profiling / Biografi** dengan 5 field
   - Panel **Riwayat & Proses Pengecekan** (list catatan + status)
   - Tombol **Cetak Lembar Arsip** (ikon printer)

---

## 5. Catat Pengecekan

1. Di halaman detail dokumen, buka panel **Riwayat & Proses Pengecekan**.
2. Isi form:
   - Pilih **Status Baru** (misal `Terverifikasi`)
   - Tambahkan **Catatan** (opsional)
3. Klik **Simpan Pengecekan**.
4. **Yang harus terjadi**:
   - Status badge berubah di halaman detail
   - Entry baru muncul di daftar riwayat (nama petugas, catatan, waktu)
   - Flash message "Pengecekan berhasil dicatat."

---

## 6. Cetak Lembar Arsip

1. Di halaman detail, klik tombol **Cetak Lembar Arsip**.
2. Halaman Print terbuka dengan:
   - Kop resmi Kelurahan Grogol Selatan + logo
   - Kode Arsip + Klasifikasi
   - Lokasi Penyimpanan (Rak / Boks)
   - Tabel Data Profiling
   - Status Pengecekan + 3 riwayat terakhir
   - Footer identitas kelurahan
3. Klik **Cetak Sekarang** → dialog print browser muncul.
4. **Yang harus terjadi**: tombol dan header UI tersembunyi saat print; hanya lembar arsip yang tercetak.
5. Jika coba akses halaman Print dari dokumen jenis `Umum` (bukan khusus) → **404** ("Cetak Lembar Arsip hanya tersedia untuk dokumen Surat Tanah / Ahli Waris").

---

## 7. Test Otomatis

```bash
php artisan test tests/Feature/DokumenKhususPemerintahanTest.php
```

| # | Test | Cek |
|---|------|-----|
| 1 | Surat Tanah + PDF di Seksi Pemerintahan | POST berhasil, semua field profiling tersimpan |
| 2 | Ahli Waris di seksi non-pemerintahan | Diam-diam jadi `umum` di database |
| 3 | Filter `jenis_dokumen` + `status_pengecekan` di index | Query menghasilkan subset yang benar |
| 4 | Endpoint `POST /arsip/{id}/pengecekan` | `RiwayatPengecekan` dibuat, `status_pengecekan` dokumen berubah |
| 5 | Halaman Print | Dokumen umum → 404; dokumen khusus → 200 |

Full suite (pasti tidak ada regresi):
```bash
php artisan test
```
