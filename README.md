# spentaru-archive-backend

Backend Laravel 13 untuk sistem arsip sekolah dengan auth Sanctum stateful berbasis session cookie, manajemen arsip, lokasi fisik arsip, master data, dashboard, workflow retensi arsip, serta fitur search, filter, dan sort pada endpoint list utama.

## Stack

- PHP 8.3+
- Laravel 13
- MySQL
- Laravel Sanctum
- Laravel Scout
- Redis opsional untuk cache, queue, atau session

## Fitur Utama

- Auth API berbasis session cookie Sanctum untuk SPA atau first-party frontend
- CRUD arsip + upload file dengan validasi MIME type server-side
- Akses file arsip melalui endpoint terautentikasi (`preview` dan `download`)
- Auto-assign lokasi fisik arsip via `ArchiveStorageService`
- Tracking used_capacity rack: otomatis increment/decrement saat archive dibuat, dihapus, dipindahkan, atau keputusan retensi `destroyed`
- CRUD rule penempatan arsip via `archive-storage-rules`
- CRUD master data: event, kategori, subkategori, lemari, rak, user
- Search text via query `q` pada endpoint list `users`, `archives`, `events`, dan `archives/physical-locations`
- Filter dan sort dinamis via query string pada endpoint list `archives`, `events`, dan `archives/physical-locations`
- Dashboard ringkas untuk total arsip, kategori, subkategori, dan user
- AI tool endpoint untuk search arsip via service Python
- Workflow retensi arsip: arsip tanpa lokasi, arsip siap pemusnahan, dan keputusan retensi
- Rate limiting pada semua endpoint CRUD
- Role protection dengan audit logging untuk perubahan role user
- SQL wildcard escaping untuk mencegah search query abuse
- CSRF protection via Sanctum stateful middleware
- CORS restricted ke origins, methods, dan headers eksplisit
- HTTPS untuk komunikasi AI service (OCR)

## Role

- `admin`: CRUD master data, user, dan `archive-storage-rules`
- `guru`: read master data, CRUD archive, akses dashboard, update profil sendiri

## Menjalankan Project

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
php artisan serve
```

Jika ingin fitur search berbasis Scout sinkron untuk driver non-`database`, import index setelah migrate:

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

1. Panggil `GET /sanctum/csrf-cookie` untuk mengambil CSRF cookie
2. Login ke `POST /api/v1/auth/login` dengan mengirim cookie + header `X-XSRF-TOKEN`
3. Kirim cookie session + header CSRF untuk request terproteksi berikutnya

Contoh flow lengkap:

```bash
# 1. Ambil CSRF cookie
curl -c cookies.txt -b cookies.txt http://localhost:8000/sanctum/csrf-cookie

# 2. Login
curl -X POST http://localhost:8000/api/v1/auth/login \
  -c cookies.txt -b cookies.txt \
  -H "Content-Type: application/json" \
  -H "X-XSRF-TOKEN: $(cat cookies.txt | grep XSRF | awk '{print $NF}')" \
  -d '{"username":"admin","password":"password"}'

# 3. Request terproteksi
curl -c cookies.txt -b cookies.txt \
  -H "X-XSRF-TOKEN: $(cat cookies.txt | grep XSRF | awk '{print $NF}')" \
  http://localhost:8000/api/v1/archives
```

## Command Penting

- `php artisan route:list`
- `composer test`
- `./vendor/bin/pint`
- `php artisan config:clear`

## Cara Akses File Arsip

### Arsitektur File Access

File arsip **tidak lagi bisa diakses langsung** melalui URL `/storage/uploads/filename.pdf`. Seluruh akses file harus melalui endpoint terautentikasi yang memvalidasi session user sebelum melayani file. Ini diterapkan untuk mencegah kebocoran data arsip sensitif kepada pihak yang tidak berwenang.

**Perubahan dari sistem lama:**

| Aspek | Sistem Lama (Public) | Sistem Baru (Authenticated) |
|---|---|---|
| Akses file | Langsung via `/storage/uploads/filename.pdf` | Hanya via `/api/v1/archives/{id}/preview` atau `/download` |
| Autentikasi | Tidak diperlukan | Wajib login via Sanctum session |
| Rate limiting | Tidak ada | Preview: 60 req/menit, Download: 30 req/menit |
| Response | Static file serve | Laravel Storage::response/download |
| Authorization | Siapa pun yang tahu URL | Hanya user terautentikasi |

**Lokasi penyimpanan:**

File fisik tetap disimpan di `storage/app/public/uploads/` dan di-symlink ke `public/storage/uploads/`. Namun, **akses langsung ke path ini tidak lagi disarankan**. Web server (Nginx/Apache) harus dikonfigurasi untuk **memblokir akses langsung** ke direktori `public/storage/uploads/` di production. File hanya disajikan melalui controller yang melakukan validasi auth.

### Endpoint Preview File

```http
GET /api/v1/archives/{id}/preview
```

**Auth:** User login via Sanctum session cookie

**Throttle:** 60 request per menit

**Perilaku:**

- Endpoint mencari archive berdasarkan `{id}` dan memuat relasi `files`
- Jika archive tidak ditemukan, Laravel mengembalikan `404`
- Jika archive tidak punya file (field `file_url` kosong), endpoint mengembalikan `404` dengan message `File tidak ditemukan`
- Response berupa file yang ditampilkan inline di browser (content-type sesuai file: `application/pdf`, `application/msword`, dll.)
- Header `Content-Disposition` diset ke `inline` sehingga browser akan menampilkan file di tab/iframe, bukan langsung mendownload
- Cocok untuk ditampilkan di `<iframe>`, `<embed>`, atau viewer PDF di frontend

**Contoh request (curl):**

```bash
curl -c cookies.txt -b cookies.txt \
  -H "X-XSRF-TOKEN: <token>" \
  http://localhost:8000/api/v1/archives/12/preview
