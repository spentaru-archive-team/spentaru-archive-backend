# AGENTS.md

> Project: backend Laravel untuk sistem arsip sekolah.
> Prinsip: file ini ringkas. Kontrak endpoint aktif ada di `docs/api.md`, tetapi source of truth tetap migration, route, request, controller, dan model aktif.

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
- Untuk endpoint terproteksi, cek dulu apakah flow auth aktif memakai cookie session Sanctum stateful atau bearer token.
- Jika request ambigu, cek migration, model, route, request, controller, dan docs sebelum mengubah code.

## Source Of Truth

Urutan cek:
1. `database/migrations/` untuk schema, foreign key, unique, nullable, dan cascade.
2. `routes/api.php` untuk route aktif dan middleware.
3. `app/Http/Requests/` untuk kontrak validasi request.
4. `app/Http/Controllers/` untuk perilaku endpoint.
5. `app/Models/` untuk relasi dan fillable.
6. `docs/api.md` untuk ringkasan kontrak API yang sudah diselaraskan dengan repo.

Jika docs berbeda dengan kode aktif, utamakan kode aktif lalu perbarui docs.

## Current Project State

- Auth API memakai Laravel Sanctum stateful berbasis session cookie untuk SPA/first-party frontend.
- Login dibatasi `throttle:5,1`.
- Frontend perlu ambil CSRF cookie via `GET /sanctum/csrf-cookie` sebelum `POST /api/v1/auth/login`.
- User roles: `admin` dan `guru`.
- RBAC aktif:
  - `admin` bisa CRUD master data, user, dan archive storage rule.
  - `guru` bisa read master data, full CRUD archive, akses dashboard, dan update profil sendiri.
