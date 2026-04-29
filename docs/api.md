# API Docs

Base URL lokal:

```text
http://localhost:8000/api/v1
```

## Konvensi Umum

- Response JSON mengikuti pola `status`, `message`, lalu opsional `data`, `errors`, `trace_id`.
- Auth API aktif memakai Laravel Sanctum stateful berbasis session cookie.
- Pencarian user memakai Laravel Scout; konfigurasi default repo saat ini memakai driver `collection`.
- Frontend SPA atau first-party perlu memanggil `GET /sanctum/csrf-cookie` sebelum `POST /api/v1/auth/login`.
- Request terproteksi dikirim dengan cookie session dan header CSRF, bukan bearer token.
- Upload file archive, OCR, dan PDF native memakai `multipart/form-data`.
- Global error JSON yang sudah ditangani konsisten untuk `401`, `403`, `405`, `422`, dan `429`.

## Role

- `admin`: CRUD master data, user, dan `archive-storage-rules`.
- `guru`: read master data, full CRUD archive, akses dashboard, akses AI gateway, update profil sendiri.

## Ringkasan Endpoint

### Auth

| Method | Endpoint | Auth | Keterangan |
|---|---|---|---|
| `POST` | `/auth/login` | Tidak | Login, throttle `5 request/menit` |
| `POST` | `/auth/logout` | Ya | Logout session aktif |
| `GET` | `/auth/me` | Ya | Profil user aktif |
| `POST` | `/auth/devlogin` | Tidak | Endpoint debug sementara berbasis `web` middleware |
| `GET` | `/auth/devme` | Ya | Endpoint debug sementara |
| `POST` | `/auth/devlogout` | Ya | Endpoint debug sementara |

### Archive

| Method | Endpoint | Auth | Keterangan |
|---|---|---|---|
| `GET` | `/archives` | Ya | List archive, `all=true` untuk tanpa pagination |
| `POST` | `/archives` | Ya | Create archive + upload file |
| `GET` | `/archives/without-location` | Ya | List arsip yang belum punya lokasi fisik |
| `GET` | `/archives/physical-locations` | Ya | List semua lokasi fisik arsip |
| `GET` | `/archives/retention/ready` | Ya | List arsip dengan `retention_status=ready_for_destruction` |
| `GET` | `/archives/{id}` | Ya | Detail archive |
| `PUT` | `/archives/{id}` | Ya | Update archive, file opsional |
| `DELETE` | `/archives/{id}` | Ya | Hapus archive |
| `GET` | `/archives/{id}/physical-locations` | Ya | Detail lokasi fisik archive |
| `POST` | `/archives/{id}/physical-locations` | Ya | Buat lokasi fisik manual |
| `PUT` | `/archives/{id}/physical-locations` | Ya | Ubah lokasi fisik |
| `DELETE` | `/archives/{id}/physical-locations` | Ya | Hapus lokasi fisik |
| `PATCH` | `/archives/{id}/retention/decide` | Ya | Simpan keputusan retensi |

### Master Data

| Method | Endpoint | Auth | Keterangan |
|---|---|---|---|
| `GET` | `/events` | Ya | List event |
| `GET` | `/events/{id}` | Ya | Detail event |
| `POST` | `/events` | Admin | Create event |
| `PUT` | `/events/{id}` | Admin | Update event |
| `DELETE` | `/events/{id}` | Admin | Hapus event |
| `GET` | `/categories` | Ya | List kategori |
| `GET` | `/categories/{id}` | Ya | Detail kategori |
| `POST` | `/categories` | Admin | Create kategori |
| `PUT` | `/categories/{id}` | Admin | Update kategori |
| `DELETE` | `/categories/{id}` | Admin | Hapus kategori |
| `GET` | `/subcategories` | Ya | List subkategori |
| `GET` | `/subcategories/{id}` | Ya | Detail subkategori |
| `POST` | `/subcategories` | Admin | Create subkategori |
| `PUT` | `/subcategories/{id}` | Admin | Update subkategori |
| `DELETE` | `/subcategories/{id}` | Admin | Hapus subkategori |
| `GET` | `/cabinets` | Ya | List lemari |
| `GET` | `/cabinets/{id}` | Ya | Detail lemari |
| `POST` | `/cabinets` | Admin | Create lemari |
| `PUT` | `/cabinets/{id}` | Admin | Update lemari |
| `DELETE` | `/cabinets/{id}` | Admin | Hapus lemari |
| `GET` | `/racks` | Ya | List rak |
| `GET` | `/racks/{id}` | Ya | Detail rak |
| `POST` | `/racks` | Admin | Create rak |
| `PUT` | `/racks/{id}` | Admin | Update rak |
| `DELETE` | `/racks/{id}` | Admin | Hapus rak |

