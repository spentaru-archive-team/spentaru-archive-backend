# API Docs

Base URL lokal:

```text
http://localhost:8000/api/v1
```

## Konvensi Umum

- Response JSON mengikuti pola `status`, `message`, lalu opsional `data`, `errors`, `trace_id`.
- Khusus endpoint proxy chat AI (`POST /chat/ask` dan alias legacy `POST /ai/chat/ask`), Laravel meneruskan body JSON dan status code dari AI service apa adanya. Format sukses/error endpoint ini mengikuti kontrak AI service, bukan wrapper Laravel lokal.
- Khusus endpoint proxy chat AI, frontend wajib mengirim header `X-Trace-Id` yang non-empty agar request bisa diteruskan ke AI service.
- Auth API aktif memakai Laravel Sanctum stateful berbasis session cookie.
- Fitur list tertentu mendukung `q` untuk pencarian teks bebas, `filters[...]` untuk penyaringan field, dan `sort` untuk pengurutan hasil.
- Konfigurasi search default repo saat ini memakai driver `database` di `config/scout.php`.
- Frontend SPA atau first-party perlu memanggil `GET /sanctum/csrf-cookie` sebelum `POST /api/v1/auth/login`.
- Request terproteksi dikirim dengan cookie session dan header CSRF, bukan bearer token.
- Upload file archive memakai `multipart/form-data`.
- Metadata file archive menyimpan `vector_id` UUID dari AI service pada `archive_files.vector_id`; OCR text/vector tetap berada di service AI/Qdrant.
- Global error JSON yang sudah ditangani konsisten untuk `401`, `403`, `405`, `422`, `429`, dan `500`.
- Semua endpoint CRUD memakai rate limiting: POST/PUT 30 req/menit, DELETE 10 req/menit, GET list 60 req/menit.
- File arsip hanya bisa diakses via endpoint terautentikasi `GET /api/v1/archives/{id}/preview` dan `GET /api/v1/archives/{id}/download`. URL langsung `/storage/uploads/...` tidak lagi bisa diakses publik.

## Panduan Search, Filter, Sort

- `q` dipakai untuk pencarian teks bebas sederhana. Cocok untuk keyword seperti nama, judul, catatan, atau kode label, tergantung endpoint.
- `filters[...]` dipakai jika ingin menyaring field tertentu dengan aturan yang jelas, mis. sama dengan, mengandung teks, atau rentang angka/tanggal.
- `sort` dipakai untuk menentukan urutan hasil, mis. terbaru dulu, abjad, atau kombinasi beberapa field.
- Search, filter, dan sort bisa dipakai bersamaan pada endpoint yang mendukung.
- Jika parameter query tidak valid, endpoint list yang memakai filter/sort saat ini cenderung mengabaikannya diam-diam, bukan melempar error.

## Role

- `admin`: CRUD master data, user, dan `archive-storage-rules`.
- `guru`: read master data, full CRUD archive, akses dashboard, update profil sendiri.

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
| `GET` | `/archives` | Ya | List archive, mendukung pencarian `q`, filter, dan sort |
| `POST` | `/archives` | Ya | Create archive + upload file |
| `GET` | `/archives/without-location` | Ya | List arsip yang belum punya lokasi fisik |
| `GET` | `/archives/physical-locations` | Ya | List semua lokasi fisik arsip, mendukung pencarian `q`, filter, dan sort |
| `GET` | `/archives/retention/ready` | Ya | List arsip dengan `retention_status=ready_for_destruction` |
| `GET` | `/archives/{id}` | Ya | Detail archive |
| `PUT` | `/archives/{id}` | Ya | Update archive, file opsional |
| `DELETE` | `/archives/{id}` | Ya | Hapus archive dan vector eksternal |
| `GET` | `/archives/{id}/preview` | Ya | Preview file archive (authenticated), throttle 60 req/menit |
| `GET` | `/archives/{id}/download` | Ya | Download file archive (authenticated), throttle 30 req/menit |
| `GET` | `/archives/{id}/physical-locations` | Ya | Detail lokasi fisik archive |
| `POST` | `/archives/{id}/physical-locations` | Ya | Buat lokasi fisik manual |
| `PUT` | `/archives/{id}/physical-locations` | Ya | Ubah lokasi fisik |
| `DELETE` | `/archives/{id}/physical-locations` | Ya | Hapus lokasi fisik |
| `PATCH` | `/archives/{id}/retention/decide` | Ya | Simpan keputusan retensi |

### Master Data

| Method | Endpoint | Auth | Keterangan |
|---|---|---|---|
| `GET` | `/events` | Ya | List event, mendukung pencarian `q`, `all`, `per_page`, filter, dan sort |
| `GET` | `/events/pending-uploads` | Ya | List event dengan `softfile_status=pending_upload`, memuat relasi `user` |
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
| `POST` | `/cabinets` | Admin | Create lemari + nested racks |
| `PUT` | `/cabinets/{id}` | Admin | Update lemari + sinkronisasi nested racks |
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
| `PUT` | `/users/{id}` | Admin | Update user. Field `role` hanya bisa diubah oleh admin dan tidak bisa diubah pada akun sendiri |
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
| `GET` | `/dashboard/teachers-without-archives` | Ya | List event guru yang belum punya archive, memuat relasi `user` |

### AI Gateway

| Method | Endpoint | Auth | Keterangan |
|---|---|---|---|
| `POST` | `/chat/ask` | Ya | Proxy utama chatbot ke AI service, throttle `30 request/menit`, response passthrough dari AI service |
| `POST` | `/ai/chat/ask` | Ya | Alias legacy ke proxy chatbot yang sama untuk kompatibilitas |

## Chat Ask Proxy

```http
POST /api/v1/chat/ask
```

Alias kompatibilitas:

```http
POST /api/v1/ai/chat/ask
```

Headers:

```http
Content-Type: application/json
Accept: application/json
X-Trace-Id: <required-trace-id-from-frontend>
```

Body:

