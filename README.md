# spentaru-archive-backend

Backend Laravel 13 untuk sistem arsip sekolah dengan auth Sanctum stateful berbasis session cookie, manajemen arsip, lokasi fisik arsip, master data, dashboard, AI gateway, workflow retensi arsip, serta fitur search, filter, dan sort pada endpoint list utama.

## Stack

- PHP 8.3+
- Laravel 13
- MySQL
- Laravel Sanctum
- Laravel Scout
- Redis opsional untuk cache, queue, atau lock

## Fitur Utama

- Auth API berbasis session cookie Sanctum untuk SPA atau first-party frontend
- CRUD arsip + upload file ke disk `public`
- Auto-assign lokasi fisik arsip via `ArchiveStorageService`
- CRUD rule penempatan arsip via `archive-storage-rules`
- CRUD master data: event, kategori, subkategori, lemari, rak, user
- Search text via query `q` pada endpoint list `users`, `archives`, `events`, dan `archives/physical-locations`
- Filter dan sort dinamis via query string pada endpoint list `archives`, `events`, dan `archives/physical-locations`
- Dashboard ringkas untuk total arsip, kategori, subkategori, dan user
- AI gateway untuk chat, OCR gambar, dan ekstraksi PDF native
- Workflow retensi arsip: arsip tanpa lokasi, arsip siap pemusnahan, dan keputusan retensi

## Role

- `admin`: CRUD master data, user, dan `archive-storage-rules`
- `guru`: read master data, CRUD archive, akses dashboard, akses AI gateway, update profil sendiri

## Menjalankan Project

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
php artisan serve
```

Jika ingin fitur search berbasis Scout sinkron untuk driver non-`collection`, import index setelah migrate:

```bash
php artisan scout:import "App\\Models\\User"
php artisan scout:import "App\\Models\\Archive"
php artisan scout:import "App\\Models\\Event"
php artisan scout:import "App\\Models\\ArchivePhysicalLocation"
```

Base URL lokal:

```text
http://localhost:8000
```

Base URL API:

```text
http://localhost:8000/api/v1
```

## Auth Flow

Untuk frontend SPA atau first-party:

1. Panggil `GET /sanctum/csrf-cookie`
2. Login ke `POST /api/v1/auth/login`
3. Kirim cookie session + header CSRF untuk request terproteksi berikutnya

Endpoint debug sementara juga ada di `api/v1/auth/devlogin`, `api/v1/auth/devme`, dan `api/v1/auth/devlogout`.

## Command Penting

- `php artisan route:list`
- `composer test`
- `./vendor/bin/pint`
- `php artisan config:clear`

## Catatan Search, Filter, dan Sort

- Dependency `laravel/scout` sudah terpasang untuk fitur search berbasis query `q`.
- Default `SCOUT_DRIVER` saat ini adalah `collection`, dengan konfigurasi di `config/scout.php`.
- Model yang saat ini sudah searchable: `User`, `Archive`, `Event`, dan `ArchivePhysicalLocation`.
- Endpoint list yang sudah mendukung `q`:
  - `GET /api/v1/users`
  - `GET /api/v1/archives`
  - `GET /api/v1/events`
  - `GET /api/v1/archives/physical-locations`
- Endpoint list yang sudah mendukung filter dan sort query string:
  - `GET /api/v1/archives`
  - `GET /api/v1/events`
  - `GET /api/v1/archives/physical-locations`
- Search dipakai untuk pencarian teks bebas sederhana.
- Filter dipakai untuk penyaringan field spesifik, mis. exact match, range, atau contains.
- Sort dipakai untuk urutan hasil, termasuk multi-sort pada endpoint yang mendukung.
- Detail field searchable, filterable, sortable, dan contoh query ada di [docs/api.md](/home/ezzar/project_coding/spentaru-archive-backend/docs/api.md).

## Dokumen

- Ringkasan endpoint aktif: [docs/api.md](/home/ezzar/project_coding/spentaru-archive-backend/docs/api.md)
- Panduan agent repo: [AGENTS.md](/home/ezzar/project_coding/spentaru-archive-backend/AGENTS.md)