### User

| Method | Endpoint | Auth | Keterangan |
|---|---|---|---|
| `GET` | `/users` | Admin | List user, mendukung query search `q`, filter role, dan urut `id` ascending |
| `POST` | `/users` | Admin | Create user |
| `GET` | `/users/{id}` | Ya | Detail user |
| `PUT` | `/users/{id}` | Admin | Update user |
| `DELETE` | `/users/{id}` | Admin | Hapus user |
| `PUT` | `/users/{id}/reset-password` | Admin | Reset password user |
| `PUT` | `/users/me` | Ya | Update profil sendiri |

### Archive Storage Rules

| Method | Endpoint | Auth | Keterangan |
|---|---|---|---|
| `GET` | `/archive-storage-rules` | Admin | List rule penyimpanan arsip dengan relasi `category`, `subcategory`, `cabinet` |
| `POST` | `/archive-storage-rules` | Admin | Create rule penyimpanan arsip |
| `GET` | `/archive-storage-rules/{id}` | Admin | Detail 1 rule penyimpanan arsip |
| `PATCH` | `/archive-storage-rules/{id}` | Admin | Update sebagian rule penyimpanan arsip |
| `DELETE` | `/archive-storage-rules/{id}` | Admin | Hapus rule penyimpanan arsip |

### Dashboard

| Method | Endpoint | Auth | Keterangan |
|---|---|---|---|
| `GET` | `/dashboard` | Ya | Ambil total arsip, kategori, subkategori, user |

### AI Gateway

| Method | Endpoint | Auth | Keterangan |
|---|---|---|---|
| `GET` | `/ai/health` | Ya | Status upstream AI service |
| `POST` | `/ai/chat/ask` | Ya | Chat ke AI service |
| `POST` | `/ai/ocr/extract` | Ya | OCR file gambar |
| `POST` | `/ai/pdf/extract-native` | Ya | Ekstraksi teks PDF native |

## Detail Endpoint Penting

## 1. Login

```http
POST /api/v1/auth/login
```

Prasyarat:

```http
GET /sanctum/csrf-cookie
```

Body:

| Key | Tipe | Wajib | Aturan |
|---|---|---|---|
| `username` | `string` | Ya | max `120` |
| `password` | `string` | Ya | tidak boleh kosong |

Response sukses:

```json
{
  "status": "success",
  "message": "Login successful",
  "data": {
    "id": 1,
    "name": "Admin Sekolah",
    "username": "admin",
    "role": "admin",
    "last_login_at": "2026-04-23T10:15:00.000000Z",
    "created_at": "2026-04-11T16:50:44.000000Z",
    "updated_at": "2026-04-23T10:15:00.000000Z"
  }
}
```

Kemungkinan error:

- `401` bila kredensial salah
- `422` bila body tidak lolos validasi
- `429` bila melewati limit login

## 2. Create Archive

```http
POST /api/v1/archives
```

Body `multipart/form-data`:

| Key | Tipe | Wajib | Aturan |
|---|---|---|---|
| `title` | `string` | Ya | string |
| `year` | `integer` | Ya | integer |
| `notes` | `string` | Tidak | nullable |
| `file` | `file` | Ya | `pdf`, `doc`, `docx`, `xls`, `xlsx`, max `10240 KB` |
| `event_id` | `integer` | Tidak | nullable, `exists:events,id` |
| `category_id` | `integer` | Ya | `exists:archive_categories,id` |
| `subcategory_id` | `integer` | Tidak | nullable, `exists:subcategories,id` |
| `uploader` | `integer` | Tidak | nullable, `exists:users,id` |

