# OTP reset diperketat + kosakata tombol back
Status: BERJALAN · Service: web (Laravel + Vue) · Diperbarui: 2026-11-07

## Sedang dikerjakan
Perubahan sudah terpasang di working-tree, BELUM commit, BELUM deploy, verifikasi browser
menunggu user:
1. **Aturan owner:** email tak terdaftar → ditolak eksplisit (422 `not-registered`) +
   banner merah di form, bukan lagi respons sukses diam-diam.
2. **Masa berlaku OTP:** 60 menit → 3 menit (backend verify + reset, dan teks badan email).
3. **Kosakata UI (minta user, sesi ini):** tombol back teks biasa ("← Ubah email",
   "← Kembali ke verifikasi") di `ForgotPassword.vue` diubah jadi tombol outline
   (border slate-300, bg putih, shadow-sm, ikon `back` 16px stroke 2, min-h-11 = 44px
   tap target, label tanpa panah teks) — konsisten dengan tombol "Kirim Ulang" di
   baris yang sama. Build OK.
4. **Tombol "Beranda"** (`GuestLayout.vue`, pojok kanan atas halaman guest): tambah
   ikon rumah di depan label via `AppIcon name="home"` (16px stroke 2, `gap-2`,
   padding `px-3`); ikon `Home` didaftarkan di REGISTRY `AppIcon.vue` (belum ada
   sebelumnya). Build OK. Menunggu konfirmasi owner.

5. **Activity Log lupa kata sandi (minta user, sesi ini, Fix #114):** alur OTP
   (tombol "Kirim Kode Verifikasi" → verifikasi → ganti kata sandi) kini tercatat
   di Activity Log. Di-log langsung di `OtpPasswordController` (`logOtp` helper)
   karena middleware `RecordActivity` tak menangkap user tanpa login. Baris berbagi
   `target_id` = id user (kolom integer) + `user_id` agar satu percobaan bisa
   ditelusuri sampai "selesai"; email disimpan di `metadata['email']` sebagai kunci
   penelusuran cadangan. Gagal verifikasi/kadaluarsa juga tercatat. 3 test baru +
   label badge di `Activity/Index.vue`. Termasuk di working-tree bersama perubahan
   OTP ini; commit + deploy bersama fix ini.
   (Catatan 2026-11-07: awalnya `target_id` diisi string email → error MySQL
   dtype di prod lokal; sudah diperbaiki jadi integer + test disesuaikan.)
   **2026-11-07 (lanjutan):** user minta baris send & verify juga TAMPIL di halaman
   /activity-log (sebelumnya hanya "ganti kata sandi"). Akar:
   `ActivityLogController` (`index` + `recent`) memfilter `whereNotIn`
   `password.otp.send`/`password.otp.verify` dari daftar route_name — keduanya
   dihapus dari filter itu; `password.otp.reset` tetap tersembunyi (gagal reset
   tumpang tindih dengan log gagal verify). Test `password reset flow...` kini
   juga klaim 3 baris tampil di /activity-log + opsi filter "aksi" terdaftar.
   21 test AuthActivityLogTest hijau.

## Status terakhir (terbukti)
- `php artisan test tests/Feature/Auth/PasswordResetTest.php` → 9 passed (34 assertions),
  termasuk test baru `test_otp_expires_after_3_minutes` dan `test_otp_still_valid_within_3_minutes`,
  dan `test_otp_send_rejects_unregistered_email_with_error` (422 + `error: not-registered`).
- `npm run build` OK (28.82s). Perubahan belum commit (git: 6 berkas belum di-commit,
  termasuk 3 di resources/js, 1 di app/Http, 1 di resources/views, 1 di tests/Feature).

## Keputusan penting
- Owner memilih: email tak terdaftar ditolak eksplisit (anti-enumeration lama sengaja
  ditinggalkan; risiko: alamat terdaftar bisa ditebak, diterima karena halaman publik
  terbuka; login tetap aman).
- Masa berlaku 3 menit cukup untuk alur reset; keputusan owner.
- Kosakata back button pakai ikon `back` dari AppIcon, bukan panah teks `←` (lebih rapi,
  konsisten dgn sistem ikon aplikasi).

## Langkah berikutnya
1. Verifikasi lewat browser (`127.0.0.1:8000`):
   a. `/forgot-password`: email tak terdaftar → banner merah; email terdaftar
      (`sayahuman478@gmail.com`) → step verifikasi; tombol "Ubah email" outline;
      >3 menit → "Kode OTP telah kadaluarsa"; step 3 → "Kembali ke verifikasi" outline.
   b. **Baru (minta user 2026-11-07):** setelah alur selesai, buka
      `/activity-log` → 3 baris harus muncul: "Aktivitas pemulihan kata sandi",
      "Verifikasi pemulihan kata sandi", "Selesai ganti kata sandi"
      (badge violet/hijau). Dropdown filter "Aksi" juga menampilkan 2 opsi baru.
      (Baris lama sudah ada di DB user 17 / sayahuman478@gmail.com, bisa langsung
      dicek tanpa mengulang alur.)
2. Bila OK → tandai ✅ LIVE di entri CHANGELOG_FIXES.md terkait (cek dulu nomornya via
   `grep -n -i "otp" CHANGELOG_FIXES.md`), cantumkan slug handoff ini di Log Keyword.
3. Commit + push.

## Jangan lakukan
- Jangan mengembalikan aturan "selalu sukses" (anti-enumeration) tanpa izin owner;
  itu keputusan sadar 2026-11-07.
- Jangan ganti ikon `back` dengan panah teks `←` lagi — sudah dipilih owner.
