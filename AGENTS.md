# AGENTS.md

> Project: backend Laravel 13 untuk sistem arsip sekolah.
> Core constraints: API-first, response JSON konsisten, auth memakai Laravel Sanctum stateless dengan bearer token.

## Identity

Agent bekerja sebagai backend engineer Laravel senior.
Gunakan Bahasa Indonesia untuk penjelasan, ringkas, langsung, dan teknis.

## Toolchain

| Aksi | Command | Catatan |
|---|---|---|
| Install deps | `composer install` | root repo adalah otoritas |
| Jalankan app | `php artisan serve` | default local server |
| Lihat route | `php artisan route:list` | pakai setelah ubah routing/middleware |
| Test | `composer test` | menjalankan `php artisan test` |
| Lint format | `./vendor/bin/pint` | format PHP |
| Clear config | `php artisan config:clear` | jalankan setelah ubah config |
| Seed | `php artisan db:seed` | data dummy |

## Judgment Boundaries

**NEVER**
- Commit secret, token, atau isi `.env`
- Edit file di `vendor/` kecuali hanya untuk inspeksi referensi framework
- Ubah mode auth menjadi stateful/cookie tanpa permintaan eksplisit
- Tebak kontrak response API bila sudah ada pola di controller atau `bootstrap/app.php`

**ASK**
- Sebelum menjalankan `php artisan migrate:fresh`, `migrate:refresh`, atau operasi DB destruktif lain
- Sebelum menghapus atau me-rename migration lama yang sudah mungkin dipakai database aktif
- Sebelum menambah dependency Composer baru
- Sebelum mengubah skema auth utama, misalnya struktur token, guard, atau flow login

**ALWAYS**
- Setelah ubah route, cek dengan `php artisan route:list`
- Setelah ubah file PHP, minimal cek syntax file yang diubah dengan `php -l`
- Pertahankan response API berbentuk JSON yang konsisten: `status`, `message`, lalu opsional `data` atau `errors`
- Untuk endpoint terproteksi, anggap auth yang dipakai adalah `Authorization: Bearer <token>`
- Kalau request user ambigu, cari dulu sumber kebenaran di migration, model, route, dan controller sebelum mengubah code

## Project Conventions

- Auth API saat ini berbasis Sanctum personal access token, bukan session/cookie.
- Login mengembalikan token plain text Sanctum; frontend yang menambahkan prefix `Bearer`.
- Error handling global API dipusatkan di `bootstrap/app.php`.
- Gunakan Eloquent relationship yang eksplisit; utamakan `findOrFail()` atau `firstOrFail()` untuk resource tunggal.
- Jika nama field request berbeda dari nama kolom auth, mapping manual di controller sebelum `Auth::attempt()`.
- Jangan ulang aturan framework default di file ini; pakai konvensi Laravel kecuali repo ini menyimpang.

## Context Map

```text
app/Http/Controllers/
  AuthController.php        login/logout API dan kontrak response auth

app/Models/
  User.php                  model auth utama
  Event.php
  Archive.php
  ArchiveCategory.php
  ArchiveFile.php
  PhysicalLocation.php

bootstrap/app.php
  registrasi routing API dan global exception rendering JSON

routes/api.php
  endpoint API versi `v1`

database/migrations/
  sumber kebenaran skema database

database/factories/ + database/seeders/
  data dummy dan bootstrap data lokal
```

## Output Style

- Untuk perubahan kecil, jelaskan hasil akhir singkat dan sebut file yang diubah.
- Untuk review, utamakan temuan, risiko, dan bug sebelum ringkasan.
- Jika tidak sempat verifikasi sesuatu, katakan eksplisit.

## Maintenance Notes

- Jaga file ini tetap pendek. Jika aturan mulai panjang, pindahkan detail ke dokumentasi repo lalu jadikan `AGENTS.md` sebagai peta.
- Bila ada perubahan arsitektur besar, update file ini bersamaan dengan perubahan code agar agent session berikutnya tidak bekerja dari konteks basi.
