# PANDUAN PENGECEKAN DOKUMEN KHUSUS

Panduan penggunaan fitur pengecekan (verifikasi) untuk dokumen khusus —
**Surat Tanah** dan **Ahli Waris** di sistem Arsip Dokumen.

---

## Sekilas

Fitur pengecekan melacak status verifikasi sebuah dokumen arsip melalui 4
status:

| Status             | Label            | Berarti                                              |
|--------------------|------------------|------------------------------------------------------|
| `belum_dicek`      | Belum Dicek      | Kondisi awal, belum ada pemeriksaan                  |
| `dalam_pengecekan` | Dalam Pengecekan | Sedang diperiksa                                     |
| `terverifikasi`    | Terverifikasi    | Hasil pemeriksaan cocok / valid                      |
| `perlu_perbaikan`  | Perlu Perbaikan  | Ada ketidaksesuaian, harus diperbaiki                |

Fitur ini **hanya tersedia** untuk klasifikasi dokumen `Surat Tanah` dan
`Ahli Waris`. Dokumen jenis lain tidak menampilkan panel pengecekan.

Setiap perubahan status tercatat otomatis di **riwayat** — siapa pelaksananya,
kapan, dari status apa ke status apa, beserta catatan opsional.

---

## 1. Filter Status Pengecekan (Halaman Semua Dokumen)

Di halaman **Semua Dokumen**, tersedia dropdown **Status Pengecekan** di panel
filter untuk menyaring dokumen berdasarkan status verifikasinya.

- **Semua Pengecekan** — tampilkan semua dokumen.
- Pilih status tertentu — hanya dokumen dengan status itu yang tampil.

> **Tips:** Jika hasil menunjukkan "0 dokumen", pastikan tidak ada filter
> lain (tanggal, status arsip, klasifikasi) yang mempersempit pencarian secara
> bersamaan. Reset filter lain untuk melihat semua dokumen.

---

## 2. Melakukan Pengecekan (Halaman Detail Dokumen)

### Prasyarat
- Dokumen yang dibuka berjenis **Surat Tanah** atau **Ahli Waris**.
- Kamu memiliki akses ke dokumen tersebut (sesuai hak seksi).

### Langkah-langkah

1. Dari daftar dokumen, **klik baris dokumen** yang ingin diperiksa → masuk
   ke halaman detail.
2. Cari panel kuning **"Riwayat & Proses Pengecekan"** di bagian bawah.
3. Pilih salah satu tindakan:

   | Tombol / Chip                        | Aksi yang terjadi                        |
   |--------------------------------------|------------------------------------------|
   | **Mulai Pengecekan** (chip biru)     | Ubah status menjadi `dalam_pengecekan`   |
   | **Terverifikasi** (chip hijau)       | Ubah status menjadi `terverifikasi`      |
   | **Perlu Perbaikan** (chip merah)     | Ubah status menjadi `perlu_perbaikan`    |
   | **+ Tambah Catatan** (tombol atas)   | Buka modal dengan status default         |

4. **Modal Pengecekan** terbuka — isi:
   - **Status Pengecekan** — pilih status tujuan (dropdown).
   - **Catatan Pengecekan** (opsional, maks 2000 karakter) — jelaskan hasil
     pemeriksaan, alasan perlu perbaikan, dsb.
5. Klik **Simpan**.
6. Status dokumen ter-update, dan entri baru muncul di riwayat.

---

## 3. Melihat Riwayat Pengecekan

Di panel yang sama, daftar riwayat (5 entri terakhir, bisa diperluas dengan
tombol **"Lihat Semua"** jika lebih dari 5) menampilkan:

- Alur status: `status sebelumnya → status sesudahnya`
- Catatan pemeriksaan (jika diisi)
- Nama petugas yang melakukan
- Waktu relatif (misal "3 hari lalu") — hover untuk timestamp lengkap

---

## 4. Kapan Harus Pindahkan Status?

Alur umum yang disarankan:

```
Belum Dicek
   ↓  (petugas memulai pemeriksaan fisik/digital dokumen)
Dalam Pengecekan
   ↓  (pemeriksaan selesai, hasil valid)
Terverifikasi
   ↓  (jika ditemukan ketidaksesuaian)
Perlu Perbaikan
   ↓  (perbaikan selesai, diperiksa ulang)
Terverifikasi
```

Catatan di setiap transisi sangat membantu audit — selalu isi bila ada
detail yang perlu didokumentasikan.

---

## 5. Catatan Teknis

- **Model data:** `RiwayatPengecekan` → tabel `riwayat_pengecekan_arsip`
- **Endpoint simpan:** `POST arsip/{arsip}/pengecekan`
- **Validasi:** status harus salah satu dari `STATUS_PENGECEKAN`
- **Akses kontrol:** `assertDocumentAccess` — hanya pengguna dengan hak akses
  dokumen yang bisa mencatat pengecekan.