| Key | Tipe | Wajib | Aturan |
|---|---|---|---|
| `message` | `string` | Ya | wajib, non-empty string |
| `use_search` | `boolean` | Tidak | default `false` bila tidak dikirim |

Contoh body:

```json
{
  "message": "Carikan arsip tentang inventaris laboratorium",
  "use_search": true
}
```

Perilaku proxy:

- Endpoint dilindungi `auth:sanctum`.
- Rate limit `throttle:30,1`.
- Request ditolak `422` jika header `X-Trace-Id` tidak dikirim atau kosong.
- Header `X-Trace-Id` dari frontend diteruskan apa adanya ke AI service upstream.
- Request diteruskan ke `${AI_SERVICE_BASE_URL}/api/chat/ask` dengan timeout dari `AI_SERVICE_TIMEOUT`.
- Payload yang diteruskan ke AI service memakai `message` berisi array riwayat chat dari Redis, dengan item `{ "role": "user|assistant", "content": "..." }`; `use_search` tetap diteruskan jika dikirim frontend.
- Riwayat chat disimpan per user di Redis key `chat:session:user:{id}:messages`; counter request memakai `chat:session:user:{id}:request_count`. Riwayat otomatis direset setelah 50 request sukses dan request upstream hanya mengambil maksimal 100 pesan terakhir.
- Jika AI service sukses dan mengembalikan `data.answer`, jawaban assistant ikut ditambahkan ke riwayat. Jika AI service error atau tidak bisa dihubungi, pesan user terakhir dibatalkan dari riwayat.
- Jika AI service merespons sukses atau error HTTP normal, body JSON dan status code diteruskan apa adanya ke frontend.
- Jika AI service timeout atau tidak dapat dihubungi, Laravel mengembalikan `502` dengan payload error lokal.

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

## 2. Create Cabinet

```http
POST /api/v1/cabinets
```

Auth:

- `admin`
- Flow SPA tetap perlu `GET /sanctum/csrf-cookie` lalu login session.

Body:

| Key | Tipe | Wajib | Aturan |
|---|---|---|---|
| `cabinet_number` | `integer` | Ya | unik, min `1` |
| `name` | `string` | Ya | unik, max `255` |
| `racks` | `array` | Ya | minimal `1` item |
| `racks.*.id` | `integer` | Tidak | boleh dikirim frontend, diabaikan saat create |
| `racks.*.rack_number` | `integer` | Ya | min `1`, max `10`, unik dalam array request |
| `racks.*.capacity` | `integer` | Ya | min `0` |
| `racks.*.used_capacity` | `integer` | Ya | min `0`, tidak boleh lebih besar dari `capacity` |

Contoh body:

```json
{
  "cabinet_number": "1",
  "name": "Standar Isi",
  "racks": [
    {
      "rack_number": "1",
      "capacity": "20",
      "used_capacity": "1"
    },
    {
      "rack_number": "2",
      "capacity": "20",
      "used_capacity": "0"
    }
  ]
}
```

Catatan:

- Endpoint membuat lemari dan seluruh rack dalam satu transaksi DB.
- `racks` boleh berisi 1 item atau lebih.
- Response sukses memuat relasi `racks` yang sudah diurutkan berdasarkan `rack_number`.

## 3. Update Cabinet

```http
PUT /api/v1/cabinets/{id}
```

Auth:

- `admin`

Body:

| Key | Tipe | Wajib | Aturan |
|---|---|---|---|
| `cabinet_number` | `integer` | Tidak | unik, min `1` |
| `name` | `string` | Tidak | unik, max `255` |
| `racks` | `array` | Tidak | jika dikirim, minimal `1` item |
| `racks.*.id` | `integer` | Tidak | pakai untuk update rack lama |
| `racks.*.rack_number` | `integer` | Ya jika `racks` dikirim | min `1`, max `10`, unik dalam array request |
| `racks.*.capacity` | `integer` | Ya jika `racks` dikirim | min `0` |
| `racks.*.used_capacity` | `integer` | Ya jika `racks` dikirim | min `0`, tidak boleh lebih besar dari `capacity` |

Perilaku sinkronisasi `racks`:

- Item `racks` yang punya `id` akan diupdate, dan `id` tersebut harus milik lemari yang sama.
- Item `racks` tanpa `id` akan dibuat sebagai rack baru.
- Rack lama yang tidak ikut dikirim dalam payload akan dihapus.
- Rack yang masih punya physical location tidak boleh dihapus.
- `capacity` rack tidak boleh diperkecil di bawah jumlah physical location yang sudah menempel.

Contoh body:

```json
{
  "cabinet_number": "1",
  "name": "Standar Isi Update",
  "racks": [
    {
      "id": 10,
      "rack_number": "1",
      "capacity": "30",
      "used_capacity": "1"
    },
    {
      "rack_number": "3",
      "capacity": "15",
      "used_capacity": "0"
    }
  ]
}
```

## 4. Create / Update Rack

```http
POST /api/v1/racks
PUT /api/v1/racks/{id}
```

Auth:

- `admin`

Body `POST /racks`:

| Key | Tipe | Wajib | Aturan |
|---|---|---|---|
| `cabinet_id` | `integer` | Ya | `exists:cabinets,id` |
| `rack_number` | `integer` | Ya | min `1`, max `10`, unik per lemari |
| `capacity` | `integer` | Ya | min `1`, max `100` |
| `used_capacity` | `integer` | Tidak | default `0`, tidak boleh lebih besar dari `capacity` |

Body `PUT /racks/{id}`:

- Mendukung partial update untuk `cabinet_id`, `rack_number`, `capacity`, dan `used_capacity`.
- Kombinasi final `cabinet_id + rack_number` tetap harus unik.
- `capacity` tidak boleh lebih kecil dari jumlah physical location yang sudah tersimpan pada rack.
- `used_capacity` tidak boleh lebih besar dari `capacity`.

## 5. List Archive

```http
GET /api/v1/archives
```

Identitas ID:

- `data.data[].id` adalah `archives.id`.
- `data.data[].event_id` adalah `events.id`.
- `data.data[].category_id` adalah `archive_categories.id`.
- `data.data[].subcategory_id` adalah `subcategories.id`.
- Jika relasi `physicalLocation` ikut termuat, `physicalLocation.id` adalah `archive_physical_locations.id` dan nilainya berbeda dari `archives.id`.

Perilaku:

- Endpoint selalu pagination `10` item per halaman.
- Query dijalankan lewat `Archive::search(...)` untuk field utama archive, lalu ditambah pencarian relasi tertentu dan bisa dipersempit dengan filter serta diurutkan dengan sort.
- Jika hasil page kosong, endpoint mengembalikan `404` dengan message `Arsip tidak ditemukan`.
- Sort/filter tidak valid akan diabaikan diam-diam karena `config('purity.silent') = true`.
- Search dipakai untuk keyword umum.
- Filter dipakai untuk syarat field yang lebih presisi.
- Sort dipakai untuk urutan hasil akhir.

Query:

| Key | Tipe | Default | Keterangan |
|---|---|---|---|
| `q` | `string` | - | keyword pencarian arsip |
| `sort` | `string` atau `array` | - | query pengurutan hasil |
| `filters` | `object` | - | query penyaringan field |

### Search archive

Search `q` saat ini mencari pada field dan relasi berikut:

- `title`
- `notes`
- `year`
- `category.name`
- `subcategory.name`
- `event.title`

Contoh:

```text
/api/v1/archives?q=rapor
/api/v1/archives?q=surat%20keluar
/api/v1/archives?q=keuangan
/api/v1/archives?q=wisuda
```

### Query Sort

Format:

```text
?sort=field
?sort=field:asc
?sort=field:desc
?sort[]=field_a:desc&sort[]=field_b:asc
```

Catatan:

- Jika arah sort tidak ditulis, default-nya `asc`.
- Default list tanpa `sort` memakai urutan `retention_status`: `active`, `ready_for_destruction`, `retained`, lalu `destroyed`, kemudian `created_at:desc`, sebelum pagination.
- `sort=retention_status` atau `sort=retention_status:asc` memakai urutan status retensi yang sama, lalu `created_at:desc` jika sort `created_at` tidak dikirim eksplisit.
- Sort relasi hanya mendukung 1 hop dengan format `relation.column:direction`.
- Nested relation sort seperti `physicalLocation.cabinet.name:asc` tidak didukung oleh implementasi package yang aktif.

#### Semua kolom archive yang bisa di-sort langsung

- `id`
- `event_id`
- `title`
- `year`
- `notes`
- `category_id`
- `subcategory_id`
- `retention_due_date`
- `retention_status`
- `retention_decided_at`
- `retention_decided_by`
- `retention_note`
- `uploader`
- `created_at`
- `updated_at`
- Versi qualified dari semua kolom di atas juga diterima, mis. `archives.title:asc`, `archives.year:desc`.

#### Semua sort relasi 1 hop yang bisa dipakai

- `event.{id,title,user_id,description,date,status,created_at,updated_at}`
- `category.{id,name,has_subcategory,description,created_at,updated_at}`
- `subcategory.{id,category_id,name,created_at,updated_at}`
- `files.{id,archive_id,file_name,file_size,file_type,created_at,updated_at}`
- `physicalLocation.{id,archive_id,cabinet_id,rack_id,slot_number,label_code,notes,created_at,updated_at}`
- `uploader.{id,name,subject,position,username,password,role,last_login_at,created_at,updated_at}`
- `retentionDecidedBy.{id,name,subject,position,username,password,role,last_login_at,created_at,updated_at}`

Contoh:

```text
/api/v1/archives?sort=year:desc
/api/v1/archives?sort=category.name:asc
/api/v1/archives?sort[]=retention_due_date:asc&sort[]=title:desc
/api/v1/archives?q=rapor&sort=year:desc
/api/v1/archives?q=keuangan&sort=category.name:asc
```

### Query Filter

Format dasar:

```text
?filters[field][operator]=value
```

Semua operator filter aktif saat ini:

| Operator | Arti singkat |
|---|---|
| `$eq` | sama dengan, case-insensitive default DB collation |
| `$eqc` | sama dengan, case-sensitive |
| `$ne` | tidak sama dengan |
| `$lt` | kurang dari |
| `$lte` | kurang dari atau sama dengan |
| `$gt` | lebih dari |
| `$gte` | lebih dari atau sama dengan |
| `$in` | ada di daftar nilai |
| `$notIn` | tidak ada di daftar nilai |
| `$between` | berada di antara dua nilai |
| `$notBetween` | di luar dua nilai |
| `$contains` | mengandung substring |
| `$notContains` | tidak mengandung substring |
| `$containsc` | mengandung substring, case-sensitive |
| `$notContainsc` | tidak mengandung substring, case-sensitive |
| `$startsWith` | diawali substring |
| `$startsWithc` | diawali substring, case-sensitive |
| `$endsWith` | diakhiri substring |
| `$endsWithc` | diakhiri substring, case-sensitive |
| `$null` | nilai `NULL` |
| `$notNull` | nilai bukan `NULL` |
| `$and` | gabungan filter logika AND |
| `$or` | gabungan filter logika OR |

Contoh bentuk operator array:

```text
filters[year][$between][0]=2020&filters[year][$between][1]=2024
filters[retention_status][$in][0]=active&filters[retention_status][$in][1]=retained
filters[$or][0][title][$contains]=rapor&filters[$or][1][notes][$contains]=rapat
```

#### Semua field archive yang bisa di-filter langsung

- `id`
- `event_id`
- `title`
- `year`
- `notes`
- `category_id`
- `subcategory_id`
- `retention_due_date`
- `retention_status`
- `retention_decided_at`
- `retention_decided_by`
- `retention_note`
- `uploader`
- `created_at`
- `updated_at`
- Versi qualified juga diterima, mis. `filters[archives.title][$contains]=rapor`.

Contoh yang valid:

```text
/api/v1/archives?filters[title][$contains]=rapor
/api/v1/archives?filters[year][$between][0]=2020&filters[year][$between][1]=2024
/api/v1/archives?filters[retention_status][$eq]=active
/api/v1/archives?filters[$or][0][retention_status][$eq]=active&filters[$or][1][retention_status][$eq]=retained
/api/v1/archives?q=rapor&filters[retention_status][$eq]=active
```

Catatan penting:

- Pada kode aktif sekarang, filter relasi `filters[category][name]...`, `filters[event][title]...`, `filters[physicalLocation]...`, dan nested relation lain belum bekerja. Query seperti itu saat ini diabaikan diam-diam oleh backend.
- Jadi daftar filter yang benar-benar aktif saat ini adalah semua kolom langsung tabel `archives` dengan semua operator di atas.
- Jika perlu filter kategori, gunakan `category_id` karena filter berdasarkan `category.name` belum didukung pada kode aktif.
- Jika nanti ingin mengaktifkan filter relasi, backend perlu menambahkan dukungan eksplisit pada model relasi yang terkait.
- Sort relasi tetap aktif walaupun filter relasi belum aktif. Untuk endpoint archive, `sort=category.name:asc|desc` sudah ditangani eksplisit dan aman dipakai bersama `q`.

## 6. Create Archive

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

Perilaku:

- File disimpan ke disk `local` pada path internal `uploads/<slug>.<ext>`.
- MIME type file divalidasi server-side: hanya `application/pdf`, `application/msword`, `application/vnd.openxmlformats-officedocument.wordprocessingml.document`, `application/vnd.ms-excel`, `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`, `image/jpeg`, dan `image/png` yang diterima.
- `uploader` tidak diterima dari request; backend selalu memakai user yang sedang login.
- `retention_due_date` otomatis diisi ke awal tahun arsip + 10 tahun.
- `retention_status` awal adalah `active`.
- Jika category sudah punya subcategory, maka `subcategory_id` wajib diisi.
- Jika `subcategory_id` dikirim, subkategori harus berada di bawah `category_id` yang sama.
- Sistem mencoba auto-assign lokasi fisik melalui `ArchiveStorageService`.
- Backend mengirim file dan metadata ke `${AI_SERVICE_BASE_URL}/api/extract/text`; AI service wajib mengembalikan `data.vector_id`.
- `vector_id` dari AI service disimpan di `archive_files.vector_id` dan tidak boleh null.

## 7. Preview & Download Archive

> **Penting:** File arsip **tidak lagi bisa diakses langsung** melalui URL `/storage/uploads/filename.pdf`. Seluruh akses file harus melalui endpoint terautentikasi. Ini diterapkan untuk mencegah kebocoran data arsip sensitif kepada pihak yang tidak berwenang.

### Arsitektur Akses File

File fisik disimpan di `storage/app/private/uploads/` melalui disk `local`, dan tidak diekspos sebagai URL publik. Satu-satunya cara resmi mengakses file adalah melalui endpoint authenticated yang melakukan validasi session.

**Perbandingan sistem lama vs baru:**

| Aspek | Sistem Lama | Sistem Baru |
|---|---|---|
| Akses file | Langsung via `/storage/uploads/filename.pdf` | Hanya via `/preview` atau `/download` |
| Autentikasi | Tidak diperlukan | Wajib Sanctum session |
| Rate limiting | Tidak ada | Preview 60 req/menit, Download 30 req/menit |
| Response | Static file | Laravel `Storage::response/download` |

### Preview File

```http
GET /api/v1/archives/{id}/preview
```

**Auth:** User login via Sanctum session cookie

**Throttle:** 60 request per menit

**Perilaku:**

- Endpoint mencari archive berdasarkan `{id}` dan memuat relasi `files`
- Jika archive tidak ditemukan, Laravel mengembalikan `404`
- Jika archive tidak punya metadata file atau file fisiknya tidak ada, endpoint mengembalikan `404` dengan message `File tidak ditemukan`
- Response berupa file inline (`Content-Disposition: inline`) sehingga browser menampilkan file di tab/iframe
- Content-Type otomatis sesuai tipe file tersimpan (`application/pdf`, `application/msword`, dll.)
- Cocok untuk `<iframe>`, `<embed>`, atau PDF viewer di frontend

**Contoh request:**

```bash
curl -c cookies.txt -b cookies.txt \
  -H "X-XSRF-TOKEN: <token>" \
  http://localhost:8000/api/v1/archives/12/preview
```

**Contoh response headers:**

```
HTTP/1.1 200 OK
Content-Type: application/pdf
Content-Disposition: inline; filename="laporan_keuangan_abc123.pdf"
Content-Length: 102400
Cache-Control: no-cache, private

[binary PDF data]
```

**Frontend React:**

```tsx
// Embed di iframe
<iframe
  src={`/api/v1/archives/${archiveId}/preview`}
  width="100%"
  height="600px"
/>

// Fetch blob dan buka di tab baru
async function preview(archiveId: number) {
  const res = await fetch(`/api/v1/archives/${archiveId}/preview`, {
    credentials: 'include',
    headers: { 'X-XSRF-TOKEN': getCsrfToken() },
  });
  const blob = await res.blob();
  window.open(URL.createObjectURL(blob), '_blank');
}
```

**Frontend Vue:**

```vue
<template>
  <iframe :src="`/api/v1/archives/${archiveId}/preview`" width="100%" height="600px" />
</template>
```

### Download File

```http
GET /api/v1/archives/{id}/download
```

**Auth:** User login via Sanctum session cookie

**Throttle:** 30 request per menit

**Perilaku:**

- Endpoint mencari archive berdasarkan `{id}` dan memuat relasi `files`
- Jika archive tidak ditemukan, Laravel mengembalikan `404`
- Jika archive tidak punya metadata file atau file fisiknya tidak ada, endpoint mengembalikan `404` dengan message `File tidak ditemukan`
- Response berupa attachment (`Content-Disposition: attachment`) sehingga browser langsung mendownload file
- Nama file sesuai `file_name` yang tersimpan di database (format: `{original}_{random}_{timestamp}.{ext}`)

