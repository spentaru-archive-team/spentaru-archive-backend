# spentaru-archive-backend

Backend Laravel 13 untuk sistem arsip sekolah dengan auth Sanctum stateless, manajemen arsip, lokasi fisik arsip, master data, dashboard, dan AI gateway.

## Stack

- PHP 8.3+
- Laravel 13
- MySQL
- Laravel Sanctum
- Redis opsional untuk cache/queue/lock

## Fitur Utama

- Auth API berbasis bearer token Sanctum
- CRUD arsip + upload file ke disk `public`
- Auto-assign lokasi fisik arsip via `ArchiveStorageService`
- CRUD master data: event, kategori, subkategori, lemari, rak, user
- Endpoint dashboard ringkas
- Proxy AI gateway untuk chat, OCR gambar, dan ekstraksi PDF native

## Role

- `admin`: CRUD semua master data dan user
- `guru`: read master data, CRUD archive, update profil sendiri

## Menjalankan Project

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
php artisan serve
```

Base URL lokal:

```text
http://localhost:8000
```

Base URL API:

```text
http://localhost:8000/api/v1
```

## Command Penting

- `php artisan route:list`
- `composer test`
- `./vendor/bin/pint`
- `php artisan config:clear`

## Dokumen

- Ringkasan endpoint aktif: [docs/api.md](/home/ezzar/project_coding/spentaru-archive-backend/docs/api.md)
- Panduan agent repo: [AGENTS.md](/home/ezzar/project_coding/spentaru-archive-backend/AGENTS.md)
