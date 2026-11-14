# Panduan Hosting Laravel di Vercel (Gratis)

**Project:** Arsip Dokumen Grogol Selatan (Laravel 12 · PHP 8.2 · Inertia.js + Vue 3)  
**Tujuan:** Website bisa diakses teman secara realtime  
**Tanggal:** 2026-11-05

---

## ⚠️ Perhatian: Keterbatasan Vercel untuk Aplikasi Ini

Aplikasi ini memiliki fitur yang **tidak sepenuhnya kompatibel** dengan Vercel Serverless:

| Fitur | Status di Vercel |
|-------|-----------------|
| Halaman publik (tanpa login) | ✅ Jalan normal |
| Login / Session auth | ⚠️ Perlu konfigurasi khusus |
| Upload file arsip | ❌ Storage sementara — file hilang setelah redeploy |
| Queue (email OTP) | ⚠️ Butuh external queue |
| Database SQLite | ❌ Tidak bisa — harus pakai MySQL eksternal |
| Backup & import | ❌ Tidak bisa di Vercel |
| Cron job otomatis | ⚠️ Perlu Workflows |

**Kesimpulan:** Vercel cocok untuk **demo/preview** halaman publik, tapi **tidak cocok** untuk aplikasi produksi yang butuh penyimpanan file permanen dan session auth yang stabil.

Jika Anda tetap ingin mencoba Vercel, ikuti panduan di bawah. Jika butuh fitur lengkap (upload, login, session), gunakan **Railway** atau **VPS** (lihat [PANDUAN-HOSTING-CLOUDFLARE.md](./PANDUAN-HOSTING-CLOUDFLARE.md)).

---

## Opsi A — Vercel PHP (Hanya Halaman Publik)

> **Cocok untuk:** Memperlihatkan halaman depan (beranda, fitur, alur kerja, bantuan) saja.
> **Tidak cocok untuk:** Login, upload file, atau fitur admin — karena Vercel serverless tidak menyimpan state.

### Langkah 1: Siapkan Repo GitHub

```bash
git add .
git commit -m "Prepare for Vercel deployment"
git push origin main
```

Pastikan `.env`, `vendor/`, `node_modules/`, `storage/` sudah ada di `.gitignore`.

### Langkah 2: Buat `vercel.json` di Root Project

```json
{
  "framework": null,
  "buildCommand": "npm run build",
  "devCommand": "npm run dev",
  "outputDirectory": "public",
  "functions": {
    "api/*.php": {
      "runtime": "vercel-php@0.5.3"
    }
  },
  "rewrites": [
    { "source": "/(.*)", "destination": "/index.php" }
  ]
}
```

### Langkah 3: Buat Entry Point PHP

Buat file `api/index.php`:
```php
<?php
require __DIR__ . '/../public/index.php';
```

Buat file `public/index.php` (jika belum ada path correct):
File ini sudah ada di project Laravel. Vercel akan serve dari sini.

### Langkah 4: Deploy

```bash
npm install -g vercel
vercel login
vercel --prod
```