- Upload file archive memakai `multipart/form-data`, file fisik ke disk `local` private, metadata ke tabel `archive_files`.
- Endpoint list `users`, `archives`, `events`, dan `archives/physical-locations` sudah memakai query `q` untuk search berbasis Laravel Scout.
- Endpoint list `archives`, `events`, dan `archives/physical-locations` juga mendukung filter dan sort dinamis via query string.
- Default konfigurasi Scout saat ini memakai driver `database` dari `config/scout.php`.
- Archive bisa punya `physical_location` dan `ocr_text`.
- Event punya `softfile_status` (`uploaded` / `pending_upload`) yang disinkronkan dari keberadaan `archive_files` pada archive terkait.
- Archive auto-assign physical location via `ArchiveStorageService` saat create archive bila rule/rak tersedia.
- Ada endpoint admin untuk CRUD `archive_storage_rules` sebagai rule penempatan arsip ke lemari.
- Ada endpoint manual untuk CRUD physical location archive.
- Endpoint create/update `cabinets` mendukung sinkronisasi nested `racks` dalam satu request.
- Endpoint create/update `racks` menerima `used_capacity` dan menjaga validasi kapasitas tetap konsisten.
- Archive punya workflow retensi: `retention_due_date`, `retention_status`, `retention_decided_at`, `retention_decided_by`, `retention_note`.
- Ada endpoint arsip tanpa lokasi fisik, arsip siap pemusnahan, dan keputusan retensi arsip.
- Ada AI tool endpoint internal `POST /api/v1/ai/tools/archives/search` yang diakses service Python via shared secret header dan menerima `question` bebas.
- Dashboard punya endpoint daftar guru dengan event yang belum memiliki archive.
- Global JSON exception handling ada di `bootstrap/app.php` untuk 401, 403, 405, 422, 429, dan 500.
- Semua endpoint CRUD memiliki rate limiting: POST/PUT 30 req/menit, DELETE 10 req/menit, GET list 60 req/menit.
- Upload file divalidasi MIME type server-side (`mimetypes:`) di controller, bukan hanya ekstensi.
- File arsip bisa diakses via endpoint terautentikasi `GET /api/v1/archives/{id}/preview` dan `GET /api/v1/archives/{id}/download`.
- Search query archive sudah escape karakter wildcard SQL (`%`, `_`, `\`) untuk mencegah abuse.
- Perubahan role user di-log dan hanya bisa dilakukan oleh admin (bukan self-update).
- Komunikasi AI service OCR memakai konfigurasi HTTP via `config/services.ai_gateway` (default `http://localhost:5000` untuk development, production wajib set env `AI_SERVICE_BASE_URL` ke HTTPS).
- Delete archive dan retention status `destroyed` menghapus vector dari Qdrant via AI service endpoint `DELETE /api/vector/{vector_id}`.
- Archive `ocr_texts` menyimpan `vector_id`, sedangkan `archive_files` hanya menyimpan metadata file dan `extraction_status`.
- AI search endpoint `/ai/tools/archives/search` memakai hybrid search: keyword (MySQL LIKE) + vector (Qdrant via AI service) dengan bobot 60% vector, 40% keyword, bonus 1.2x jika muncul di kedua sumber.
- Middleware `EnsureFrontendRequestsAreStateful` ditambahkan eksplisit untuk CSRF protection.
- CORS di-restrict ke origins, methods, dan headers eksplisit; wildcard dihapus.
- Default `.env.example`: `APP_DEBUG=false`, `LOG_LEVEL=warning`, `SESSION_SECURE_COOKIE=true`, `SESSION_EXPIRE_ON_CLOSE=true`.

## Domain Map

```text
archives
  belongsTo event
  belongsTo archive_categories
  belongsTo subcategories
  hasOne archive_files
  hasOne archive_physical_locations
  hasOne ocr_texts

storage domain
  cabinets
  racks
  archive_storage_rules
  /archive-storage-rules

retention domain
  /archives/without-location
  /archives/retention/ready
  /archives/{id}/retention/decide

ai gateway
  /ai/tools/archives/search
```

## Working Rules

- Saat mengubah fitur archive, cek konsistensi model, migration, request, controller, route, seeder, dan storage file.
- Untuk resource tunggal, utamakan `findOrFail()` atau `firstOrFail()`.
- Gunakan relationship Eloquent yang eksplisit, bukan query lepas bila relasi sudah tersedia.
- Jangan simpan file upload ke database; simpan file ke storage, metadata ke tabel relasi.
- Jika menambah cache Redis, dokumentasikan key, TTL, dan titik invalidasinya secara singkat di kode atau docs terkait.
- Jika mengubah kontrak endpoint AI gateway, cek juga `config/services.php` dan downstream payload yang diteruskan.

## File Map

```text
app/Http/Controllers/AuthController.php
  login, logout, me

app/Http/Controllers/ArchiveController.php
  CRUD archive + upload/update file archive
  auto-assign physical location via ArchiveStorageService
  delete vector from Qdrant saat destroy/retention destroyed

app/Http/Controllers/ArchivePhysicalLocationController.php
  list/show/create/update/delete physical location archive

app/Http/Controllers/EventController.php
  CRUD event (admin-only write)

app/Http/Controllers/CategoryController.php
  CRUD category (admin-only write)

app/Http/Controllers/SubcategoryController.php
  CRUD subcategory (admin-only write)

app/Http/Controllers/CabinetController.php
  CRUD cabinet (admin-only write) + sync nested racks saat create/update

app/Http/Controllers/RackController.php
  CRUD rack (admin-only write) + validasi used_capacity/capacity

app/Http/Controllers/UserController.php
  CRUD user (admin-only) + self-update profile + reset password + search/filter user by query

app/Http/Controllers/DashboardController.php
  summary total archive, kategori, subkategori, user + daftar guru yang belum upload arsip

app/Http/Controllers/ArchiveStorageRuleController.php
  CRUD archive storage rule (admin-only)

app/Http/Controllers/AiGatewayController.php
  search archives tool endpoint

app/Services/ArchiveStorageService.php
  cari slot available + assign label_code

app/Models/User.php
  auth user + searchable fields untuk Scout

config/scout.php
  konfigurasi driver search user

bootstrap/app.php
  exception handling JSON global + auth/admin middleware alias

docs/api.md
  ringkasan endpoint aktif dan contoh payload/response
```

## Maintenance

- Jaga file ini tetap pendek.
- Pindahkan contoh payload/response detail ke `docs/api.md`.
- Jika ada perubahan arsitektur, auth, middleware, atau kontrak endpoint, update `AGENTS.md` dan `docs/api.md` di task yang sama.
