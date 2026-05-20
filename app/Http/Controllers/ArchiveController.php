<?php

namespace App\Http\Controllers;

use App\Http\Requests\DecideRetentionRequest;
use App\Http\Requests\StoreArchiveRequest;
use App\Http\Requests\UpdateArchiveRequest;
use App\Models\Archive;
use App\Models\ArchiveCategory;
use App\Models\ArchiveFile;
use App\Models\Subcategory;
use App\Services\ArchiveStorageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Str as SupportStr;

class ArchiveController extends Controller
{
    private const ARCHIVE_FILE_DISK = 'local';

    private const ARCHIVE_FILE_DIRECTORY = 'uploads';

    public function __construct(private ArchiveStorageService $storageService) {}

    private function makeArchiveFilename(UploadedFile $file): string
    {
        $timestamp = now()->format('YmdHisv');
        $random = Str::random(10);

        return str(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME).' '.$random.' '.$timestamp)
            ->slug('_')
            .'.'.strtolower($file->getClientOriginalExtension());
    }

    private function archiveFilePath(?ArchiveFile $file): ?string
    {
        if (! $file?->file_name) {
            return null;
        }

        return self::ARCHIVE_FILE_DIRECTORY.'/'.basename($file->file_name);
    }

    private function storeArchiveFile(UploadedFile $file, string $filename): string
    {
        return $file->storeAs(self::ARCHIVE_FILE_DIRECTORY, $filename, self::ARCHIVE_FILE_DISK);
    }

    private function deleteArchiveFile(?ArchiveFile $file): void
    {
        $path = $this->archiveFilePath($file);

        if ($path) {
            Storage::disk(self::ARCHIVE_FILE_DISK)->delete($path);
        }
    }

    private function requireArchiveFilePath(?ArchiveFile $file): string
    {
        $path = $this->archiveFilePath($file);

        if (! $path || ! Storage::disk(self::ARCHIVE_FILE_DISK)->exists($path)) {
            abort(response()->json([
                'status' => 'error',
                'message' => 'File tidak ditemukan',
            ], 404));
        }

        return $path;
    }

    private function deleteVectorFromQdrant(?string $vectorId): void
    {
        if (! $vectorId) {
            return;
        }

        $aiBaseUrl = rtrim((string) config('services.ai_gateway.base_url', 'http://localhost:5000'), '/');
        $aiTimeout = (int) config('services.ai_gateway.timeout', 15);

        try {
            $http = Http::timeout($aiTimeout);
            $aiServiceKey = config('services.ai_gateway.api_key', '');
            if ($aiServiceKey) {
                $http->withHeader('X-AI-Service-Key', $aiServiceKey);
            }
            $http->delete("{$aiBaseUrl}/api/vector/{$vectorId}");
        } catch (\Exception $e) {
            Log::warning('Failed to delete vector from Qdrant', [
                'vector_id' => $vectorId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function validateSubcategorySelection(int $categoryId, ?int $subcategoryId): ?JsonResponse
    {
        $category = ArchiveCategory::find($categoryId);

        if ($category?->has_subcategory && $subcategoryId === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Subkategori wajib diisi karena kategori ini mempunyai sub kategori',
            ], 422);
        }

        if ($category && ! $category->has_subcategory && $subcategoryId !== null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Subkategori harus kosong karena kategori ini tidak mempunyai sub kategori',
            ], 422);
        }

        if ($subcategoryId !== null && ! Subcategory::whereKey($subcategoryId)->where('category_id', $categoryId)->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Subkategori tidak sesuai dengan kategori',
            ], 422);
        }

        return null;
    }

    private function hasSameValue(mixed $current, mixed $incoming): bool
    {
        if ($current === null || $incoming === null) {
            return $current === $incoming;
        }

        if (is_int($current) || is_float($current) || is_int($incoming) || is_float($incoming)) {
            return (string) $current === (string) $incoming;
        }

        return $current === $incoming;
    }

    private function normalizedSorts(Request $request): array
    {
        $sort = $request->query('sort', []);

        if (is_string($sort) && $sort !== '') {
            return [$sort];
        }

        if (! is_array($sort)) {
            return [];
        }

        return array_values(array_filter($sort, fn ($value) => is_string($value) && $value !== ''));
    }

    private function hasCategoryNameSort(array $sorts): bool
    {
        foreach ($sorts as $sort) {
            [$column] = array_pad(explode(':', $sort, 2), 2, 'asc');
            $normalizedColumn = trim($column);

            if (in_array($normalizedColumn, ['category.name', 'archive_categories.name'], true)) {
                return true;
            }
        }

        return false;
    }

    private function hasRetentionStatusSort(array $sorts): bool
    {
        foreach ($sorts as $sort) {
            [$column] = array_pad(explode(':', $sort, 2), 2, 'asc');
            $normalizedColumn = trim($column);

            if (in_array($normalizedColumn, ['retention_status', 'archives.retention_status'], true)) {
                return true;
            }
        }

        return false;
    }

    private function hasCreatedAtSort(array $sorts): bool
    {
        foreach ($sorts as $sort) {
            [$column] = array_pad(explode(':', $sort, 2), 2, 'asc');
            $normalizedColumn = trim($column);

            if (in_array($normalizedColumn, ['created_at', 'archives.created_at'], true)) {
                return true;
            }
        }

        return false;
    }

    private function applyRetentionStatusOrder(Builder $query, string $direction = 'asc'): void
    {
        $cases = $direction === 'desc'
            ? "WHEN 'destroyed' THEN 1 WHEN 'retained' THEN 2 WHEN 'ready_for_destruction' THEN 3 WHEN 'active' THEN 4 ELSE 5"
            : "WHEN 'active' THEN 1 WHEN 'ready_for_destruction' THEN 2 WHEN 'retained' THEN 3 WHEN 'destroyed' THEN 4 ELSE 5";

        $query->orderByRaw("CASE archives.retention_status {$cases} END");
    }

    private function applyArchiveSearch(Builder $query, string $q): void
    {
        $escaped = str_replace(['%', '_', '\\'], ['\%', '\_', '\\\\'], $q);

        $query->where(function (Builder $searchQuery) use ($escaped) {
            $searchQuery->where('archives.title', 'like', "%{$escaped}%")
                ->orWhere('archives.notes', 'like', "%{$escaped}%")
                ->orWhere('archives.year', 'like', "%{$escaped}%")
                ->orWhereHas('category', function (Builder $categoryQuery) use ($escaped) {
                    $categoryQuery->where('name', 'like', "%{$escaped}%");
                })
                ->orWhereHas('subcategory', function (Builder $subcategoryQuery) use ($escaped) {
                    $subcategoryQuery->where('name', 'like', "%{$escaped}%");
                })
                ->orWhereHas('event', function (Builder $eventQuery) use ($escaped) {
                    $eventQuery->where('title', 'like', "%{$escaped}%");
                });
        });
    }

    private function applyArchiveSorts(Builder $query, array $sorts): void
    {
        $allowedDirectSorts = [
            'id',
            'event_id',
            'title',
            'year',
            'notes',
            'category_id',
            'subcategory_id',
            'retention_due_date',
            'retention_status',
            'retention_decided_at',
            'retention_decided_by',
            'retention_note',
            'uploader',
            'created_at',
            'updated_at',
        ];

        $joinedCategory = false;
        $appliedSort = false;
        $appliedRetentionStatusSort = false;
        $hasCreatedAtSort = $this->hasCreatedAtSort($sorts);

        foreach ($sorts as $sort) {
            [$column, $direction] = array_pad(explode(':', $sort, 2), 2, 'asc');
            $normalizedColumn = trim($column);
            $normalizedDirection = strtolower(trim($direction)) === 'desc' ? 'desc' : 'asc';

            if (in_array($normalizedColumn, ['category.name', 'archive_categories.name'], true)) {
                if (! $joinedCategory) {
                    $query->leftJoin('archive_categories as category_sort', 'category_sort.id', '=', 'archives.category_id')
                        ->select('archives.*');
                    $joinedCategory = true;
                }

                $query->orderBy('category_sort.name', $normalizedDirection);
                $appliedSort = true;

                continue;
            }

            $directColumn = SupportStr::startsWith($normalizedColumn, 'archives.')
                ? SupportStr::after($normalizedColumn, 'archives.')
                : $normalizedColumn;

            if (in_array($directColumn, $allowedDirectSorts, true)) {
                if ($directColumn === 'retention_status') {
                    $this->applyRetentionStatusOrder($query, $normalizedDirection);
                    $appliedRetentionStatusSort = true;
                } else {
                    $query->orderBy("archives.{$directColumn}", $normalizedDirection);
                }

                $appliedSort = true;
            }
        }

        if (! $appliedSort) {
            $this->applyRetentionStatusOrder($query);
            $query->orderBy('archives.created_at', 'desc');
            $query->orderBy('archives.id', 'desc');

            return;
        }

        if ($appliedRetentionStatusSort && ! $hasCreatedAtSort) {
            $query->orderBy('archives.created_at', 'desc');
            $query->orderBy('archives.id', 'desc');
        }
    }

    public function archivesWithoutLocation()
    {
        $archives = Archive::doesntHave('physicalLocation')->get();

        return response()->json([
            'status' => 'success',
            'message' => 'sukses mengambil arsip yang belum memiliki lokasi fisik',
            'data' => $archives,
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $sorts = $this->normalizedSorts($request);
        $hasCategoryNameSort = $this->hasCategoryNameSort($sorts);
        $hasRetentionStatusSort = $this->hasRetentionStatusSort($sorts);

        $archives = Archive::search('')->query(function ($query) use ($q, $sorts, $hasCategoryNameSort, $hasRetentionStatusSort) {
            $query->with([
                'event',
                'category',
                'subcategory',
                'files',
                'uploader',
                'physicalLocation.cabinet',
                'physicalLocation.rack',
            ])->filter();

            if (filled($q)) {
                $this->applyArchiveSearch($query, $q);
            }

            if ($hasCategoryNameSort || $hasRetentionStatusSort || empty($sorts)) {
                $this->applyArchiveSorts($query, $sorts);
            } else {
                $query->sort();
            }
        })
            ->paginate(10);

        if (empty($archives[0])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Arsip tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'sukses mengambil archive',
            'data' => $archives,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreArchiveRequest $request)
    {
        $allowed_mimes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'image/jpeg', 'image/png'];
        if (! in_array($request->file('file')->getMimeType(), $allowed_mimes, true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tipe file tidak diizinkan',
            ], 422);
        }

        $subcategoryId = $request->filled('subcategory_id') ? (int) $request->subcategory_id : null;
        if ($response = $this->validateSubcategorySelection((int) $request->category_id, $subcategoryId)) {
            return $response;
        }

        $req_archive = $request->safe()->except(['file']);
        $file = $request->file('file');
        $filename = $this->makeArchiveFilename($file);
        $storedPath = $this->storeArchiveFile($file, $filename);

        $year = intval($req_archive['year']);

        $payload = array_merge($req_archive, [
            'uploader' => Auth::id(),
            'retention_due_date' => $year ? now()->setYear($year)->startOfYear()->addYears(10)->toDateString() : null,
            'retention_status' => 'active',
        ]);

        try {
            $archive = DB::transaction(function () use ($payload, $req_archive) {
                $archive = Archive::create($payload);
                $this->storageService->assignLocation($archive, $req_archive['category_id'], $req_archive['subcategory_id'] ?? null);

                return $archive;
            });

            $cat = ArchiveCategory::find($req_archive['category_id']);
            $sub = isset($req_archive['subcategory_id']) ? Subcategory::find($req_archive['subcategory_id']) : null;

            $aiBaseUrl = rtrim((string) config('services.ai_gateway.base_url', 'http://localhost:5000'), '/');
            $aiTimeout = (int) config('services.ai_gateway.timeout', 15);

            $http = Http::timeout($aiTimeout)->asMultipart();
            $aiServiceKey = config('services.ai_gateway.api_key', '');
            if ($aiServiceKey) {
                $http->withHeader('X-AI-Service-Key', $aiServiceKey);
            }
            $response = $http->post("{$aiBaseUrl}/api/extract/text", [
                [
                    'name' => 'file',
                    'contents' => file_get_contents($file->getRealPath()),
                    'filename' => $filename,
                ],
                [
                    'name' => 'archive_id',
                    'contents' => (string) $archive->id,
                ],
                [
                    'name' => 'title',
                    'contents' => $archive->title ?? '',
                ],
                [
                    'name' => 'year',
                    'contents' => (string) ($archive->year ?? ''),
                ],
                [
                    'name' => 'category',
                    'contents' => $cat?->name ?? '',
                ],
                [
                    'name' => 'subcategory',
                    'contents' => $sub?->name ?? '',
                ],
            ]);

            $ocr_result = $response->json();
            $ocr = $ocr_result['data']['text'] ?? '';
            $vector_id = $ocr_result['data']['vector_id'] ?? null;

            $payload_file = [
                'file_name' => $filename,
                'file_size' => $file->getSize(),
                'file_type' => strtolower($file->getClientOriginalExtension()),
                'extraction_status' => 'done',
            ];

            $payload_ocr = [
                'extracted_text' => $ocr,
                'vector_id' => $vector_id,
            ];

            $archive->files()->create($payload_file);
            $archive->ocrText()->create($payload_ocr);
        } catch (\Throwable $th) {
            Storage::disk(self::ARCHIVE_FILE_DISK)->delete($storedPath);
            $archive?->delete();

            throw $th;
        }

        return response()->json(
            [
                'status' => 'success',
                'message' => 'sukses menyimpan archive',
                'data' => $archive,
            ],
            201,
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $archive = Archive::with(['event', 'category', 'subcategory', 'files', 'physicalLocation.cabinet', 'physicalLocation.rack', 'uploader'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'sukses mengambil detail archive',
            'data' => $archive,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateArchiveRequest $request, string $id)
    {
        $archive = Archive::with(['files', 'ocrText', 'physicalLocation.rack'])->findOrFail($id);
        $validated = $request->safe()->all();
        $oldFile = $archive->files;
        $oldVectorId = $archive->ocrText?->vector_id;

        $archiveInput = collect($validated)
            ->except(['file'])
            ->all();
        $archiveData = [];
        $categoryChanged = false;
        $subcategoryChanged = false;

        if (array_key_exists('category_id', $archiveInput) || array_key_exists('subcategory_id', $archiveInput)) {
            $categoryId = array_key_exists('category_id', $archiveInput)
                ? (int) $archiveInput['category_id']
                : (int) $archive->category_id;
            $subcategoryId = array_key_exists('subcategory_id', $archiveInput)
                ? ($archiveInput['subcategory_id'] === null ? null : (int) $archiveInput['subcategory_id'])
                : ($archive->subcategory_id === null ? null : (int) $archive->subcategory_id);

            if ($response = $this->validateSubcategorySelection($categoryId, $subcategoryId)) {
                return $response;
            }
        }

        foreach ($archiveInput as $key => $value) {
            if (! $this->hasSameValue($archive->{$key}, $value)) {
                $archiveData[$key] = $value;
                if ($key === 'category_id') {
                    $categoryChanged = true;
                }
                if ($key === 'subcategory_id') {
                    $subcategoryChanged = true;
                }
            }
        }

        $needsRelocation = $categoryChanged || $subcategoryChanged;
        $oldRack = $archive->physicalLocation?->rack;

        $storedPath = null;
        $filename = null;
        $file = null;
        $ocrPayload = null;

        try {
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $filename = $this->makeArchiveFilename($file);
                $storedPath = $this->storeArchiveFile($file, $filename);

                // OCR
                $aiBaseUrl = rtrim((string) config('services.ai_gateway.base_url', 'http://localhost:5000'), '/');
                $aiTimeout = (int) config('services.ai_gateway.timeout', 15);

                $http = Http::timeout($aiTimeout)->asMultipart();
                $aiServiceKey = config('services.ai_gateway.api_key', '');
                if ($aiServiceKey) {
                    $http->withHeader('X-AI-Service-Key', $aiServiceKey);
                }
                $response = $http->post("{$aiBaseUrl}/api/extract/text", [
                    [
                        'name' => 'file',
                        'contents' => file_get_contents($file->getRealPath()),
                        'filename' => $file->getClientOriginalName(),
                    ],
                ]);
                // Ambil response jadi variable
                $ocr_result = $response->json();
                $ocr = $ocr_result['data']['text'] ?? '';
                $vector_id = $ocr_result['data']['vector_id'] ?? null;

                $ocrPayload = [
                    'extracted_text' => $ocr,
                    'vector_id' => $vector_id,
                ];
                // OCR end
            }

            DB::transaction(function () use ($archive, $archiveData, $file, $filename, $needsRelocation, $oldRack, $ocrPayload) {
                if ($archiveData !== []) {
                    $archive->update($archiveData);
                }

                if ($file && $filename) {
                    $archive->files()->delete();
                    $archive->files()->create([
                        'file_name' => $filename,
                        'file_size' => $file->getSize(),
                        'file_type' => strtolower($file->getClientOriginalExtension()),
                        'extraction_status' => 'done',
                    ]);
                    $archive->ocrText()->updateOrCreate(
                        ['archive_id' => $archive->id],
                        $ocrPayload ?? []
                    );
                }

                if ($needsRelocation) {
                    if ($archive->physicalLocation) {
                        $archive->physicalLocation->delete();
                        if ($oldRack) {
                            $oldRack->decrement('used_capacity');
                        }
                    }
                    $this->storageService->assignLocation($archive, $archive->category_id, $archive->subcategory_id);
                }
            });
        } catch (\Throwable $th) {
            if ($storedPath) {
                Storage::disk(self::ARCHIVE_FILE_DISK)->delete($storedPath);
            }

            throw $th;
        }

        if ($file) {
            $this->deleteArchiveFile($oldFile);
            $this->deleteVectorFromQdrant($oldVectorId);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'sukses memperbarui archive',
            'data' => $archive->fresh(['event', 'category', 'subcategory', 'files', 'physicalLocation.cabinet', 'physicalLocation.rack', 'ocrText']),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $archive = Archive::with(['files', 'ocrText', 'physicalLocation.rack'])->findOrFail($id);
        $file = $archive->files;
        $vectorId = $archive->ocrText?->vector_id;

        DB::transaction(function () use ($archive, $vectorId) {
            $this->deleteVectorFromQdrant($vectorId);

            if ($archive->physicalLocation?->rack) {
                $archive->physicalLocation->rack->decrement('used_capacity');
            }
            $archive->delete();
        });

        $this->deleteArchiveFile($file);

        return response()->json([
            'status' => 'success',
            'message' => 'sukses menghapus archive',
        ]);
    }

    public function readyForDestruction()
    {
        $archives = Archive::where('retention_status', 'ready_for_destruction')
            ->with(['category', 'subcategory', 'files'])
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'sukses mengambil arsip siap pemusnahan',
            'data' => $archives,
        ]);
    }

    public function decideRetention(DecideRetentionRequest $request, string $id)
    {
        $archive = Archive::with(['files', 'ocrText', 'physicalLocation.rack'])->findOrFail($id);

        $fileToDelete = null;
        $vectorIdToDelete = null;
        $year = now()->year;

        DB::transaction(function () use ($archive, $request, &$fileToDelete, &$vectorIdToDelete, $year) {
            if ($request->retention_status === 'destroyed') {
                $fileToDelete = $archive->files;
                $vectorIdToDelete = $archive->ocrText?->vector_id;

                $this->deleteVectorFromQdrant($vectorIdToDelete);

                if ($archive->files) {
                    $archive->files()->delete();
                }

                if ($archive->physicalLocation) {
                    if ($archive->physicalLocation->rack) {
                        $archive->physicalLocation->rack->decrement('used_capacity');
                    }
                    $archive->physicalLocation->delete();
                }
            }

            $archive->update([
                'retention_status' => $request->retention_status,
                'retention_decided_at' => now(),
                'retention_decided_by' => Auth::id(),
                'retention_note' => $request->retention_note,
                'retention_due_date' => $year ? now()->setYear($year)->startOfYear()->addYears(1)->toDateString() : null,
            ]);
        });

        if ($request->retention_status === 'destroyed') {
            $this->deleteArchiveFile($fileToDelete);
        }

        if ($request->retention_status !== 'destroyed') {
            return response()->json([
                'status' => 'success',
                'message' => 'keputusan retensi berhasil disimpan, retension dipanjangkan jadi 1 tahun lagi',
                'data' => $archive->fresh(),
            ]);
        } else {
            return response()->json([
                'status' => 'success',
                'message' => 'Retensi berhasil dihancurkan',
                'data' => $archive->fresh(),
            ]);
        }
    }

    public function preview(string $id)
    {
        $archive = Archive::with('files')->findOrFail($id);

        $storagePath = $this->requireArchiveFilePath($archive->files);

        return Storage::disk(self::ARCHIVE_FILE_DISK)->response($storagePath, $archive->files->file_name);
    }

    public function download(string $id)
    {
        $archive = Archive::with('files')->findOrFail($id);

        $storagePath = $this->requireArchiveFilePath($archive->files);

        return Storage::disk(self::ARCHIVE_FILE_DISK)->download($storagePath, $archive->files->file_name);
    }
}
