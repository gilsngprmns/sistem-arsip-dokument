# Golden Path — Tahap 6: Klasifikasi Masa Aktif & Retensi Arsip

## Persiapan

1. Jalankan migrasi:
   ```bash
   php artisan migrate
   ```
2. Jalankan dev server:
   ```bash
   npm run dev
   ```
3. Login sebagai **admin** (atau staf, tergantung skenario).

> Untuk menguji skenario "kedaluwarsa" tanpa menunggu 5 tahun, set tanggal
> `tanggal_surat` dokumen ke 6 tahun lalu sehingga `tanggal_kedaluwarsa`
> jatuh di masa lalu. Kategori `kustom` dengan `retensi_tahun` kecil
> (mis. 1) mempercepat pengujian.

---

## 1. Tambah Dokumen dengan Retensi Ringan (Default)

1. Buka **Arsip** → klik **Tambah Dokumen**.
2. Di panel **Klasifikasi Masa Aktif**, pilih kategori:
   - **Retensi Ringan (5 Tahun)** → input tahun tersembunyi, tanggal
     kedaluwarsa dihitung otomatis = tanggal surat + 5 tahun.
3. Klik **Simpan**.
4. **Yang harus terjadi**:
   - Redirect ke daftar arsip, dokumen muncul.
   - Di halaman detail, badge retensi **hijau** muncul:
     "Retensi Ringan".
   - Kolom `retensi_kategori` = `ringan_5_tahun`,
     `tanggal_kedaluwarsa` terisi (tanggal_surat + 5 tahun).

---

## 2. Tambah Dokumen dengan Retensi Permanen

1. Buka **Tambah Dokumen** lagi.
2. Pilih kategori **Permanen**.
3. Klik **Simpan**.
4. **Yang harus terjadi**:
   - Badge di detail: **"Permanen"** (biru langit).
   - Kolom `tanggal_kedaluwarsa` = `null`.
   - Dokumen tidak pernah masuk daftar "kedaluwarsa".

---

## 3. Tambah Dokumen dengan Retensi Kustom

1. Buka **Tambah Dokumen**.
2. Pilih kategori **Kustom**, isi jumlah tahun (mis. 10).
3. **Preview tanggal** di bawah input menampilkan tanggal kedaluwarsa
   yang akan diterapkan.
4. Klik **Simpan**.
5. **Yang harus terjadi**:
   - Badge di detail: **"Retensi 10 thn"** (hijau).
   - `retensi_kategori` = `kustom`, `retensi_tahun` = 10,
     `tanggal_kedaluwarsa` = tanggal_surat + 10 tahun.
6. Jika pilih kategori `kustom` tanpa mengisi tahun → validasi
   menolak: tahun wajib.

---

## 4. Jenis Dokumen Khusus → Retensi Permanen Otomatis

1. Buka **Tambah Dokumen**.
2. Pilih Klasifikasi Dokumen: `Surat Tanah` atau `Ahli Waris`
   (seksi Pemerintahan).
3. Panel retensi secara otomatis mengunci kategori ke **Permanen**
   (input kategori nonaktif / diset otomatis).
4. Klik **Simpan**.
5. **Yang harus terjadi**:
   - `retensi_kategori` = `permanen`, badge **"Permanen"** muncul.
   - `tanggal_kedaluwarsa` = `null`.

---

## 5. Filter Status Retensi di Daftar Arsip

1. Di daftar **Arsip**, pada bar filter:
   - Pilih **Status Retensi** → `Kedaluwarsa`.
2. **Yang harus terjadi**:
   - Hanya dokumen dengan `tanggal_kedaluwarsa` < hari ini dan
     `retensi_dipertahankan = false` yang tampil.
3. Ulangi dengan:
   - `Akan Kedaluwarsa` → dokumen yang kedaluwarsanya dalam 30 hari.
   - `Dipertahankan` → dokumen yang sudah ditindak (retensi diperpanjang).
   - `Aktif` → default, dokumen yang masih dalam masa aktif.

---

## 6. Peringatan Retensi di Dashboard

1. Buat 2 dokumen:
   - 1 dokumen dengan `tanggal_kedaluwarsa` = 1 bulan lalu
     (kedaluwarsa).
   - 1 dokumen dengan `tanggal_kedaluwarsa` = 20 hari lagi
     (akan kedaluwarsa).
2. Buka **Dashboard**.
3. Di panel **"Perlu Diperhatikan"**:
   - Kartu **merah** "Retensi Kedaluwarsa" muncul, angka = jumlah
     dokumen kedaluwarsa.
   - Kartu **kuning** "Retensi Akan Berakhir" muncul, angka = jumlah
     dokumen yang akan kedaluwarsa dalam 30 hari.
4. Klik kartu → redirect ke `/arsip?status_retensi=kedaluwarsa`
   atau `/arsip?status_retensi=akan_kedaluwarsa`.

---

## 7. Peringatan Retensi di Banner Daftar Arsip

1. Jika ada dokumen kedaluwarsa/akan kedaluwarsa (dalam scope user),
   buka daftar **Arsip**.
2. **Banner peringatan** muncul di atas tabel:
   - Warna **merah** jika ada yang sudah kedaluwarsa.
   - Warna **kuning** jika hanya yang akan kedaluwarsa.
