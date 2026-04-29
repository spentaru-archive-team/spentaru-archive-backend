# spentaru-archive-backend

Backend Laravel 13 untuk sistem arsip sekolah dengan auth Sanctum stateful berbasis session cookie, manajemen arsip, lokasi fisik arsip, master data, dashboard, AI gateway, workflow retensi arsip, dan pencarian user berbasis Laravel Scout.

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
- Pencarian user admin via query `q` dan filter role `role` pada endpoint `GET /api/v1/users`
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

Jika ingin fitur pencarian user berbasis Scout sinkron untuk driver non-`collection`, import index setelah migrate:

```bash
php artisan scout:import "App\\Models\\User"
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

## Catatan Search User

- Dependency `laravel/scout` sudah terpasang.
- Default `SCOUT_DRIVER` saat ini adalah `collection`, dengan konfigurasi di `config/scout.php`.
- Model `User` sudah memakai trait `Searchable`.
- Field searchable aktif untuk user: `name`, `username`, `subject`, dan `position`.
- Endpoint list user juga mendukung filter exact `role` dan hasil diurutkan dari `id` terkecil.
- Migration terbaru menambahkan fulltext index ke kolom `name`, `subject`, `position`, dan `username`.

## Dokumen

- Ringkasan endpoint aktif: [docs/api.md](/home/ezzar/project_coding/spentaru-archive-backend/docs/api.md)
- Panduan agent repo: [AGENTS.md](/home/ezzar/project_coding/spentaru-archive-backend/AGENTS.md)
