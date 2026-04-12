# API Docs

Base URL lokal:

```text
http://127.0.0.1:8000/api/v1
```

Auth yang dipakai:
- Laravel Sanctum stateless
- Gunakan header `Authorization: Bearer <token>` untuk endpoint terproteksi

## 1. Login

Endpoint:

```http
POST /api/v1/auth/login
```

Deskripsi:
- Digunakan untuk login user dan mendapatkan token Sanctum.

Header:

```http
Accept: application/json
Content-Type: application/json
```

Body yang dibutuhkan:

| Key | Tipe | Wajib | Syarat value |
|---|---|---|---|
| `email` | `string` | Ya | harus format email valid, maksimal `120` karakter |
| `password` | `string` | Ya | tidak boleh kosong |

Contoh body:

```json
{
  "email": "esmeralda76@example.net",
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
    "email": "esmeralda76@example.net",
    "role": "admin",
    "created_at": "2026-04-11T16:50:44.000000Z",
    "updated_at": "2026-04-11T16:50:44.000000Z",
    "token": "9|exampletoken"
  }
}
```

Kemungkinan error:
- `401 Unauthorized`
  Kredensial salah
- `422 Unprocessable Entity`
  Format body tidak lolos validasi

## 2. Logout

Endpoint:

```http
POST /api/v1/auth/logout
```

Deskripsi:
- Menghapus token Sanctum yang sedang dipakai pada request.

Header:

```http
Accept: application/json
Authorization: Bearer <token>
```

Body:
- Tidak membutuhkan body.

Contoh response sukses:

```json
{
  "status": "success",
  "message": "Logout sukses"
}
```

Kemungkinan error:
- `401 Unauthorized`
  Token tidak dikirim, tidak valid, atau sudah dihapus

## Contoh cURL

Login:

```bash
curl -X POST http://127.0.0.1:8000/api/v1/auth/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "esmeralda76@example.net",
    "password": "password"
  }'
```

Logout:

```bash
curl -X POST http://127.0.0.1:8000/api/v1/auth/logout \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <token>"
```
