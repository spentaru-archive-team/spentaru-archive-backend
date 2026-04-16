# API Docs

Base URL lokal:

```text
http://127.0.0.1:8000/api/v1
```

Konvensi umum:
- Response API mengikuti pola `status`, `message`, lalu opsional `data`.
- Auth API memakai bearer token Sanctum stateless.
- Upload file arsip memakai `multipart/form-data`.

Role-based access:
- **Admin** (`admin`): full access ke semua endpoint.
- **Guru** (`guru`): READ master data + CRUD archives + self-update profile via `/users/me`.

## 1. Login

Endpoint:

```http
POST /api/v1/auth/login
```

Header:

```http
Accept: application/json
Content-Type: application/json
```

Body:

| Key | Tipe | Wajib | Aturan |
|---|---|---|---|
| `username` | `string` | Ya | username user |
| `password` | `string` | Ya | tidak boleh kosong |

Contoh request:

```json
{
  "username": "admin",
  "password": "password"
}
```

Contoh response sukses:

```json
{
  "status": "success",
  "message": "Login successful",
  "data": {
    "id": 1,
    "username": "admin",
    "role": "admin",
    "created_at": "2026-04-11T16:50:44.000000Z",
    "updated_at": "2026-04-11T16:50:44.000000Z",
    "token": "9|exampletoken"
  }
}
```

Kemungkinan error:
- `401 Unauthorized` bila kredensial salah
- `422 Unprocessable Entity` bila body tidak lolos validasi

## 2. Logout

Endpoint:

```http
POST /api/v1/auth/logout
```

Header:

```http
Accept: application/json
Authorization: Bearer <token>
```

Contoh response sukses:

```json
{
  "status": "success",
  "message": "Logout sukses"
}
```

## 3. Me

Endpoint terdaftar:

```http
GET /api/v1/auth/me
```

Header:

```http
Accept: application/json
Authorization: Bearer <token>
```

Contoh response sukses:

```json
{
  "status": "success",
  "message": "Profil pengguna berhasil diambil",
  "data": {
    "id": 1,
    "name": "Admin Sekolah",
    "email": "admin@example.com",
    "role": "admin",
    "created_at": "2026-04-14T09:00:00.000000Z",
    "updated_at": "2026-04-14T09:00:00.000000Z"
  }
}
```

## 4. List Archive

Endpoint:

```http
GET /api/v1/archives
```

Header:

```http
Accept: application/json
Authorization: Bearer <token>
```

Deskripsi:
- Mengembalikan data arsip dalam bentuk pagination Laravel.
- Saat ini memakai `paginate(10)`.

Contoh response sukses:

```json
{
  "status": "success",
  "message": "sukses mengambil archive",
  "data": {
    "current_page": 1,
    "data": []
  }
}
```

## 5. Detail Archive

Endpoint:

```http
GET /api/v1/archives/{id}
```

Header:

```http
Accept: application/json
Authorization: Bearer <token>
```

Deskripsi:
- Mengembalikan detail archive beserta relasi:
  - `event`
  - `category`
  - `subcategory`
  - `files`
  - `physicalLocation.cabinet`
  - `physicalLocation.rack`

## 6. Create Archive

Endpoint:

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
| `event_id` | `integer` | Tidak | nullable, min `0` |
| `category_id` | `integer` | Ya | min `0` |
| `subcategory_id` | `integer` | Tidak | nullable, min `0` |

Deskripsi implementasi saat ini:
- File disimpan ke disk `public` pada path relatif `uploads/<nama_file_sanitized>`.
- Row `archives` dibuat dengan status `uploaded`.
- Metadata file disimpan melalui relasi `archive_files`.
- **Physical location auto-assigned** berdasarkan `category_id` melalui `ArchiveStorageService`:
  - Cek `archive_storage_rules` untuk kategori
  - Cari rack dengan slot available
  - Generate `label_code` format: `L{cabinet_id}-R{rack_number}-S{slot}`

Contoh response sukses:

```json
{
  "status": "success",
  "message": "sukses menyimpan archive",
  "data": {
    "id": 1,
    "title": "Arsip Rapat",
    "status": "uploaded",
    "files": {
      "file_name": "arsip_rapat_xxx_20260414120000000.pdf",
      "file_type": "pdf",
      "file_url": "/storage/uploads/arsip_rapat_xxx_20260414120000000.pdf"
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

Endpoint:

```http
PUT /api/v1/archives/{id}
```

Header:

```http
Accept: application/json
Authorization: Bearer <token>
```

Body:
- Mendukung partial update.
- Bila menyertakan file, request harus `multipart/form-data`.

Aturan validasi saat ini:
- `title`: `sometimes|required|string`
- `year`: `sometimes|required|integer`
- `notes`: `sometimes|nullable|string`
- `file`: `nullable|file|mimes:pdf,doc,docx,xls,xlsx|max:10240`
- `event_id`: `sometimes|nullable|integer|min:0`
- `category_id`: `sometimes|required|integer|min:0`
- `subcategory_id`: `sometimes|nullable|integer|min:0`

Catatan implementasi:
- Bila file baru dikirim, file lama dihapus dari storage setelah update berhasil.
- Metadata file diperbarui lewat `updateOrCreate()` pada relasi `files`.

## 8. Show Archive Physical Location

Endpoint:

```http
GET /api/v1/archives/{id}/physical-location
```

Header:

```http
Accept: application/json
Authorization: Bearer <token>
```

## 9. Create Archive Physical Location

Endpoint:

```http
POST /api/v1/archives/{id}/physical-location
```

Body:
- `cabinet_id`: `required|integer|min:0|exists:cabinets,id`
- `rack_id`: `required|integer|min:0|exists:racks,id`
- `slot_number`: `required|integer|min:1`
- `notes_physical_location`: `nullable|string`

Catatan implementasi:
- Physical location hanya boleh dibuat satu kali per archive.
- `label_code` dihitung otomatis dari kombinasi `cabinet_id`, `rack_id`, dan `slot_number`.

## 10. Update Archive Physical Location

Endpoint:

```http
PUT /api/v1/archives/{id}/physical-location
```

Body:
- `cabinet_id`: `sometimes|required|integer|min:0|exists:cabinets,id`
- `rack_id`: `sometimes|required|integer|min:0|exists:racks,id`
- `slot_number`: `sometimes|required|integer|min:1`
- `notes_physical_location`: `sometimes|nullable|string`

Catatan implementasi:
- Mendukung partial update.
- Jika nilai sama dengan database, field tidak diubah.
- `label_code` dihitung ulang otomatis dari kombinasi akhir `cabinet_id`, `rack_id`, dan `slot_number`.

## 11. Delete Archive Physical Location

Endpoint:

```http
DELETE /api/v1/archives/{id}/physical-location
```

## 12. Delete Archive

Endpoint:

```http
DELETE /api/v1/archives/{id}
```

Header:

```http
Accept: application/json
Authorization: Bearer <token>
```

Deskripsi:
- Menghapus row archive.
- File fisik terkait juga dihapus dari disk `public` setelah delete berhasil.

Contoh response sukses:

```json
{
  "status": "success",
  "message": "sukses menghapus archive"
}
```

## 13. Events

CRUD untuk event. **Admin-only untuk write operations.**

| Method | Endpoint | Auth | Keterangan |
|--------|----------|------|------------|
| GET | `/events` | sanctum | List semua event (paginated) |
| GET | `/events/{id}` | sanctum | Detail event + relasi user & archives |
| POST | `/events` | sanctum+admin | Buat event baru |
| PUT | `/events/{id}` | sanctum+admin | Update event |
| DELETE | `/events/{id}` | sanctum+admin | Hapus event (gagal kalau ada archives) |

Body (POST/PUT):
- `title`: required|string
- `description`: nullable|string
- `date`: required|date
- `status`: required|in:ongoing,done

## 14. Categories

CRUD untuk kategori arsip. **Admin-only untuk write operations.**

| Method | Endpoint | Auth | Keterangan |
|--------|----------|------|------------|
| GET | `/categories` | sanctum | List semua kategori + subcategories |
| GET | `/categories/{id}` | sanctum | Detail kategori + relasi |
| POST | `/categories` | sanctum+admin | Buat kategori baru |
| PUT | `/categories/{id}` | sanctum+admin | Update kategori |
| DELETE | `/categories/{id}` | sanctum+admin | Hapus (cascade subcategories, gagal kalau ada archives) |

Body (POST/PUT):
- `name`: required|string|unique
- `description`: nullable|string

## 15. Subcategories

CRUD untuk subkategori. **Admin-only untuk write operations.**

| Method | Endpoint | Auth | Keterangan |
|--------|----------|------|------------|
| GET | `/subcategories` | sanctum | List subkategori |
| GET | `/subcategories?category_id=X` | sanctum | Filter by kategori |
| GET | `/subcategories/{id}` | sanctum | Detail subkategori |
| POST | `/subcategories` | sanctum+admin | Buat subkategori |
| PUT | `/subcategories/{id}` | sanctum+admin | Update subkategori |
| DELETE | `/subcategories/{id}` | sanctum+admin | Hapus (gagal kalau ada archives) |

Body (POST/PUT):
- `category_id`: required|exists:archive_categories
- `name`: required|string

## 16. Cabinets

CRUD untuk lemari. **Admin-only untuk write operations.**

| Method | Endpoint | Auth | Keterangan |
|--------|----------|------|------------|
| GET | `/cabinets` | sanctum | List lemari + racks |
| GET | `/cabinets/{id}` | sanctum | Detail lemari + racks |
| POST | `/cabinets` | sanctum+admin | Buat lemari |
| PUT | `/cabinets/{id}` | sanctum+admin | Update lemari |
| DELETE | `/cabinets/{id}` | sanctum+admin | Hapus + cascade racks (gagal kalau ada archives di rack) |

Body (POST/PUT):
- `name`: required|string|unique

## 17. Racks

CRUD untuk rak. **Admin-only untuk write operations.**

| Method | Endpoint | Auth | Keterangan |
|--------|----------|------|------------|
| GET | `/racks` | sanctum | List rak + cabinet |
| GET | `/racks?cabinet_id=X` | sanctum | Filter by lemari |
| GET | `/racks/{id}` | sanctum | Detail rak + physical locations |
| POST | `/racks` | sanctum+admin | Buat rak |
| PUT | `/racks/{id}` | sanctum+admin | Update rak |
| DELETE | `/racks/{id}` | sanctum+admin | Hapus (gagal kalau ada archives) |

Body (POST/PUT):
- `cabinet_id`: required|exists:cabinets
- `rack_number`: required|integer|min:1
- `capacity`: required|integer|min:1|max:100

## 18. Users - Self Update Profile

Endpoint untuk user update profil sendiri.

```http
PUT /api/v1/users/me
```

Header:

```http
Accept: application/json
Authorization: Bearer <token>
```

Body:
- `name`: sometimes|string
- `username`: sometimes|string|unique:users,username
- `password`: sometimes|string|min:8|letters|numbers|mixedCase

Catatan:
- User tidak bisa mengubah `role` sendiri.
- Validasi unique username mengabaikan user sendiri.

Contoh response sukses:

```json
{
  "status": "success",
  "message": "sukses mengupdate profil",
  "data": {
    "id": 2,
    "name": "Guru User",
    "username": "guru_updated",
    "role": "guru",
    "created_at": "2026-04-16T21:53:38.000000Z",
    "updated_at": "2026-04-16T22:03:21.000000Z"
  }
}
```

## Contoh cURL

Login:

```bash
curl -X POST http://127.0.0.1:8000/api/v1/auth/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "username": "admin",
    "password": "password"
  }'
```

Create archive:

```bash
curl -X POST http://127.0.0.1:8000/api/v1/archives \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <token>" \
  -F "title=Arsip Rapat" \
  -F "year=2026" \
  -F "notes=Dokumen rapat tahunan" \
  -F "category_id=2" \
  -F "subcategory_id=3" \
  -F "event_id=1" \
  -F "file=@/path/ke/file/contoh.pdf"
```

Create archive physical location:

```bash
curl -X POST http://127.0.0.1:8000/api/v1/archives/1/physical-location \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "cabinet_id": 1,
    "rack_id": 2,
    "slot_number": 3,
    "notes_physical_location": "Rak arsip rapat"
  }'
```

Detail archive:

```bash
curl -X GET http://127.0.0.1:8000/api/v1/archives/1 \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <token>"
```
