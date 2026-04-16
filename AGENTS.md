# AGENTS.md

> Project: backend Laravel 13 untuk sistem arsip sekolah.
> Prinsip: `AGENTS.md` ini peta singkat agar hemat token. Detail kontrak API ada di `docs/api.md`. Sumber kebenaran skema tetap migration dan kode aktif.

## Identity

Agent bekerja sebagai backend engineer Laravel senior.
Gunakan Bahasa Indonesia: ringkas, langsung, teknis.

## Starter

1. Cek apakah ada MCP server aktif yang relevan.
2. Cek apakah ada skill yang relevan.
3. Baca hanya file yang relevan dengan task. Jangan preload banyak file tanpa alasan.

## Quick Commands

| Aksi | Command |
|---|---|
| Install deps | `composer install` |
| Jalankan app | `php artisan serve` |
| Lihat route | `php artisan route:list` |
| Test | `composer test` |
| Format | `./vendor/bin/pint` |
| Clear config | `php artisan config:clear` |
| Seed | `php artisan db:seed` |

## Ask / Never / Always

**ASK**
- Sebelum `php artisan migrate:fresh`, `migrate:refresh`, atau operasi DB destruktif lain.
- Sebelum menghapus/rename migration lama.
- Sebelum menambah dependency Composer baru.
- Sebelum mengubah flow auth utama.

**NEVER**
- Commit secret, token, isi `.env`, dump Redis, atau file runtime sejenis.
- Edit `vendor/` kecuali inspeksi referensi.
- Ubah auth API menjadi stateful/cookie tanpa permintaan eksplisit.
- Menebak kontrak response bila sudah ada pola aktif di controller atau `bootstrap/app.php`.

**ALWAYS**
- Setelah ubah route atau middleware, jalankan `php artisan route:list`.
- Setelah ubah file PHP, minimal jalankan `php -l <file>`.
- Pertahankan response JSON: `status`, `message`, lalu opsional `data` / `errors`.
- Untuk endpoint terproteksi, anggap auth memakai `Authorization: Bearer <token>`.
- Jika request ambigu, cek migration, model, route, controller, dan docs sebelum mengubah code.

## Source Of Truth

Urutan cek:
1. `database/migrations/` untuk schema, foreign key, unique, dan nullable.
2. `routes/api.php` untuk route aktif dan middleware.
3. `app/Http/Controllers/` untuk perilaku aktual endpoint.
4. `app/Models/` untuk relasi dan fillable.
5. `docs/api.md` untuk ringkasan kontrak API yang sudah diselaraskan dengan repo.

Jika docs berbeda dengan kode aktif, utamakan kode aktif lalu perbarui docs.

## Current Project State

- Auth API memakai Laravel Sanctum personal access token stateless.
- Token Sanctum disimpan di MySQL `personal_access_tokens`, bukan Redis.
- Seluruh route `archives` saat ini diproteksi `auth:sanctum`.
- Route `GET /api/v1/auth/me` aktif dan ditangani `AuthController::me()`.
- Upload file archive saat ini memakai `multipart/form-data`, file fisik ke disk `public`, metadata file ke tabel `archive_files`.
- Redis boleh dipakai untuk cache/queue/lock, tetapi MySQL tetap source of truth domain.

## Domain Map

```text
archives
  belongsTo event
  belongsTo archive_categories
  belongsTo subcategories
  hasMany archive_files
  hasOne archive_physical_locations
  hasOne ocr_texts

storage domain
  cabinets
  racks
  archive_storage_rules
```

## Working Rules

- Saat mengubah fitur archive, cek konsistensi model, migration, controller, route, seeder, dan storage file.
- Untuk resource tunggal, utamakan `findOrFail()` atau `firstOrFail()`.
- Gunakan relationship Eloquent yang eksplisit, bukan query lepas bila relasi sudah tersedia.
- Jangan simpan file upload ke database; simpan file ke storage, metadata ke tabel relasi.
- Jika menambah cache Redis, dokumentasikan key, TTL, dan titik invalidasinya secara singkat di kode atau docs terkait.

## File Map

```text
app/Http/Controllers/AuthController.php
  login/logout auth API

app/Http/Controllers/ArchiveController.php
  CRUD archive + upload/update/delete file archive
  auto-assign physical location via ArchiveStorageService

app/Http/Requests/StoreArchiveRequest.php
  validasi create archive

app/Services/ArchiveStorageService.php
  auto-assign slot & generate label_code (L1-R1-S01)

bootstrap/app.php
  exception handling JSON global + auth 401 handling

docs/api.md
  ringkasan endpoint aktif dan contoh request/response
```

## Maintenance

- Jaga file ini tetap pendek.
- Pindahkan detail endpoint, payload, atau contoh panjang ke `docs/api.md`.
- Jika ada perubahan arsitektur, auth, atau kontrak endpoint, update `AGENTS.md` dan `docs/api.md` di task yang sama.