```

**Contoh response:**

```
HTTP/1.1 200 OK
Content-Type: application/pdf
Content-Disposition: inline; filename="laporan_keuangan_abc123.pdf"
Content-Length: 102400
Cache-Control: no-cache, private

[binary PDF data]
```

**Cara pakai di frontend React:**

```tsx
// Method 1: Embed di iframe
function ArchivePreview({ archiveId }: { archiveId: number }) {
  return (
    <iframe
      src={`/api/v1/archives/${archiveId}/preview`}
      width="100%"
      height="600px"
      title="Preview arsip"
    />
  );
}

// Method 2: Fetch blob dan tampilkan
async function previewArchive(archiveId: number) {
  const response = await fetch(`/api/v1/archives/${archiveId}/preview`, {
    credentials: 'include', // Kirim cookie session
    headers: {
      'X-XSRF-TOKEN': getCsrfToken(),
    },
  });

  if (!response.ok) {
    throw new Error('Gagal memuat preview');
  }

  const blob = await response.blob();
  const url = URL.createObjectURL(blob);
  window.open(url, '_blank');
}

// Method 3: Embed di object tag
<object
  data={`/api/v1/archives/${archiveId}/preview`}
  type="application/pdf"
  width="100%"
  height="600px"
>
  <p>Browser tidak mendukung preview PDF</p>
</object>
```

**Cara pakai di frontend Vue:**

```vue
<template>
  <!-- Embed di iframe -->
  <iframe :src="previewUrl" width="100%" height="600px" />

  <!-- Atau embed di object -->
  <object :data="previewUrl" type="application/pdf" width="100%" height="600px">
    <p>Browser tidak mendukung preview PDF</p>
  </object>
</template>

<script setup>
const props = defineProps({ archiveId: Number })
const previewUrl = computed(() => `/api/v1/archives/${props.archiveId}/preview`)
</script>
```

### Endpoint Download File

```http
GET /api/v1/archives/{id}/download
```

**Auth:** User login via Sanctum session cookie

**Throttle:** 30 request per menit

**Perilaku:**

- Endpoint mencari archive berdasarkan `{id}` dan memuat relasi `files`
- Jika archive tidak ditemukan, Laravel mengembalikan `404`
- Jika archive tidak punya file (field `file_url` kosong), endpoint mengembalikan `404` dengan message `File tidak ditemukan`
- Response berupa file yang didownload sebagai attachment
- Header `Content-Disposition` diset ke `attachment; filename="..."` sehingga browser akan langsung mendownload file ke perangkat user
- Nama file yang didownload sesuai dengan `file_name` yang tersimpan di database (bukan nama asli saat upload, melainkan nama yang sudah di-obfuscate dengan random string dan timestamp)

**Contoh request (curl):**

```bash
curl -c cookies.txt -b cookies.txt \
  -H "X-XSRF-TOKEN: <token>" \
  -O -J \
  http://localhost:8000/api/v1/archives/12/download
```

Flag `-O` menyimpan file dengan nama dari server, `-J` menggunakan nama file dari header `Content-Disposition`.

**Contoh response:**

```
HTTP/1.1 200 OK
Content-Type: application/pdf
Content-Disposition: attachment; filename="laporan_keuangan_abc123.pdf"
Content-Length: 102400
Content-Transfer-Encoding: binary
Cache-Control: no-cache, private

[binary PDF data]
```

**Cara pakai di frontend React:**

```tsx
// Method 1: Link langsung (paling sederhana)
function ArchiveDownloadLink({ archiveId, fileName }: { archiveId: number; fileName: string }) {
  return (
    <a
      href={`/api/v1/archives/${archiveId}/download`}
      download={fileName}
    >
      Download {fileName}
    </a>
  );
}