**Contoh request:**

```bash
curl -c cookies.txt -b cookies.txt \
  -H "X-XSRF-TOKEN: <token>" \
  -O -J \
  http://localhost:8000/api/v1/archives/12/download
```

**Contoh response headers:**

```
HTTP/1.1 200 OK
Content-Type: application/pdf
Content-Disposition: attachment; filename="laporan_keuangan_abc123.pdf"
Content-Length: 102400
Content-Transfer-Encoding: binary

[binary PDF data]
```

**Frontend React:**

```tsx
// Link langsung (paling sederhana)
<a href={`/api/v1/archives/${archiveId}/download`} download={fileName}>
  Download {fileName}
</a>

// Fetch blob untuk nama custom
async function download(archiveId: number, customName: string) {
  const res = await fetch(`/api/v1/archives/${archiveId}/download`, {
    credentials: 'include',
    headers: { 'X-XSRF-TOKEN': getCsrfToken() },
  });
  const blob = await res.blob();
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = customName;
  a.click();
  URL.revokeObjectURL(url);
}
```

**Frontend Vue:**

```vue
<template>
  <a :href="`/api/v1/archives/${archiveId}/download`" :download="fileName">
    Download
  </a>
</template>
```

### Error Handling

| Status | Kondisi | Response |
|---|---|---|
| `401` | Tidak terautentikasi | `{"status": "error", "message": "Unauthenticated"}` |
| `404` | Archive tidak ditemukan | `No query results for model...` |
| `404` | Archive ada tapi tanpa file | `{"status": "error", "message": "File tidak ditemukan"}` |
| `429` | Melebihi rate limit | `{"status": "error", "message": "Terlalu banyak request"}` |

### Catatan Keamanan

1. **API tidak mengirim `file_url`** — Frontend harus memakai endpoint `/preview` dan `/download` untuk akses file.
2. **File ada di disk private** — File fisik disimpan di `storage/app/private/uploads/` melalui disk `local`, bukan di direktori publik.
3. **Filename di-obfuscate** — Format `{original}_{random10}_{timestamp}.{ext}` untuk mencegah guessing attack.
4. **MIME type divalidasi server-side** — Upload file divalidasi dengan `mimetypes:` di controller, bukan hanya ekstensi.

## 8. Update Archive

```http
PUT /api/v1/archives/{id}
```

Body mendukung partial update.

Perilaku:

- Field `uploader` tidak bisa diubah melalui endpoint ini.
- Backend melakukan `PATCH ${AI_SERVICE_BASE_URL}/api/vector/{vector_id}` untuk menyinkronkan vector eksternal. Payload hanya menyertakan field yang punya value atau berubah; field kosong tidak dikirim.
- Jika file diganti, metadata file di-update lewat relasi `hasOne`, `vector_id` lama tetap dipakai, dan file fisik lama dibersihkan setelah transaksi sukses.
- Jika `category_id` atau `subcategory_id` berubah, archive akan auto-relocate ke rak baru (rack lama di-decrement, rack baru di-increment).
- Hapus archive (`DELETE /api/v1/archives/{id}`) memanggil `DELETE ${AI_SERVICE_BASE_URL}/api/vector/{vector_id}` sebelum row archive dan file fisik dihapus.

## 9. Arsip Tanpa Lokasi

```http
GET /api/v1/archives/without-location
```

Mengembalikan semua archive yang belum punya relasi `physicalLocation`.

## 10. Physical Location Archive

### List semua physical location

```http
GET /api/v1/archives/physical-locations
```

Identitas ID:

- `data.data[].id` adalah `archive_physical_locations.id`.
- `data.data[].archive_id` adalah `archives.id`.
- `data.data[].cabinet_id` adalah `cabinets.id`.
- `data.data[].rack_id` adalah `racks.id`.
- Endpoint ini mengembalikan resource physical location, bukan resource archive.

Perilaku:

- Query dasar: `ArchivePhysicalLocation::search($q ?? '')`, lalu hasilnya masih bisa dipersempit dengan filter dan diurutkan dengan sort.
- Jika `all=true`, response mengembalikan semua physical location tanpa pagination.
- Jika `all` tidak dikirim atau `false`, endpoint memakai pagination `10` item per halaman.
- Relasi yang dimuat: `archive.files`, `cabinet`, `rack`.
- Filter/sort tidak valid akan diabaikan diam-diam karena `config('purity.silent') = true`.
- Search dipakai untuk keyword umum.
- Filter dipakai untuk syarat field yang lebih presisi.
- Sort dipakai untuk urutan hasil akhir.

Query:

| Key | Tipe | Default | Keterangan |
|---|---|---|---|
| `all` | `boolean` | `false` | bila `true`, tanpa pagination |
| `q` | `string` | - | keyword pencarian lokasi fisik |
| `sort` | `string` atau `array` | - | query pengurutan hasil |
| `filters` | `object` | - | query penyaringan field |

### Search physical location

Search `q` saat ini mencari pada field dan relasi berikut:

- `label_code`
- `notes`
- `archive.title`
- `cabinet.name`
- `cabinet.cabinet_number`

Contoh:

```text
/api/v1/archives/physical-locations?q=L1-R2
/api/v1/archives/physical-locations?q=lemari%20depan
/api/v1/archives/physical-locations?q=Arsip%20Kelulusan
/api/v1/archives/physical-locations?q=Lemari%20A
```

### Sort physical location

Format:

```text
?sort=field
?sort=field:asc
?sort=field:desc
?sort[]=field_a:desc&sort[]=field_b:asc
```

Semua kolom physical location yang bisa di-sort langsung:

- `id`
- `archive_id`
- `cabinet_id`
- `rack_id`
- `slot_number`
- `label_code`
- `notes`
- `created_at`
- `updated_at`
- Versi qualified juga diterima, mis. `archive_physical_locations.slot_number:asc`.

Sort relasi 1 hop yang tersedia:

- `archive.{id,event_id,title,year,notes,category_id,subcategory_id,retention_due_date,retention_status,retention_decided_at,retention_decided_by,retention_note,uploader,created_at,updated_at}`
- `cabinet.{id,cabinet_number,name,created_at,updated_at}`
- `rack.{id,cabinet_id,rack_number,capacity,used_capacity,created_at,updated_at}`

Contoh:

```text
/api/v1/archives/physical-locations?sort=slot_number:asc
/api/v1/archives/physical-locations?sort=cabinet.name:asc
/api/v1/archives/physical-locations?sort[]=cabinet_id:asc&sort[]=slot_number:asc
/api/v1/archives/physical-locations?q=L1&sort=slot_number:asc
```

### Filter physical location

Format dasar:

```text
?filters[field][operator]=value
```

Operator yang aktif sama seperti endpoint archive:

- `$eq`, `$eqc`, `$ne`
- `$lt`, `$lte`, `$gt`, `$gte`
- `$in`, `$notIn`
- `$between`, `$notBetween`
- `$contains`, `$containsc`, `$notContains`, `$notContainsc`
- `$startsWith`, `$startsWithc`, `$endsWith`, `$endsWithc`
- `$null`, `$notNull`
- `$and`, `$or`

Semua field physical location yang benar-benar bisa di-filter langsung saat ini:

- `id`
- `archive_id`
- `cabinet_id`
- `rack_id`
- `slot_number`
- `label_code`
- `notes`
- `created_at`
- `updated_at`
- Versi qualified juga diterima, mis. `filters[archive_physical_locations.label_code][$contains]=L1-`.

Contoh:

```text
/api/v1/archives/physical-locations?filters[cabinet_id][$eq]=1
/api/v1/archives/physical-locations?filters[slot_number][$between][0]=1&filters[slot_number][$between][1]=10
/api/v1/archives/physical-locations?filters[$or][0][label_code][$contains]=L1&filters[$or][1][label_code][$contains]=L2
/api/v1/archives/physical-locations?q=L1&filters[cabinet_id][$eq]=1
```

Catatan penting:

- Walau endpoint ini punya relasi `archive`, `cabinet`, dan `rack`, filter relasi seperti `filters[cabinet][name]...` atau `filters[archive][title]...` belum tentu bekerja pada kode aktif jika backend belum menyiapkan dukungan relasi tersebut.
- Sort relasi tetap tersedia karena model `ArchivePhysicalLocation` memakai `Sortable`.

### Detail physical location per archive

```http
GET /api/v1/archives/{id}/physical-locations
```

Arti parameter:

- `{id}` pada endpoint ini adalah `archives.id`.
- Endpoint ini bukan menerima `archive_physical_locations.id`.
- Contoh: jika archive punya `id=25` dan row physical location punya `id=7`, URL yang benar adalah `/api/v1/archives/25/physical-locations`.

Perilaku:

- Endpoint mencari archive berdasarkan `{id}`, lalu mengambil relasi `physicalLocation.cabinet` dan `physicalLocation.rack`.
- Jika `{id}` tidak ada di tabel `archives`, Laravel mengembalikan `404`.
- Jika archive tidak punya physical location, endpoint mengembalikan `404` dengan message `Physical location tidak ditemukan`.

### Create physical location manual

```http
POST /api/v1/archives/{id}/physical-locations
```

Arti parameter:

- `{id}` pada endpoint ini adalah `archives.id`.
- Frontend tidak perlu mengirim `archive_id` di body.
- Nilai `archive_id` diambil otomatis dari archive parent pada URL.

Body:

| Key | Tipe | Wajib | Aturan |
|---|---|---|---|
| `cabinet_id` | `integer` | Ya | `exists:cabinets,id` |
| `rack_id` | `integer` | Ya | `exists:racks,id`. Backend validasi kapasitas: jika `used_capacity >= capacity`, return error `Rak tidak cukup kapasitas. Silakan pilih rak lain.` |
| `slot_number` | `integer` | Ya | min `1` |
| `notes_physical_location` | `string` | Tidak | nullable |

Perilaku:

- Payload `notes_physical_location` dinormalisasi menjadi kolom `notes`.
- `label_code` dibentuk otomatis dari `cabinet.cabinet_number`, `rack.rack_number`, dan `slot_number` dengan format `L{cabinet_number}-R{rack_number}-S{slot_number}`.
- Backend validasi kapasitas: jika `used_capacity >= capacity` pada rack tujuan, return error `Rak tidak cukup kapasitas. Silakan pilih rak lain.`
- `cabinet_id` harus mengacu ke `cabinets.id` yang valid.
- `rack_id` harus mengacu ke `racks.id` yang valid.
- `rack_id` harus memang berada di `cabinet_id` yang dipilih, jika tidak return `422` dengan message `Rak tidak berada di lemari yang dipilih`.
- Kombinasi `rack_id` dan `slot_number` tidak boleh bentrok dengan physical location lain, jika slot sudah dipakai return `422` dengan message `Slot pada rak tersebut sudah terpakai`.
- `slot_number` tidak boleh lebih besar dari `capacity` rack tujuan.
- Jika `{id}` tidak ada di tabel `archives`, Laravel mengembalikan `404`.
- Jika archive sudah punya physical location, endpoint mengembalikan `422` dengan message `Physical location archive sudah ada`.
- Response sukses memuat relasi `cabinet` dan `rack`.

### Update physical location

```http
PUT /api/v1/archives/{id}/physical-locations
```

Arti parameter:

- `{id}` pada endpoint ini tetap `archives.id`.
- Endpoint tidak menerima `archive_physical_locations.id` sebagai path parameter.
- Frontend tidak perlu mengirim `archive_id` atau `physical_location_id` di body.

Field partial update:

- `cabinet_id`
- `rack_id`
- `slot_number`
- `notes_physical_location`

Perilaku:

- Semua field memakai validasi `sometimes|required`, kecuali `notes_physical_location` yang `sometimes|nullable|string`.
- Payload `notes_physical_location` dinormalisasi menjadi kolom `notes`.
- `label_code` dihitung ulang dari kombinasi final `cabinet_id`, `rack_id`, dan `slot_number`, termasuk saat update partial.
- Backend validasi kapasitas: jika `rack_id` berubah dan `used_capacity >= capacity` pada rack tujuan, return error `Rak tidak cukup kapasitas. Silakan pilih rak lain.` (Validasi di-skip jika `rack_id` tidak berubah).
- Kombinasi final `cabinet_id` dan `rack_id` harus cocok. Jika rack tidak berada di lemari yang dipilih, return `422` dengan message `Rak tidak berada di lemari yang dipilih`.
- Kombinasi final `rack_id` dan `slot_number` tidak boleh bentrok dengan physical location archive lain. Jika slot sudah dipakai, return `422` dengan message `Slot pada rak tersebut sudah terpakai`.
- Validasi `slot_number <= capacity` tetap dihitung dari rack final, termasuk saat request hanya mengubah `slot_number`.
- Hanya field yang nilainya benar-benar berubah yang akan di-update.
- Jika `{id}` tidak ada di tabel `archives`, Laravel mengembalikan `404`.
- Jika archive belum punya physical location, endpoint mengembalikan `404` dengan message `Physical location tidak ditemukan`.
- Response sukses memuat relasi `cabinet` dan `rack`.

### Delete physical location

```http
DELETE /api/v1/archives/{id}/physical-locations
```

Arti parameter:

- `{id}` pada endpoint ini adalah `archives.id`.
- Endpoint menghapus physical location milik archive tersebut, bukan menghapus berdasarkan `archive_physical_locations.id`.

Perilaku:

- Endpoint menghapus relasi physical location milik archive.
- Endpoint ini dilindungi middleware `admin`, sehingga user non-admin akan mendapat `403`.
- Jika `{id}` tidak ada di tabel `archives`, Laravel mengembalikan `404`.
- Jika archive belum punya physical location, endpoint mengembalikan `404` dengan message `Physical location tidak ditemukan`.

Catatan untuk frontend:

- Jika sumber data berasal dari list `/api/v1/archives/physical-locations`, jangan kirim `data.id` ke endpoint nested ini.
- Untuk endpoint nested `/api/v1/archives/{id}/physical-locations`, gunakan `data.archive_id` dari hasil list physical location, atau gunakan `archive.id` dari endpoint archive.

## 11. List Event

```http
GET /api/v1/events
```

Perilaku:

- Query dasar: `Event::search($q ?? '')`, lalu hasilnya masih bisa dipersempit dengan filter dan diurutkan dengan sort.
- Jika `all=true`, response mengembalikan semua event tanpa pagination.
- Jika `all` tidak dikirim atau `false`, endpoint memakai pagination.
- `per_page` default `10`.
- Jika hasil kosong, endpoint mengembalikan `404` dengan message `event tidak ditemukan`.
- Filter/sort tidak valid akan diabaikan diam-diam karena `config('purity.silent') = true`.
- Search dipakai untuk keyword umum.
- Filter dipakai untuk syarat field yang lebih presisi.
- Sort dipakai untuk urutan hasil akhir.

Query:

| Key | Tipe | Default | Keterangan |
|---|---|---|---|
| `all` | `boolean` | `false` | bila `true`, tanpa pagination |
| `per_page` | `integer` | `10` | jumlah item per halaman saat pagination |
| `q` | `string` | - | keyword pencarian event |
| `sort` | `string` atau `array` | - | query pengurutan hasil |
| `filters` | `object` | - | query penyaringan field |

### Search event

Search `q` saat ini mencari pada field searchable model event:

- `title`
- `description`

Contoh:

```text
/api/v1/events?q=wisuda
/api/v1/events?q=rapat%20guru
```

### Sort event

Format:

```text
?sort=field
?sort=field:asc
?sort=field:desc
?sort[]=field_a:desc&sort[]=field_b:asc
```

Semua kolom event yang bisa di-sort langsung:

- `id`
- `title`
- `user_id`
- `description`
- `date`
- `status`
- `softfile_status`
- `created_at`
- `updated_at`
- Versi qualified juga diterima, mis. `events.title:asc`.

Sort relasi 1 hop yang tersedia:

- `user.{id,name,subject,position,username,password,role,last_login_at,created_at,updated_at}`
- `archives.{id,event_id,title,year,notes,category_id,subcategory_id,retention_due_date,retention_status,retention_decided_at,retention_decided_by,retention_note,uploader,created_at,updated_at}`

Contoh:

```text
/api/v1/events?sort=date:desc
/api/v1/events?sort=user.name:asc
/api/v1/events?sort[]=status:asc&sort[]=date:desc
/api/v1/events?q=wisuda&sort=date:desc
```

### Filter event

Format dasar:

```text
?filters[field][operator]=value
```

Operator yang aktif sama seperti endpoint archive:

- `$eq`, `$eqc`, `$ne`
- `$lt`, `$lte`, `$gt`, `$gte`
- `$in`, `$notIn`
- `$between`, `$notBetween`
- `$contains`, `$containsc`, `$notContains`, `$notContainsc`
- `$startsWith`, `$startsWithc`, `$endsWith`, `$endsWithc`
- `$null`, `$notNull`
- `$and`, `$or`

Semua field event yang benar-benar bisa di-filter langsung saat ini:

- `id`
- `title`
- `user_id`
- `description`
- `date`
- `status`
- `softfile_status`
- `created_at`
- `updated_at`
- Versi qualified juga diterima, mis. `filters[events.title][$contains]=rapat`.

Contoh:

```text
/api/v1/events?filters[title][$contains]=rapat
/api/v1/events?filters[date][$between][0]=2026-04-01&filters[date][$between][1]=2026-04-30
/api/v1/events?filters[$or][0][status][$eq]=ongoing&filters[$or][1][status][$eq]=done
/api/v1/events?filters[softfile_status][$eq]=uploaded
/api/v1/events?q=rapat&filters[status][$eq]=ongoing
```

Catatan penting:

- Walau endpoint ini punya relasi `user` dan `archives`, filter relasi `filters[user][name]...` atau `filters[archives][title]...` belum tentu bekerja pada kode aktif jika backend belum menyiapkan dukungan relasi tersebut.
- Sort relasi tetap tersedia karena model `Event` memakai `Sortable`.

## 12. List Event Pending Upload

```http
GET /api/v1/events/pending-uploads
```

Perilaku:

- Endpoint memakai auth Sanctum.
- Query memuat relasi `user`.
- Endpoint hanya mengambil event dengan `softfile_status = pending_upload`.
- Jika `all=true`, response mengembalikan semua data tanpa pagination.
- Jika `all` tidak dikirim atau `false`, endpoint memakai pagination default `10` item per halaman.
- Endpoint saat ini tidak memakai query `per_page`.
- Jika tidak ada data, endpoint tetap mengembalikan `200` dengan `data` kosong sesuai bentuk hasil query Laravel.

Query:

| Key | Tipe | Default | Keterangan |
|---|---|---|---|
| `all` | `boolean` | `false` | bila `true`, tanpa pagination |

Response sukses:

```json
{
  "status": "success",
  "message": "sukses ambil event yang masih belum uploaded",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "title": "Rapat Guru",
        "user_id": 2,
        "description": "Persiapan semester baru",
        "date": "2026-04-20T00:00:00.000000Z",
        "status": "ongoing",
        "softfile_status": "pending_upload",
        "created_at": "2026-04-20T08:00:00.000000Z",
        "updated_at": "2026-04-20T08:00:00.000000Z",
        "user": {
          "id": 2,
          "name": "Guru Bahasa Indonesia",
          "username": "guruindo",
          "role": "guru",
          "created_at": "2026-04-11T16:50:44.000000Z",
          "updated_at": "2026-04-11T16:50:44.000000Z"
        }
      }
    ]
  }
}
```

## 13. Create Event

```http
POST /api/v1/events
```

Body:

| Key | Tipe | Wajib | Aturan |
|---|---|---|---|
| `title` | `string` | Ya | max `255` |
| `description` | `string` | Tidak | nullable |
| `user_id` | `integer` | Ya | `exists:users,id` |
| `date` | `date` | Ya | format tanggal valid |
| `status` | `string` | Ya | `ongoing` atau `done` |

Response sukses memuat relasi `user`.

Catatan:

- `softfile_status` tidak diinput manual pada endpoint event.
- Nilainya otomatis `pending_upload` atau `uploaded` berdasarkan ada tidaknya archive pada event yang memiliki relasi `files`.

## 14. Update Event

```http
PUT /api/v1/events/{id}
```

Body mendukung partial update.

Aturan:

- `title`, `user_id`, `date`, dan `status` divalidasi dengan `sometimes|required`.
- `description` boleh `nullable|string`.
- Response sukses memuat relasi `user`.

## 15. Delete Event

```http
DELETE /api/v1/events/{id}
```

Perilaku:

- Event tidak bisa dihapus jika masih punya archive.
- Jika masih punya archive, endpoint mengembalikan `422` dengan message `Tidak dapat menghapus event yang memiliki arsip`.

## 16. Retention

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
- Jika `retention_status=destroyed`, file fisik arsip di disk `local`, row `archive_files`, dan lokasi fisik arsip ikut dihapus; `used_capacity` rak terkait dikurangi.
- Jika `retention_status=active`, `retention_due_date` boleh diperbarui dari payload.

## 17. Dashboard Teachers Without Archives

```http
GET /api/v1/dashboard/teachers-without-archives
```

Auth:

- user login via Sanctum session cookie

Perilaku:

- Mengambil data dari tabel `events`.
- Hanya mengambil event yang belum punya relasi `archives`.
- Hanya mengambil event yang pemiliknya (`user`) ber-role `guru`.
- Response memuat relasi `user`.
- Urutan data: `date` terbaru dulu, lalu `created_at` terbaru dulu.
- Jika `all=true`, response mengembalikan seluruh data tanpa pagination.
- Jika `all` tidak dikirim, response menggunakan pagination dengan default `10`.

Query:

| Key | Tipe | Default | Keterangan |
|---|---|---|---|
| `all` | `boolean` | `false` | ambil semua data tanpa pagination |
| `per_page` | `integer` | `10` | jumlah data per halaman saat pagination aktif |

Contoh:

```text
/api/v1/dashboard/teachers-without-archives
/api/v1/dashboard/teachers-without-archives?per_page=5
/api/v1/dashboard/teachers-without-archives?all=true
```

Contoh bentuk item:

```json
{
  "id": 12,
  "title": "Rapat Komite",
  "user_id": 3,
  "description": "Koordinasi agenda sekolah",
  "date": "2026-05-01T00:00:00.000000Z",
  "status": "ongoing",
  "softfile_status": "pending_upload",
  "created_at": "2026-05-02T08:54:50.000000Z",
  "updated_at": "2026-05-02T08:54:50.000000Z",
  "user": {
    "id": 3,
    "name": "Guru Naufal",
    "username": "guru_bindo",
    "role": "guru",
    "subject": "Bahasa Indonesia",
    "position": "Guru",
    "last_login_at": "2026-05-02 15:30:00",
    "created_at": "2026-05-02 08:54:50",
    "updated_at": "2026-05-02 09:27:54"
  }
}
```

## 19. Archive Storage Rules

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
| `priority` | `integer` | Ya | unik untuk kombinasi `category_id` + `subcategory_id` yang sama |

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

- `priority` tetap unik untuk kombinasi `category_id` + `subcategory_id` yang sama, dengan pengecualian untuk row yang sedang di-update.
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

## 20. User Notes

### List user

```http
GET /api/v1/users
```

Query:

| Key | Tipe | Default | Keterangan |
|---|---|---|---|
| `q` | `string` | - | keyword pencarian user |
| `role` | `string` | - | filter exact role, mis. `admin` atau `guru` |

Perilaku:

- Jika `q` tidak dikirim, endpoint mengembalikan pagination user biasa, 10 item per halaman.
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
