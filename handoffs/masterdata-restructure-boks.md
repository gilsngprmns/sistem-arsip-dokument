# Masterdata restructure: Lokasi → Boks per Rak
Status: BERJALAN · Service: arsip · Diperbarui: 2026-11-12 23:30

## Sedang dikerjakan
Struktur fisik arsip berubah dari `lokasi_arsip` (gedung/lantai/ruangan) menjadi
hierarki baru: Rak → Boks → Dokumen. Semua kode sudah ditulis di working tree,
belum di-deploy (migration belum dijalankan).

## Status terakhir (apa yang sudah terbukti / sudah deploy)
- Kode lengkap: model, controller, routes, seeder, FormRequest, middleware,
  exports, BundleBackupService, frontend (Vue), tests — semua di working tree.
- Baru file: BoksArsip model, BoksArsipController, Boks/Index.vue page,
  migration `2026_11_12_000001_restructure_location_hierarchy.php`
- Dihapus: LokasiArsip model, LokasiArsipController, LokasiEditModal.vue,
  LokasiArsipSeeder.php

## Keputusan penting
- `kode_boks` unik global, format `BOK-<KODE_RAK>` (auto-generate saat migrasi)
- Dokumen tanpa rak → boks_id = null (valid, tidak ditolak)
- Dokumen dengan rak tapi boks_id null → validasi closure menolak (harus pilih boks)
- `boks.destroy` menolak hapus kalau masih berisi arsip
- Misi 2 (handover rak/seksi + boks_id di FormRequest): sudah selesai di kode

## Langkah berikutnya
1. **Jalankan migration** — `php artisan migrate` (butuh izin user; ada DROP TABLE)
2. Jalankan test suite: `php artisan test`
3. Jalankan build frontend: `npm run build`
4. Verifikasi UI: halaman Boks/Index.vue, form arsip (Create/Edit), rak modal
5. Setelah terverifikasi: tulis entri changelog + commit + hapus handoff ini

## Jangan lakukan (jebakan yang sudah ditemukan)
- Migration `down()` tidak bisa mengembalikan data yang sudah di-drop dari
  kolom lama (nomor_box/map/slot hilang setelah migrate:refresh)
- Pastikan DB backup SEBELUM `php artisan migrate` (mysqldump)
- `BoksArsip::where('kode_boks', 'unique')` — kode harus unik global, bukan per-rak
- Jangan pakai `lokasi_arsip` di BundleBackupService export/import — sudah
  diganti ke `boks_arsip` di payload