// Method 2: Fetch blob untuk download dengan nama custom
async function downloadArchive(archiveId: number, customName: string) {
  const response = await fetch(`/api/v1/archives/${archiveId}/download`, {
    credentials: 'include',
    headers: {
      'X-XSRF-TOKEN': getCsrfToken(),
    },
  });

  if (!response.ok) {
    throw new Error('Gagal mendownload file');
  }

  const blob = await response.blob();
  const url = URL.createObjectURL(blob);

  // Buat elemen <a> temporary untuk trigger download
  const a = document.createElement('a');
  a.href = url;
  a.download = customName; // Nama file yang ditampilkan ke user
  document.body.appendChild(a);
  a.click();

  // Cleanup
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}

// Method 3: Download multiple files
async function downloadMultipleArchives(archiveIds: number[]) {
  for (const id of archiveIds) {
    await downloadArchive(id, `arsip-${id}.pdf`);
  }
}
```

**Cara pakai di frontend Vue:**

```vue
<template>
  <!-- Link langsung -->
  <a :href="downloadUrl" :download="fileName">
    Download {{ fileName }}
  </a>

  <!-- Button dengan custom handler -->
  <button @click="handleDownload">
    Download Arsip
  </button>
</template>

<script setup>
const props = defineProps({ archiveId: Number, fileName: String })

const downloadUrl = computed(() => `/api/v1/archives/${props.archiveId}/download`)

async function handleDownload() {
  const response = await fetch(`/api/v1/archives/${props.archiveId}/download`, {
    credentials: 'include',
  })

  if (!response.ok) {
    alert('Gagal mendownload file')
    return
  }

  const blob = await response.blob()
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = props.fileName
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  URL.revokeObjectURL(url)
}
</script>
```

### Error Handling

Kedua endpoint (`preview` dan `download`) memiliki skenario error yang sama:

| Status Code | Kondisi | Response |
|---|---|---|
| `401` | User tidak terautentikasi | `{"status": "error", "message": "Unauthenticated"}` |
| `404` | Archive tidak ditemukan | `{"status": "error", "message": "No query results for model..."}` |
| `404` | Archive ada tapi tidak punya file | `{"status": "error", "message": "File tidak ditemukan"}` |
| `429` | Melebihi rate limit | `{"status": "error", "message": "Terlalu banyak request"}` |

### Konfigurasi Web Server Production

Di production, **blokir akses langsung** ke `public/storage/uploads/` di web server Anda:

**Nginx:**

```nginx
location /storage/uploads/ {
    deny all;
    return 403;
}
```

**Apache (.htaccess di `public/storage/uploads/`):**

```apache
Deny from all
```

Dengan konfigurasi ini, satu-satunya cara mengakses file arsip adalah melalui endpoint authenticated yang sudah memiliki validasi auth dan rate limiting.

### Catatan Keamanan

1. **Jangan simpan URL file di frontend** — Response API masih mengandung field `file_url` (`/storage/uploads/...`) di object `archive.files`. Frontend tidak boleh menggunakan URL ini langsung. Gunakan endpoint `/preview` dan `/download` sebagai gantinya.
2. **File tetap di disk `public`** — File fisik disimpan di `storage/app/public/uploads/` untuk kemudahan backup dan manajemen file. Namun, akses HTTP langsung ke path ini harus diblokir di level web server.
3. **Filename di-obfuscate** — Nama file disimpan dengan format `{original-name}_{random10}_{timestamp}.{ext}` untuk mencegah guessing attack. Namun, ini bukan substitusi untuk autentikasi.
4. **Validasi MIME type** — File yang di-upload divalidasi MIME type-nya di server-side menggunakan rule `mimetypes:` di controller, bukan hanya ekstensi file.

## Catatan Search, Filter, dan Sort

- Dependency `laravel/scout` sudah terpasang untuk fitur search berbasis query `q`.
- Default `SCOUT_DRIVER` saat ini adalah `database`, dengan konfigurasi di `config/scout.php`.
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
- Detail field searchable, filterable, sortable, dan contoh query ada di [docs/api.md](docs/api.md).

## Rate Limiting

Semua endpoint CRUD memiliki rate limiting untuk mencegah abuse:

| Jenis Endpoint | Limit | Keterangan |
|---|---|---|
| Login | 5 req/menit | `POST /api/v1/auth/login` |
| Create/Update | 30 req/menit | Semua endpoint `POST` dan `PUT/PATCH` |
| Delete | 10 req/menit | Semua endpoint `DELETE` |
| Preview File | 60 req/menit | `GET /api/v1/archives/{id}/preview` |
| Download File | 30 req/menit | `GET /api/v1/archives/{id}/download` |
| Get List | 60 req/menit | Semua endpoint `GET` list |

Jika melebihi limit, server mengembalikan `429` dengan message `Terlalu banyak request`.

## Dokumen

- Ringkasan endpoint aktif: [docs/api.md](docs/api.md)
- Panduan agent repo: [AGENTS.md](AGENTS.md)
- Vulnerability report: [docs/vuln.md](docs/vuln.md)
