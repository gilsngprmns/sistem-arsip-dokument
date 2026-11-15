# Golden Path — Tahap 5: Enkripsi & Dekripsi Terkontrol File PDF

## Persiapan

1. Jalankan migrasi:
   ```bash
   php artisan migrate
   ```
2. Jalankan dev server:
   ```bash
   npm run dev
   ```
3. Login sebagai **staf di Seksi Pemerintahan** (atau admin).

---

## 1. Upload Surat Tanah — Enkripsi Otomatis

1. Buka **Arsip** → klik **Tambah Dokumen**.
2. Di form, pilih:
   - **Klasifikasi Dokumen**: `Surat Tanah`
   - **Seksi**: Pemerintahan
3. Panel **Data Profiling** muncul — isi: Nama Pemilik, NIK, dll.
4. Upload lampiran PDF.
5. **Banner hijau** muncul: *"Dokumen khusus akan dienkripsi secara otomatis (AES-256) saat disimpan."*
6. Klik **Simpan**.
7. **Yang harus terjadi**:
   - Redirect ke daftar arsip, dokumen muncul.
   - Di kolom **File Digital**, label berubah jadi **"Unduh PDF"** (bukan "Lihat Lampiran").
   - Di halaman detail, badge **"Terenkripsi AES-256"** muncul di dekat nama file.

---

## 2. Upload Dokumen Umum — Enkripsi Opsional

1. Buka **Tambah Dokumen** lagi.
2. Pilih Klasifikasi **`Umum`**, seksi mana pun.
3. **Checkbox** *"Enkripsi file (AES-256)"* muncul di bawah input upload.
4. Tanpa checkbox diaktifkan → file disimpan publik, label **"Lihat Lampiran"** biasa.
5. Centang checkbox → file dienkripsi, label **"Unduh PDF"**, badge muncul.

---

## 3. Unduh / Lihat File Terenkripsi

### Sebagai staf pemilik (seksi sama)
1. Di daftar arsip, klik **"Unduh PDF"**.
2. **Yang harus terjadi**:
   - File PDF terbuka di browser (inline) atau terunduh (attachment, tergantung parameter `?download=1`).
   - Di **Log Aktivitas** (hanya visible admin), entri baru muncul: `arsip.file.access` dengan keterangan akses.

### Sebagai staf seksi lain
1. Staff dari seksi KSR mencoba membuka file dari seksi PEM.
2. **Yang harus terjadi**: **403 Forbidden** — tidak ada akses.

### Sebagai admin
1. Admin dapat membuka file dari seksi mana pun.
2. Log aktivitas tercatat.

---

## 4. Edit Dokumen yang Sudah Terenkripsi

1. Buka halaman **Edit** untuk dokumen terenkripsi.
2. **Yang harus terjadi**:
   - Badge **"Terenkripsi AES-256"** muncul.
   - Link file saat ini mengarah ke endpoint terkontrol (bukan `/storage/`).
   - Jika ganti file baru: file terenkripsi lama dihapus otomatis dari disk privat.

---

## 5. Hapus Dokumen Terenkripsi (Admin)

1. Buka halaman detail → klik **Hapus** (atau bulk delete).
2. Login sebagai **admin**.
3. Hapus dokumen.
4. **Yang harus terjadi**:
   - File terenkripsi (`storage/app/private/arsip-encrypted/*.enc`) terhapus dari disk.
   - Record dihapus permanen (force delete).
   - Log aktivitas tercatat.

### Sebagai staf (non-admin)
- Staf melakukan **soft delete** (file terenkripsi tetap ada di disk).
- Untuk hapus permanen file, admin harus melakukan force delete.

---

## 6. Export Excel — Hyperlink Aman

1. Klik **Export Excel** di daftar arsip.
2. **Yang harus terjadi**:
   - Kolom lampiran untuk file terenkripsi: hyperlink mengarah ke `/arsip/{id}/file` (bukan `/storage/`).
   - Kolom lampiran untuk file publik: hyperlink tetap ke `/storage/lampiran-arsip/...`.

---

## 7. Backup & Restore — File Terenkripsi Ikut Tercakup

1. Buat backup (bundle ZIP) via halaman **Backup**.
2. **Yang harus terjadi**:
   - File terenkripsi (`disk: local`, `arsip-encrypted/`) ikut terbundel.
   - Metadata `is_encrypted`, `file_hash`, `encrypted_at` ikut terback.
   - Saat restore, file terenkripsi dipulihkan ke `storage/app/private/arsip-encrypted/`.

---

## Ceklist Verifikasi

| No | Skenario | Ekspektasi | Status |
|----|---------|-----------|--------|
| 1 | Upload surat_tanah + PDF | `is_encrypted=true`, file di `local/arsip-encrypted/` | |
| 2 | Upload umum tanpa encrypt | `is_encrypted=false`, file di `public/` | |
| 3 | Upload umum + encrypt | `is_encrypted=true`, file di `local/arsip-encrypted/` | |
| 4 | Staf owner akses file terenkripsi | 200, log aktivitas tercatat | |
| 5 | Staf seksi lain akses | 403 | |
| 6 | Admin akses file terenkripsi | 200, log aktivitas tercatat | |
| 7 | Admin hapus dokumen terenkripsi | File di disk terhapus, record force deleted | |
| 8 | Export Excel — file terenkripsi | Hyperlink → `/arsip/{id}/file` | |
| 9 | Export Excel — file publik | Hyperlink → `/storage/...` | |
| 10 | Backup + restore — file terenkripsi | File pulih ke `private/arsip-encrypted/` | |

---

## Catatan Teknis

- **Algoritma**: AES-256-CBC via Laravel `Crypt` (menggunakan `APP_KEY`).
- **Hash integritas**: SHA-256 file asli disimpan di `file_hash`. Saat dekripsi, hash diverifikasi. Jika tidak cocok → error (file dimodifikasi secara ilegal).
- **File terenkripsi**: disk `local`, direktori `arsip-encrypted/`, ekstensi `.enc`.
- **File publik**: disk `public`, direktori `lampiran-arsip/`.
- **Audit log**: setiap akses file terenkripsi tercatat di `ActivityLog` dengan action `arsip.file.access`.
