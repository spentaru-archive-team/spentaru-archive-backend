# API Docs

Base URL lokal:

```text
http://127.0.0.1:8000/api/v1
```

Konvensi umum:
- Response API mengikuti pola `status`, `message`, lalu opsional `data`.
- Seluruh endpoint `archives` saat ini diproteksi `auth:sanctum`.
- Auth API memakai bearer token Sanctum stateless.
- Upload file arsip memakai `multipart/form-data`.

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
