# Panduan Push ke GitHub (Kolaborator)

> Repo: `https://github.com/gilsngprmns/sistem-arsip-dokument.git`
> Peran Anda: **Collaborator** (akses penuh ke `main`)
> Terakhir diperbarui: 2026-11-15

---

## 1. Verifikasi Akses (Sekali Saja)

Jalankan di terminal:

```bash
git remote -v
```

Harus menampilkan:

```
origin  https://github.com/gilsngprmns/sistem-arsip-dokument.git (fetch)
origin  https://github.com/gilsngprmns/sistem-arsip-dokument.git (push)
```

Lalu tarik status terbaru:

```bash
git fetch origin
git status
```

Jika muncul pesan **permission denied** atau **403**, Anda belum di-invite.
Minta owner (gilsngprmns) mengecek:
> Repository Settings → Collaborators → pastikan username Anda terdaftar.

---

## 2. Persiapan: Commit Kerja Lokal

Semua perubahan harus di-commit dulu sebelum push.

### Lihat apa yang berubah
```bash
git status
```

### Tambahkan file yang berubah (pilih salah satu)
```bash
# Hanya file tertentu
git add app/Http/Controllers/ArsipController.php
git add resources/js/Pages/Dashboard.vue

# Semua file di workspace
git add -A
```

### Commit
```bash
git commit -m "deskripsi singkat: apa yang diubah dan kenapa"
```

> Contoh message yang baik:
> `Add: implementasi fitur pencarian arsip per seksi`
> `Fix: perbaiki bug ekspor PDF pada halaman detail arsip`

### Cek commit terakhir
```bash
git log --oneline -5
```

---

## 3. Push ke GitHub

```bash
git push origin main
```

Jika baru pertama kali push di branch `main`, tambahkan flag:
```bash
git push -u origin main
```

---

## 4. Alur Lengkap — Dari `git status` Bersih sampai Push Berhasil

Untuk kasus di mana Anda sudah tahu semua file yang berubah (contoh: 40+ file):

```bash
# 1. Cek apa yang berubah
git status

# 2. Cek folder backup — JANGAN commit jika berisi DB dump / credential
ls backup/
# Jika isinya sensitif, tambahkan ke .gitignore:
# echo "backup/" >> .gitignore

# 3. Cek file sensitif lain
git status --porcelain | grep -Ei "\.env|secret|credential|api.?key"
# Harus kosong — jika tidak, jangan add file tersebut

# 4. Commit semua
git add -A
git commit -m "Add: dokumen khusus, enkripsi PDF, retensi arsip, riwayat pengecekan, backup bundle, UI components"

# 5. Sinkronkan dengan remote (wajib sebelum push)
git pull --rebase origin main
# Jika ada conflict:
#   a. Buka file conflict, cari marker <<<<<<< / ======= / >>>>>>>
#   b. Pilih versi yang benar, hapus semua marker
#   c. git add <nama-file>
#   d. git rebase --continue
#   e. Ulangi sampai selesai

# 6. Push
git push origin main
```

---

## 5. Jika Ada Kendala

### A. Muncul "rejected: non-fast-forward" (remote lebih baru dari local)

Artinya ada commit di GitHub yang belum di lokal. Ambil dulu, baru push:

```bash
git pull --rebase origin main
git push origin main
```

Jika ada **conflict** saat rebase:

1. Buka file yang conflict — cari marker `<<<<<<<`, `=======`, `>>>>>>>`
2. Pilih versi yang benar (atau gabungkan), hapus semua marker
3. `git add <file-yang-sudah-diperbaiki>`
4. `git rebase --continue`
5. Ulangi sampai selesai, lalu `git push origin main`

### B. Muncul "403 Forbidden" atau "permission denied (https)"

Akun Anda belum punya akses push ke repo ini.

```
Langkah:
1. Buka https://github.com/gilsngprmns/sistem-arsip-dokument
2. Tanya owner untuk memverifikasi Anda sudah ada di tab "Collaborators"
3. Jika sudah ada, coba pull dulu: git pull origin main
4. Jika tetap gagal, pastikan credential GitHub sudah tersimpan:
   - Terminal Windows: gunakan "Git Credential Manager" yang muncul otomatis
   - Atau set ulang: git config --global credential.helper manager
```

### C. Muncul "error: RPC failed" atau koneksi timeout

Biasanya karena file yang di-push besar atau koneksi tidak stabil.

```bash
# Naikkan batas buffer (untuk file < 500MB)
git config http.postBuffer 524288000
git push origin main
```

### D. Mau membatalkan push yang sudah dilakukan

**Jangan gunakan `git push --force` tanpa izin owner** — itu bisa menghapus kerja orang lain.

Untuk membatalkan commit terakhir yang sudah di-push:

```bash
# Buat commit balikan (aman, tanpa menghapus history)
git revert HEAD
git push origin main
```

### E. Ada banyak perubahan dan mau memisahkan push

Buat branch dulu, lalu merge ke main di GitHub (Pull Request):

```bash
git checkout -b fitur-saya
git add -A
git commit -m "Fitur X: implementasi awal"
git push -u origin fitur-saya
```

Lalu di GitHub: buka repo → tab **Pulls** → **New pull request** →
pilih `fitur-saya` → `main` → **Create pull request** → **Merge**.

> Catatan: sebagai collaborator Anda bisa push langsung ke `main`.
> Branch + PR disarankan untuk perubahan besar agar owner bisa review dulu.

### F. Push dari laptop HP / perangkat lain

```bash
# Clone repo (sekali saja)
git clone https://github.com/gilsngprmns/sistem-arsip-dokument.git
cd sistem-arsip-dokument

# Lalu ikuti langkah 4 di atas (Alur Lengkap)
```

Jika clone minta username/password, masukkan **Personal Access Token (PAT)**:
1. GitHub → Settings → Developer settings → Personal access tokens
2. Buat token baru dengan scope `repo`
3. Gunakan token sebagai password saat diminta
4. Username tetap `gilsngprmns` (atau username yang di-invite)

---

## 6. Checklist Sebelum Push

- [ ] `git status` bersih atau sudah di-commit semua
- [ ] `git log --oneline -3` commit terakhir Anda terlihat
- [ ] `ls backup/` — folder backup tidak berisi DB dump / credential
- [ ] Tidak ada file sensitif yang ter-commit: `.env`, API key, secret
- [ ] Test berjalan lokal (jika ada test suite)

```bash
# Cek cepat file sensitif
git diff --name-only HEAD~1 | grep -Ei "\.env|secret|credential|key"
# Hasil harus kosong
```

---

## 7. Singkat — Alur Normal (6 Baris)

```bash
git status                    # apa yang berubah?
git add -A                    # simpan semua perubahan
git commit -m "deskripsi"     # commit
git pull --rebase origin main # sinkronkan dengan remote
git push origin main          # kirim ke GitHub
# Buka https://github.com/gilsngprmns/sistem-arsip-dokument untuk verifikasi
```