Perilaku:

- File disimpan ke disk `public` pada path `uploads/<slug>.<ext>`.
- Archive dibuat dengan `status=uploaded`.
- `retention_due_date` otomatis diisi ke awal tahun arsip + 10 tahun.
- `retention_status` awal adalah `active`.
- Jika category sudah punya subcategory, maka `subcategory_id` wajib diisi.
- Sistem mencoba auto-assign lokasi fisik melalui `ArchiveStorageService`.

## 3. Update Archive

```http
PUT /api/v1/archives/{id}
```

Body mendukung partial update.

Perilaku:

- Jika file diganti, metadata file lama dihapus dan file fisik lama ikut dibersihkan setelah transaksi sukses.
- Jika file baru diunggah, `status` dipaksa menjadi `uploaded`.
- Endpoint ini tidak otomatis menghitung ulang lokasi fisik.

## 4. Arsip Tanpa Lokasi

```http
GET /api/v1/archives/without-location
```

Mengembalikan semua archive yang belum punya relasi `physicalLocation`.

## 5. Physical Location Archive

### List semua physical location

```http
GET /api/v1/archives/physical-locations
```

Query:

| Key | Tipe | Default | Keterangan |
|---|---|---|---|
| `all` | `boolean` | `false` | bila `true`, tanpa pagination |

Relasi yang dimuat:

- `archive.files`
- `cabinet`
- `rack`

### Detail physical location per archive

```http
GET /api/v1/archives/{id}/physical-locations
```

### Create physical location manual

```http
POST /api/v1/archives/{id}/physical-locations
```

Body:

| Key | Tipe | Wajib | Aturan |
|---|---|---|---|
| `cabinet_id` | `integer` | Ya | `exists:cabinets,id` |
| `rack_id` | `integer` | Ya | `exists:racks,id` |
| `slot_number` | `integer` | Ya | min `1` |
| `notes_physical_location` | `string` | Tidak | nullable |

Perilaku:

- `label_code` dibentuk otomatis dengan format `L{cabinet_id}-R{rack_id}-S{slot_number}`.
- Bila archive sudah punya physical location, endpoint mengembalikan `422`.

### Update physical location

```http
PUT /api/v1/archives/{id}/physical-locations
```

Field partial update:

- `cabinet_id`
- `rack_id`
- `slot_number`
- `notes_physical_location`

### Delete physical location

```http
DELETE /api/v1/archives/{id}/physical-locations
```

## 6. Retention

### Arsip siap pemusnahan

```http
GET /api/v1/archives/retention/ready
```

Mengembalikan archive dengan `retention_status=ready_for_destruction` beserta relasi `category`, `subcategory`, dan `files`.

### Simpan keputusan retensi

```http
PATCH /api/v1/archives/{id}/retention/decide
```

Body:

| Key | Tipe | Wajib | Aturan |
|---|---|---|---|
| `retention_status` | `string` | Ya | `destroyed`, `retained`, `active` |
| `retention_note` | `string` | Tidak | nullable |
| `retention_due_date` | `date` | Tidak | nullable |

Perilaku:

- `retention_decided_at` diisi `now()`.
- `retention_decided_by` diisi user login.
- Jika `retention_status=destroyed`, file fisik arsip di disk `public` dihapus dan row `archive_files` ikut dihapus.
- Jika `retention_status=active`, `retention_due_date` boleh diperbarui dari payload.

## 7. AI Gateway

Semua endpoint AI gateway memakai auth Sanctum dan dapat meneruskan `trace_id` atau header `X-Trace-Id`.

### Chat Ask

```http
POST /api/v1/ai/chat/ask
```

Body:

| Key | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `message` | `string` | Ya | prompt user |
| `context` | `mixed` | Tidak | diteruskan apa adanya |
| `use_search` | `boolean` | Tidak | default `false` |

### OCR Extract

```http
POST /api/v1/ai/ocr/extract
```

Body:

| Key | Tipe | Wajib | Aturan |
|---|---|---|---|
| `file` | `file` | Ya | `jpg`, `jpeg`, `png`, `webp`, `bmp`, `tiff`, `tif` |