3. Teks banner menunjukkan jumlah dokumen dan tautan ke filter
   `status_retensi` yang sesuai.

---

## 8. Tindak Retensi — Pertahankan Masa Aktif

### Syarat
Dokumen harus berstatus **kedaluwarsa** atau **akan kedaluwarsa**.

### Langkah
1. Buka halaman detail dokumen yang kedaluwarsa/akan kedaluwarsa.
2. Panel peringatan **"Masa Aktif Retensi Berakhir"** muncul
   di kolom kanan.
3. Klik tombol **"Pertahankan Retensi"**.
4. Muncul modal:
   - Teks penjelasan.
   - Input **Catatan** (opsional, maks. 2000 karakter).
5. Klik **Pertahankan**.
6. **Yang harus terjadi**:
   - Redirect kembali ke halaman detail.
   - `retensi_dipertahankan` = `true`.
   - `retensi_ditindak_waktu` dan `retensi_ditindak_oleh` terisi.
   - Badge berubah jadi **"Retensi Dipertahankan"** (biru).
   - Di Log Aktivitas (admin), entri baru muncul:
     `arsip.retensi-tindakan` — keterangan "mengambil tindakan
     retensi: mempertahankan masa aktif".

### Bila status masih aktif
1. Dokumen permanen atau belum kedaluwarsa → klik tombol
   "Pertahankan Retensi" (tidak tersedia di UI; bila dipaksa via
   endpoint):
   - **Flash message merah**: "Dokumen belum memerlukan tindakan
     retensi."
   - `retensi_dipertahankan` tetap `false`.

### Akses staff (seksi lain)
1. Staff dari seksi KSR mencoba tindak dokumen milik seksi PEM.
2. **403 Forbidden** — tidak ada akses.

---

## 9. Badge Retensi di Modal Detail Singkat

1. Di daftar arsip, klik ikon detail (modal ringan) sebuah dokumen
   yang memiliki retensi.
2. Di header modal, samping status badge, muncul badge retensi:
   - Warna & label sesuai status (lihat tabel di bawah).

| Kondisi | Label Badge | Warna |
|---------|------------|-------|
| `retensi_dipertahankan = true` | Retensi Dipertahankan | Biru |
| status = kedaluwarsa | Retensi Kedaluwarsa | Merah |
| status = akan kedaluwarsa | Akan Kedaluwarsa | Kuning |
| kategori = permanen | Permanen | Biru langit |
| kategori = ringan_5_tahun | Retensi Ringan | Hijau |
| kategori = kustom | Retensi {n} thn | Hijau |
| tanpa kategori | (tidak muncul) | — |

---

## Ceklist Verifikasi

| No | Skenario | Ekspektasi | Status |
|----|---------|-----------|--------|
| 1 | Tambah + ringan_5_tahun | `tanggal_kedaluwarsa` = tanggal_surat + 5thn, badge hijau | |
| 2 | Tambah + permanen | `tanggal_kedaluwarsa` = null, badge biru langit | |
| 3 | Tambah + kustom 10 thn | `tanggal_kedaluwarsa` = +10 thn, label "Retensi 10 thn" | |
| 4 | Kustom tanpa tahun → validasi tolak | Session error di `retensi_tahun` | |
| 5 | Surat Tanah → permanen otomatis | Badge permanen, `tanggal_kedaluwarsa` null | |
| 6 | Filter `status_retensi=kedaluwarsa` | Hanya dokumen kedaluwarsa tampil | |
| 7 | Filter `status_retensi=akan_kedaluwarsa` | Dokumen kedaluwarsa ≤ 30 hari tampil | |
| 8 | Dashboard — kartu retensi muncul | Angka benar, tautan ke filter yang sesuai | |
| 9 | Banner daftar arsip | Muncul bila ada dokumen perlu perhatian | |
| 10 | Tindak "Pertahankan" (kedaluwarsa) | `retensi_dipertahankan=true`, badge biru, log tercatat | |
| 11 | Tindak dokumen yang masih aktif | Flash merah, tidak ada perubahan | |
| 12 | Staff seksi lain tindak | 403 | |
| 13 | Badge di modal detail | Label & warna sesuai tabel | |

---

## Catatan Teknis

- **Kategori retensi**: `ringan_5_tahun` (default), `permanen`, `kustom`.
- **Ambang peringatan**: 30 hari sebelum `tanggal_kedaluwarsa` → status
  `akan_kedaluwarsa`. Konstanta: `DokumenArsip::RETENSI_PERINGATAN_HARI`.
- **Tanggal kedaluwarsa dihitung** di `perbaruiTanggalKedaluwarsa()`:
  - `permanen` → `null` (tanpa batas).
  - `ringan_5_tahun` → tanggal dasar + 5 tahun.
  - `kustom` → tanggal dasar + `retensi_tahun` (min. 1).
- **Tanggal dasar** = `tanggal_surat` bila ada, jika tidak = hari ini.
- **Tindakan retensi** hanya lewat endpoint `POST /arsip/{id}/retensi-tindakan`;
  tidak bisa diubah dari form store/update biasa.
- **Audit log**: setiap tindakan retensi tercatat dengan action
  `arsip.retensi-tindakan`, deskripsi khusus di `RecordActivity` middleware.
- **Akses staff**: `assertDocumentAccess` memastikan staf hanya bisa
  tindak dokumen dari seksi yang sama.