atau import via [vercel.com/dashboard](https://vercel.com/dashboard) → **Add New...** → **Project** → pilih repo GitHub.

### Langkah 5: Set Environment Variables

Di dashboard Vercel → Settings → Environment Variables:

```
APP_NAME=Arsip Dokumen Grogol Selatan
APP_ENV=production
APP_DEBUG=false
APP_URL=https://nama-proyek.vercel.app
APP_KEY=base64:xxx   # hasil dari: php artisan key:generate

DB_CONNECTION=mysql
DB_HOST=host.database.lombok1.neon.tech
DB_PORT=3306
DB_DATABASE=arsip_grogol
DB_USERNAME=arsip_grogol_user
DB_PASSWORD=password_database

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your@gmail.com
MAIL_PASSWORD=app_password_gmail
MAIL_FROM_ADDRESS=your@gmail.com
MAIL_FROM_NAME="${APP_NAME}"

SESSION_DRIVER=cookie
QUEUE_CONNECTION=sync
CACHE_STORE=file
```

### Langkah 6: Siapkan Database Eksternal

Pilih salah satu:

**Neon (PostgreSQL, gratis):**
1. Daftar di [neon.tech](https://neon.tech)
2. Buat project → salin connection string
3. Ubah ke MySQL-compatible atau pakai adapter PostgreSQL

**PlanetScale (MySQL, gratis — hingga 5GB):**
1. Daftar di [planetscale.com](https://planetscale.com)
2. Buat database `arsip_grogol`
3. Di shell, jalankan setiap migration file satu per satu:
   ```bash
   npx pscale shell arsip_grogol main
   # paste isi migration files
   ```

**Supabase (PostgreSQL, gratis):**
1. Daftar di [supabase.com](https://supabase.com)
2. Buat project → salin connection string
3. Jalankan semua migration via SQL Editor Supabase

---

## Opsi B — Railway (Rekomendasi Utama ✅)

> **Cocok untuk:** Aplikasi penuh — login, upload file, email OTP, queue, cron backup.
> **Harga:** Gratis tier ($5 credit/bulan), cukup untuk aplikasi kecil.

### Langkah 1: Push ke GitHub

Pastikan semua kode sudah di-push ke repository publik atau private.

### Langkah 2: Daftar dan Import

1. Buka [railway.app](https://railway.app)
2. Login dengan GitHub
3. Klik **"New Project"** → **"Initialize from GitHub repo"**
4. Pilih repository Anda

### Langkah 3: Tambah Database

1. Di dalam project Railway, klik **"+"** → **"New Service"**
2. Pilih **"PostgreSQL"** (auto-provisioned, gratis)
3. Salin `DATABASE_URL` yang muncul di variable tab

### Langkah 4: Konfigurasi Environment Variables

Di service PHP (aplikasi utama), tambahkan:

```
APP_NAME=Arsip Dokumen Grogol Selatan
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-project.up.railway.app
APP_KEY=base64:xxx

DB_CONNECTION=pgsql
DB_URL=<paste DATABASE_URL dari service PostgreSQL>

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your@gmail.com
MAIL_PASSWORD=app_password_gmail
MAIL_FROM_ADDRESS=your@gmail.com

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=file
FILESYSTEM_DISK=local
```

Generate APP_KEY di terminal lokal, lalu copy hasilnya ke Railway:
```bash
php artisan key:generate --show
```

### Langkah 5: Jalankan Migration

Di Railway dashboard → PHP service → **Shell**:
```bash
php artisan migrate --force
```

### Langkah 6: Aktifkan Queue Worker

Tambahkan file `Procfile` di root project:
```
web: vendor/bin/heroku-php-apache2 public/
worker: php artisan queue:work --tries=3
scheduler: php artisan schedule:run
```

Di Railway → PHP service → **Settings** → **Scale** → pastikan semua 3 process type aktif.

### Langkah 7: SSL & Domain

Railway memberikan SSL otomatis (`https://*.up.railway.app`).
Untuk custom domain, sambungkan lewat Cloudflare DNS (sama seperti panduan Cloudflare).

### Langkah 8: Cron Backup Otomatis

Di Railway → **Cron Jobs** → tambah:
- Command: `php artisan arsip:cadangkan`
- Schedule: `0 2 * * *` (setiap jam 02:00)

---

## Opsi C — Vercel + External Services (Hybrid)

Jika tetap ingin pakai Vercel tapi butuh fitur lengkap:

### Arsitektur

```
Internet → Cloudflare (DNS/CDN) → Vercel (PHP serverless)
                                          │
                    ┌─────────────────────┼──────────────┐
                    ▼                     ▼              ▼
              Supabase/Neon        Bunny.net        Resend
             (database)         (file storage)     (email OTP)
```

### Langkah Lengkap

#### 1. Database — Supabase (Gratis)

```
1. Daftar di supabase.com
2. Buat project baru
3. Settings → Database → salin Connection String (mode: Transaction)
4. Buka SQL Editor → jalankan semua isi folder database/migrations/
```

Update `.env` produksi:
```ini
DB_CONNECTION=pgsql
DB_URL=postgresql://postgres:PASSWORD@db.xxxx.supabase.co:5432/postgres
```

#### 2. File Storage — Bunny.net Storage (Gratis 10 GB)

```bash
composer require spatie/laravel-filesystem-bunny
```

Daftar di [bunny.net](https://bunny.net) → Storage → buat bucket.
Di `.env`:
```ini
FILESYSTEM_DISK=bunny
BUDDY_STORAGE_ZONE=nama-zone-anda
BUDDY_STORAGE_KEY=key-xxx
```

Update `config/filesystems.php` untuk menambah disk `bunny`.

#### 3. Email — Resend (Gratis 3.000 email/bulan)

```bash
composer require resends/resend-laravel
```

Daftar di [resend.com](https://resend.com) → dapatkan API key.
Di `.env`:
```ini
MAIL_MAILER=resend
RESEND_API_KEY=re_xxxxxxxxx
MAIL_FROM_ADDRESS=noreply@domain-anda.com
```

#### 4. Deploy ke Vercel

```bash
vercel --prod
```

Setelah deploy, buat akun baru di website, coba login, cek apakah email OTP masuk.

---

## Ringkasan Rekomendasi

| Kebutuhan | Solusi Terbaik |
|-----------|---------------|
| Hanya tampilkan halaman depan ke teman | **Vercel** (gratis) |
| Login, upload file, email OTP | **Railway** (gratis tier cukup) |
| Full production, kontrol total | **VPS + Cloudflare** (~50rb/bln) |
| Cepat & tanpa konfigurasi rumit | **Railway** |

> **Saran saya:** Gunakan **Railway** (Opsi B). Satu platform, semua fitur jalan — login, upload, email, queue, cron backup. Gratis untuk usage aplikasi kelurahan kecil.

---

## Troubleshooting

| Masalah | Solusi |
|---------|--------|
| Halaman blank / 500 error | Buka Vercel Dashboard → Project → Logs, cek error PHP |
| "Invalid key" di halaman login | Jalankan `php artisan key:generate --show`, copy ke env vars |
| Upload file gagal / tidak tersimpan | Storage ephemeral — gunakan Bunny.net (Opsi C) atau Railway (Opsi B) |
| Session tidak bertahan saat pindah halaman | Vercel serverless tidak support session persisten; gunakan Railway |
| Email OTP tidak terkirim | Pastikan Gmail App Password (bukan password biasa), cek spam folder |
| Database migration error | Pastikan connection string benar; di Supabase pakai mode Transaction, bukan Session |
| Asset JS/CSS 404 | Jalankan `npm run build` sebelum deploy; cek `public/build/` ada di commit |
| Ziggy route tidak ditemukan | Pastikan `@routes` di `app.blade.php` tetap ada; ini dari PHP, bukan frontend |

## Cara Membuat Gmail App Password (Wajib untuk Email OTP)

Karena aplikasi ini menggunakan SMTP Gmail untuk kirim OTP, Anda perlu App Password:

1. Buka [myaccount.google.com/security](https://myaccount.google.com/security)
2. Aktifkan **2-Step Verification** (jika belum)
3. Cari **App passwords** → klik
4. Pilih app: **Mail**, device: **Other (Custom name)**
5. Ketik nama: `Laravel Arsip`
6. Salin password 16 karakter yang muncul (contoh: `abcd efgh ijkl mnop`)
7. Tempel ke `MAIL_PASSWORD` di environment variables

> **Perhatian:** Ini BUKAN password Gmail biasa. Password biasa tidak bisa dipakai untuk SMTP.

## Rekomendasi Akhir

Berdasarkan arsitektur aplikasi ini (session-based auth, file upload, queue email, cron backup):

1. **Untuk demo cepat** — gunakan **Cloudflare Tunnel** (lihat [PANDUAN-HOSTING-CLOUDFLARE.md](./PANDUAN-HOSTING-CLOUDFLARE.md)). Paling cepat, langsung jalan tanpa server baru.

2. **Untuk aplikasi penuh online 24/7** — gunakan **Railway** (Opsi B). Gratis untuk usage kecil, semua fitur jalan: login, upload, email OTP, queue, cron backup.

3. **Vercel hanya untuk halaman publik** — jika yang penting cuma tampilkan beranda/fitur/alur-kerja/bantuan ke teman tanpa login, Vercel cukup. Tapi begitu mau login atau upload, beralih ke Railway.