### PDF Extract Native

```http
POST /api/v1/ai/pdf/extract-native
```

Body:

| Key | Tipe | Wajib | Aturan |
|---|---|---|---|
| `file` | `file` | Ya | `pdf` |

## 8. Archive Storage Rules

Semua endpoint `archive-storage-rules` memakai middleware `auth:sanctum` dan `admin`.

### List rule penyimpanan arsip

```http
GET /api/v1/archive-storage-rules
```

Perilaku:

- Response `data` berupa pagination 10 item per halaman.
- Setiap item memuat relasi `category`, `subcategory`, dan `cabinet`.

### Create rule penyimpanan arsip

```http
POST /api/v1/archive-storage-rules
```

Body:

| Key | Tipe | Wajib | Aturan |
|---|---|---|---|
| `category_id` | `integer` | Ya | `exists:archive_categories,id` |
| `subcategory_id` | `integer` | Kondisional | `nullable`, `exists:subcategories,id`, harus milik `category_id` yang dipilih |
| `cabinet_id` | `integer` | Ya | `exists:cabinets,id` |
| `priority` | `integer` | Ya | unik di `archive_storage_rules` |

Aturan `subcategory_id`:

- Jika kategori punya `has_subcategory=true`, `subcategory_id` wajib diisi.
- Jika kategori punya `has_subcategory=false`, `subcategory_id` harus kosong.

Perilaku:

- Response `201` mengembalikan row `archive_storage_rules` yang baru dibuat pada field `data`.

### Detail rule penyimpanan arsip

```http
GET /api/v1/archive-storage-rules/{id}
```

Perilaku:

- Response memuat relasi `category`, `subcategory`, dan `cabinet`.

### Update rule penyimpanan arsip

```http
PATCH /api/v1/archive-storage-rules/{id}
```

Body mendukung partial update.

Field yang bisa diubah:

- `category_id`
- `subcategory_id`
- `cabinet_id`
- `priority`

Aturan aktif:

- `priority` tetap unik, dengan pengecualian untuk row yang sedang di-update.
- Jika `category_id` dikirim, aturan `subcategory_id` mengikuti `has_subcategory` kategori tersebut.
- Jika `subcategory_id` dikirim, nilainya harus berasal dari kategori yang sama.

Perilaku:

- Response `200` mengembalikan row hasil update pada field `data`.

### Hapus rule penyimpanan arsip

```http
DELETE /api/v1/archive-storage-rules/{id}
```

Perilaku:

- Response hanya mengembalikan `status` dan `message`.

## 9. User Notes

### List user

```http
GET /api/v1/users
```

Query:

| Key | Tipe | Default | Keterangan |
|---|---|---|---|
| `q` | `string` | - | cari user berdasarkan index Scout |
| `role` | `string` | - | filter exact role, mis. `admin` atau `guru` |

Perilaku:

- Jika `q` tidak dikirim, endpoint mengembalikan pagination user biasa, 10 item per halaman.
- Endpoint memakai `User::search($q ?? '')` dari Laravel Scout untuk query pencarian.
- Jika `q` dikirim, endpoint mencari pada field `name`, `username`, `subject`, dan `position`.
- Jika `role` dikirim, endpoint memfilter exact match pada kolom `role`.
- `q` dan filter role bisa dipakai bersamaan.
- Hasil list diurutkan dari `id` terkecil.
- Jika hasil filter atau pencarian kosong, endpoint mengembalikan `404` dengan message `User tidak ditemukan`.

### Create user

```http
POST /api/v1/users
```

Body:

| Key | Tipe | Wajib | Aturan |
|---|---|---|---|
| `name` | `string` | Ya | max `200` |
| `subject` | `string` | Ya | max `200` |
| `position` | `string` | Ya | max `200` |
| `username` | `string` | Ya | max `120` |
| `password` | `string` | Ya | min `8`, huruf besar, huruf kecil, angka |
| `role` | `string` | Ya | `guru` atau `admin` |

Perilaku:

- Response `201` pada `data` berisi model user yang baru dibuat, bukan payload request mentah.
