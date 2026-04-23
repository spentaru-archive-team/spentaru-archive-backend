# API Docs

Base URL lokal:

```text
http://127.0.0.1:8000/api/v1
```

## Konvensi Umum

- Response JSON mengikuti pola `status`, `message`, lalu opsional `data`, `errors`, `trace_id`.
- Auth API memakai Laravel Sanctum stateful berbasis session cookie.
- Frontend SPA/first-party harus panggil `GET /sanctum/csrf-cookie` sebelum login.
- Endpoint terproteksi mengandalkan cookie session + header CSRF untuk request stateful lintas origin.
- Upload file arsip, OCR, dan PDF native memakai `multipart/form-data`.
- Error global yang sudah ditangani konsisten:
  - `401` unauthenticated
  - `403` forbidden
  - `405` method not allowed
  - `422` validasi gagal
  - `429` terlalu banyak request

## Role-Based Access

- `admin`: full access ke semua endpoint.
- `guru`: read master data, full CRUD archive, akses dashboard, akses AI gateway, dan update profil sendiri.

## Ringkasan Endpoint

### Auth

| Method | Endpoint | Auth | Keterangan |
|---|---|---|---|
| `POST` | `/auth/login` | Tidak | Login, throttle `5 request/menit` |
| `POST` | `/auth/logout` | Ya | Logout session aktif |
| `GET` | `/auth/me` | Ya | Profil user aktif |

### Archive

| Method | Endpoint | Auth | Keterangan |
|---|---|---|---|
| `GET` | `/archives` | Ya | List archive, `all=true` untuk tanpa pagination |
| `POST` | `/archives` | Ya | Create archive + upload file |
| `GET` | `/archives/{id}` | Ya | Detail archive |
| `PUT` | `/archives/{id}` | Ya | Update archive, file opsional |
| `DELETE` | `/archives/{id}` | Ya | Hapus archive |

### Archive Physical Location

| Method | Endpoint | Auth | Keterangan |
|---|---|---|---|
| `GET` | `/archives/physical-location` | Ya | List semua physical location |
| `GET` | `/archives/{id}/physical-location` | Ya | Detail physical location per archive |
| `POST` | `/archives/{id}/physical-location` | Ya | Buat physical location manual |
| `PUT` | `/archives/{id}/physical-location` | Ya | Ubah physical location |
| `DELETE` | `/archives/{id}/physical-location` | Ya | Hapus physical location |

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
| `GET` | `/users` | Admin | List user |
| `POST` | `/users` | Admin | Create user |
| `GET` | `/users/{id}` | Ya | Detail user |
| `PUT` | `/users/{id}` | Admin | Update user |
| `DELETE` | `/users/{id}` | Admin | Hapus user |
| `PUT` | `/users/{id}/reset-password` | Admin | Reset password user |
| `PUT` | `/users/me` | Ya | Update profil sendiri |

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

Header:

```http
Accept: application/json
Content-Type: application/json
```

Prasyarat untuk SPA/first-party frontend:

```http
GET /sanctum/csrf-cookie
```

Request login dan request stateful berikutnya harus mengirim cookie hasil endpoint di atas. Jika frontend beda origin, aktifkan `withCredentials`/`credentials: "include"` dan kirim header CSRF sesuai mekanisme client yang dipakai.

Body:

| Key | Tipe | Wajib | Aturan |
|---|---|---|---|
| `username` | `string` | Ya | max `120` |
| `password` | `string` | Ya | tidak boleh kosong |

Contoh response sukses:

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
- `401 Unauthorized` bila kredensial salah
- `422 Unprocessable Entity` bila body tidak lolos validasi
- `429 Too Many Requests` bila melewati limit login

## 2. Logout

```http
POST /api/v1/auth/logout
```

Header untuk frontend lintas origin:

```http
X-XSRF-TOKEN: <csrf-token>
Cookie: XSRF-TOKEN=...; spentaru-archive-backend-session=...
```

Contoh response:

```json
{
  "status": "success",
  "message": "Logout sukses"
}
```

## 3. Me

```http
GET /api/v1/auth/me
```

Contoh response:

```json
{
  "status": "success",
  "message": "Profil pengguna berhasil diambil",
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

## 4. List Archive

```http
GET /api/v1/archives
```

Query yang didukung:

| Key | Tipe | Default | Keterangan |
|---|---|---|---|
| `all` | `boolean` | `false` | Bila `true`, mengembalikan semua data tanpa pagination |

Perilaku:
- Default memakai pagination `10` item per halaman.
- Mode paginasi memuat relasi: `event`, `category`, `subcategory`, `files`, `physicalLocation.cabinet`, `physicalLocation.rack`.
- Mode paginasi menambahkan field SQL `row_num`.

Contoh response ringkas:

```json
{
  "status": "success",
  "message": "sukses mengambil archive",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "row_num": 1,
        "title": "Arsip Rapat",
        "status": "uploaded"
      }
    ]
  }
}
```

## 5. Detail Archive

```http
GET /api/v1/archives/{id}
```

Relasi yang dimuat:
- `event`
- `category`
- `subcategory`
- `files`
- `physicalLocation.cabinet`
- `physicalLocation.rack`

## 6. Create Archive

```http
POST /api/v1/archives
```

Header:

```http
Accept: application/json
Authorization: Bearer <token>
Content-Type: multipart/form-data
```

Body:

| Key | Tipe | Wajib | Aturan |
|---|---|---|---|
| `title` | `string` | Ya | string |
| `year` | `integer` | Ya | integer |
| `notes` | `string` | Tidak | nullable |
| `file` | `file` | Ya | `pdf`, `doc`, `docx`, `xls`, `xlsx`, max `10240 KB` |
| `event_id` | `integer` | Tidak | nullable, `exists:events,id` |
| `category_id` | `integer` | Ya | `exists:archive_categories,id` |
| `subcategory_id` | `integer` | Tidak | nullable, `exists:subcategories,id` |
| `uploaded_by` | `integer` | Tidak | nullable, `exists:users,id` |

Perilaku implementasi:
- File disimpan ke disk `public` pada path relatif `uploads/<slug>.<ext>`.
- Row `archives` dibuat dengan status `uploaded`.
- Metadata file disimpan di relasi `archive_files`.
- Sistem mencoba auto-assign physical location melalui `ArchiveStorageService`.
- Bila tidak ada rule/slot yang tersedia, archive tetap tersimpan, tetapi physical location bisa `null`.

Contoh response ringkas:

```json
{
  "status": "success",
  "message": "sukses menyimpan archive",
  "data": {
    "id": 1,
    "title": "Arsip Rapat",
    "status": "uploaded",
    "files": {
      "file_name": "arsip_rapat_random_20260423101500000.pdf",
      "file_type": "pdf",
      "file_url": "/storage/uploads/arsip_rapat_random_20260423101500000.pdf"
    },
    "physical_location": {
      "cabinet_id": 1,
      "rack_id": 1,
      "slot_number": 1,
      "label_code": "L1-R1-S01"
    }
  }
}
```

## 7. Update Archive

```http
PUT /api/v1/archives/{id}
```

Body mendukung partial update.

Field valid:

| Key | Tipe | Keterangan |
|---|---|---|
| `title` | `string` | opsional |
| `year` | `integer \| null` | opsional |
| `notes` | `string \| null` | opsional |
| `file` | `file` | opsional, tipe sama seperti create |
| `event_id` | `integer \| null` | opsional |
| `category_id` | `integer` | opsional |
| `subcategory_id` | `integer \| null` | opsional |
| `uploaded_by` | `integer \| null` | opsional |

Perilaku penting:
- Jika `file` diganti, metadata file lama dihapus dan file fisik lama ikut dihapus setelah transaksi sukses.
- Jika `file` baru diunggah, `status` dipaksa menjadi `uploaded`.
- Endpoint ini tidak otomatis menghitung ulang physical location.

## 8. Delete Archive

```http
DELETE /api/v1/archives/{id}
```

Perilaku:
- Menghapus row archive beserta file fisik terkait.
- Response tidak mengembalikan `data`.

## 9. Physical Location Archive

### List semua physical location

```http
GET /api/v1/archives/physical-location
```

Relasi yang dimuat:
- `archive`
- `cabinet`
- `rack`

### Detail physical location per archive

```http
GET /api/v1/archives/{id}/physical-location
```

Jika archive belum punya physical location, mengembalikan `404`.

### Create physical location manual

```http
POST /api/v1/archives/{id}/physical-location
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
PUT /api/v1/archives/{id}/physical-location
```

Body mendukung partial update untuk:
- `cabinet_id`
- `rack_id`
- `slot_number`
- `notes_physical_location`

`label_code` dihitung ulang dari kombinasi akhir field.

### Delete physical location

```http
DELETE /api/v1/archives/{id}/physical-location
```

## 10. Event

Parameter list:

| Key | Tipe | Default | Keterangan |
|---|---|---|---|
| `all` | `boolean` | `false` | tanpa pagination |
| `per_page` | `integer` | `10` | jumlah item per halaman |

Payload create/update:

| Key | Tipe | Wajib create | Aturan |
|---|---|---|---|
| `title` | `string` | Ya | max `255` |
| `description` | `string` | Tidak | nullable |
| `date` | `date` | Ya | format tanggal valid |
| `status` | `string` | Ya | `ongoing` / `done` |

Catatan:
- Saat create, `user_id` diisi dari user login.
- Event tidak bisa dihapus bila masih punya archive.

## 11. Category

Parameter list:

| Key | Tipe | Default | Keterangan |
|---|---|---|---|
| `all` | `boolean` | `false` | tanpa pagination |
| `per_page` | `integer` | `10` | jumlah item per halaman |

Payload create:

| Key | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `name` | `string` | Ya | unique |
| `description` | `string` | Tidak | nullable |
| `subcategories` | `array` | Tidak | buat subkategori sekaligus |
| `subcategories.*.name` | `string` | Kondisional | required jika `subcategories` ada |

Catatan:
- Detail category memuat `subcategories` dan `archives`.
- Category tidak bisa dihapus bila masih punya archive.

## 12. Subcategory

Parameter list:

| Key | Tipe | Default | Keterangan |
|---|---|---|---|
| `all` | `boolean` | `false` | tanpa pagination |
| `per_page` | `integer` | `10` | jumlah item per halaman |
| `category_id` | `integer` | - | filter subkategori berdasarkan kategori |

Payload create/update:

| Key | Tipe | Wajib create | Keterangan |
|---|---|---|---|
| `category_id` | `integer` | Ya | `exists:archive_categories,id` |
| `name` | `string` | Ya | unique per kategori |

Catatan:
- Detail subcategory memuat `category` dan `archives`.
- Subcategory tidak bisa dihapus bila masih punya archive.

## 13. Cabinet

Payload create/update:

| Key | Tipe | Wajib create | Keterangan |
|---|---|---|---|
| `name` | `string` | Ya | unique |

Catatan:
- List cabinet selalu memuat relasi `racks`.
- Detail cabinet memuat `racks` yang diurutkan berdasarkan `rack_number`.
- Cabinet tidak bisa dihapus bila salah satu rack masih punya physical location.
- Saat delete cabinet sukses, semua rack di bawah cabinet ikut dihapus.

## 14. Rack

Parameter list:

| Key | Tipe | Default | Keterangan |
|---|---|---|---|
| `per_page` | `integer` | `10` | jumlah item per halaman |
| `cabinet_id` | `integer` | - | filter rack berdasarkan lemari |

Payload create/update:

| Key | Tipe | Wajib create | Keterangan |
|---|---|---|---|
| `cabinet_id` | `integer` | Ya | `exists:cabinets,id` |
| `rack_number` | `integer` | Ya | min `1`, unique per cabinet |
| `capacity` | `integer` | Ya | min `1`, max `100` |

Catatan:
- Detail rack memuat `cabinet` dan `physicalLocations`.
- Rack tidak bisa dihapus bila masih dipakai physical location.

## 15. User

### List User

Query:

| Key | Tipe | Default | Keterangan |
|---|---|---|---|
| `all` | `boolean` | `false` | tanpa pagination |

### Create User

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
| `role` | `string` | Ya | `guru` / `admin` |

### Update User

```http
PUT /api/v1/users/{id}
```

Body valid sama seperti create, tetapi `password` boleh `null`.

### Reset Password

```http
PUT /api/v1/users/{id}/reset-password
```

Body:

| Key | Tipe | Wajib | Aturan |
|---|---|---|---|
| `password` | `string` | Tidak | nullable, min `8`, huruf besar, huruf kecil, angka |

### Update Profil Sendiri

```http
PUT /api/v1/users/me
```

Body:

| Key | Tipe | Wajib | Aturan |
|---|---|---|---|
| `name` | `string` | Tidak | max `200` |
| `username` | `string` | Tidak | unique kecuali user aktif |
| `password` | `string` | Tidak | min `8`, huruf besar, huruf kecil, angka |

## 16. Dashboard

```http
GET /api/v1/dashboard
```

Contoh response:

```json
{
  "status": "success",
  "message": "sukses mengambil jumlah arsip, user, kategori, dan subkategori",
  "data": {
    "archive_total": 120,
    "archive_category_total": 8,
    "archive_subcategory_total": 21,
    "user_total": 15
  }
}
```

## 17. AI Gateway

Semua endpoint AI gateway meneruskan atau menghasilkan `trace_id` dan juga mengirim header `X-Trace-Id`.

### Health

```http
GET /api/v1/ai/health
```

Response sukses memakai:

```json
{
  "status": "success",
  "message": "sukses mengambil status AI service",
  "data": {},
  "trace_id": "uuid-or-upstream-trace-id"
}
```

Jika upstream tidak dapat dihubungi:
- `504 Gateway Timeout`

### Chat Ask

```http
POST /api/v1/ai/chat/ask
```

Header:

```http
Accept: application/json
Authorization: Bearer <token>
Content-Type: application/json
X-Trace-Id: <optional-custom-trace-id>
```

Body:

| Key | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `message` | `string` | Ya | prompt user |
| `context` | `mixed` | Tidak | context tambahan, diteruskan apa adanya |
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

Perilaku error AI gateway:
- Jika upstream mengembalikan error `4xx`, status akan diteruskan.
- Jika upstream mengembalikan status non-`4xx` yang gagal, API ini mengubahnya menjadi `502`.
- Format error:

```json
{
  "status": "error",
  "message": "AI service gagal memproses request",
  "errors": null,
  "trace_id": "uuid-or-upstream-trace-id"
}
```
