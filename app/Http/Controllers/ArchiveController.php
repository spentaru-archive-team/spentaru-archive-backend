<?php

namespace App\Http\Controllers;

use App\Http\Requests\DecideRetentionRequest;
use App\Http\Requests\StoreArchiveRequest;
use App\Http\Requests\UpdateArchiveRequest;
use App\Models\Archive;
use App\Models\Subcategory;
use App\Services\ArchiveStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ArchiveController extends Controller
{
    public function __construct(private ArchiveStorageService $storageService) {}



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


    private function storagePathFromUrl(?string $fileUrl): ?string
    {
        if (!$fileUrl) {
            return null;
        }

        return Str::startsWith($fileUrl, '/storage/') ? Str::after($fileUrl, '/storage/') : ltrim($fileUrl, '/');
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
        if ($request->boolean('all')) {
            $archives = Archive::orderBy('created_at', 'desc')->get();
        } else {
            $archives = Archive::with(['event', 'category', 'subcategory', 'files', 'uploader', 'physicalLocation.cabinet', 'physicalLocation.rack'])
                ->selectRaw('ROW_NUMBER() OVER (ORDER BY id) AS row_num, archives.*')
                ->paginate(10);
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

        $jumlah_subcategory = Subcategory::where('category_id', $request->category_id)->count();
        if ($jumlah_subcategory > 0) {
            if ($request->subcategory_id == null) {
                return response()->json(
                    [
                        'status' => 'error',
                        'message' => 'Subkategori wajib diisi karena kategori sudah mempunyai sub kategori',
                    ],
                    422,
                );
            }
        }

        $req_archive = $request->safe()->except(['file']);
        $file = $request->file('file');
        $timestamp = now()->format('YmdHisv');
        $random = Str::random(10);
        $filename = str(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) . ' ' . $random . ' ' . $timestamp)->slug('_') . '.' . strtolower($file->getClientOriginalExtension());
        $path = $file->storeAs('uploads', $filename, 'public');

        $year = intval($req_archive['year']);

        $payload = $req_archive + [
            'status' => 'uploaded',
            'uploader' => str(Auth::user()->id),
            'retention_due_date' => $year ? now()->setYear($year)->startOfYear()->addYears(10)->toDateString() : null,
            'retention_status' => 'active',
        ];

        try {
            $archive = DB::transaction(function () use ($path, $filename, $file, $req_archive, $payload) {
                $archive = Archive::create($payload);

                $archive->files()->create([
                    'file_name' => $filename,
                    'file_size' => $file->getSize(),
                    'file_type' => strtolower($file->getClientOriginalExtension()),
                    'file_url' => '/storage/' . $path,
                ]);

                $this->storageService->assignLocation($archive, $req_archive['category_id'], $req_archive['subcategory_id'] ?? null);

                return $archive->load(['files', 'physicalLocation.cabinet', 'physicalLocation.rack']);
            });
        } catch (\Throwable $th) {
            Storage::disk('public')->delete($path);

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
        $archive = Archive::with(['files', 'physicalLocation'])->findOrFail($id);
        $validated = $request->safe()->all();

        $archiveInput = collect($validated)
            ->except(['file'])
            ->all();
        $archiveData = [];
        foreach ($archiveInput as $key => $value) {
            if (!$this->hasSameValue($archive->{$key}, $value)) {
                $archiveData[$key] = $value;
            }
        }

        $path = null;
        $filename = null;
        $file = null;
        $previousPaths = collect([$archive->files])
            ->filter()
            ->pluck('file_url')
            ->map(fn(?string $fileUrl) => $this->storagePathFromUrl($fileUrl))
            ->filter()
            ->values()
            ->all();

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $timestamp = now()->format('YmdHisv');
            $random = Str::random(10);
            $filename = str(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) . ' ' . $random . ' ' . $timestamp)->slug('_') . '.' . strtolower($file->getClientOriginalExtension());
            $storedPath = $file->storeAs('uploads', $filename, 'public');
            $path = '/storage/' . $storedPath;

            if (!$this->hasSameValue($archive->status, 'uploaded')) {
                $archiveData['status'] = 'uploaded';
            }
        }

        try {
            DB::transaction(function () use ($archive, $archiveData, $file, $filename, $path) {
                if ($archiveData !== []) {
                    $archive->update($archiveData);
                }

                if ($file && $filename && $path) {
                    $archive->files()->delete();
                    $archive->files()->create([
                        'file_name' => $filename,
                        'file_size' => $file->getSize(),
                        'file_type' => strtolower($file->getClientOriginalExtension()),
                        'file_url' => $path,
                    ]);
                }
            });
        } catch (\Throwable $th) {
            if ($path) {
                Storage::disk('public')->delete($this->storagePathFromUrl($path));
            }

            throw $th;
        }

        if ($file && $previousPaths !== []) {
            Storage::disk('public')->delete($previousPaths);
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
        $archive = Archive::with('files')->findOrFail($id);
        $filePaths = collect([$archive->files])
            ->filter()
            ->pluck('file_url')
            ->map(fn(?string $fileUrl) => $this->storagePathFromUrl($fileUrl))
            ->filter()
            ->values()
            ->all();

        DB::transaction(function () use ($archive) {
            $archive->delete();
        });

        Storage::disk('public')->delete($filePaths);

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
        $archive = Archive::with('files')->findOrFail($id);

        if ($request->retention_status === 'destroyed' && $archive->files?->file_url) {
            Storage::disk('public')->delete(Str::after($archive->files->file_url, '/storage/'));
            $archive->files()->delete();
        }

        $archive->update([
            'retention_status' => $request->retention_status,
            'retention_decided_at' => now(),
            'retention_decided_by' => Auth::id(),
            'retention_note' => $request->retention_note,
            'retention_due_date' => $request->retention_status === 'active' || $request->retention_status == 'retained' ? $request->retention_due_date : $archive->retention_due_date,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'keputusan retensi berhasil disimpan',
            'data' => $archive->fresh(),
        ]);
    }
}
