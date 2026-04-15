<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreArchiveRequest;
use App\Http\Requests\UpdateArchiveRequest;
use App\Models\Archive;
use App\Models\ArchivePhysicalLocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ArchiveController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $page = Archive::selectRaw('ROW_NUMBER() OVER (ORDER BY id) AS row_num, archives.*')->paginate(10);

        return response()->json([
            'status' => 'success',
            'message' => 'sukses mengambil archive',
            'data' => $page,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreArchiveRequest $request)
    {
        $req_archive = $request->safe()->except(['file', 'cabinet_id', 'slot_number', 'rack_id', 'notes_physical_location']);
        $req_physical_location = $request->safe()->only([
            'cabinet_id',
            'rack_id',
            'slot_number',
            'notes_physical_location',
        ]);
        $file = $request->file('file');
        $timestamp = now()->format('YmdHisv');
        $random = Str::random(10);
        $filename = str(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME).' '.$random.' '.$timestamp)->slug('_').'.'.strtolower($file->getClientOriginalExtension());
        $path = $file->storeAs('uploads', $filename, 'public');

        if (array_key_exists('notes_physical_location', $req_physical_location)) {
            $req_physical_location['notes'] = $req_physical_location['notes_physical_location'];
            unset($req_physical_location['notes_physical_location']);
        }

        // hitung label code
        $label_code = ['label_code' => 'L'.$req_physical_location['cabinet_id'].'-R'.$req_physical_location['rack_id'].'-S'.$req_physical_location['slot_number']];
        try {
            $archive = DB::transaction(function () use ($path, $filename, $file, $req_archive, $req_physical_location, $label_code) {
                $archive = Archive::create($req_archive + ['status' => 'uploaded']);
                $archive_id = $archive->getKey();
                ArchivePhysicalLocation::create(
                    $req_physical_location + $label_code + ['archive_id' => $archive_id]
                );

                $archive->files()->create([
                    'file_name' => $filename,
                    'file_size' => $file->getSize(),
                    'file_type' => strtolower($file->getClientOriginalExtension()),
                    'file_url' => $path,
                ]);

                return $archive->load('files');
            });
        } catch (\Throwable $th) {
            Storage::disk('public')->delete($path);

            throw $th;
        }

        return response()->json([
            'status' => 'success',
            'message' => 'sukses menyimpan archive',
            'data' => $archive,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $archive = Archive::with([
            'event',
            'category',
            'subcategory',
            'files',
            'physicalLocation.cabinet',
            'physicalLocation.rack',
        ])->findOrFail($id);

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
        // ngeget archive dengan table files sama physicalLocation
        $archive = Archive::with(['files', 'physicalLocation'])->findOrFail($id);

        // ambil yang dibutuhin (soalnya setornya beda nanti)
        $archiveData = $request->safe()->except([
            'file',
            'cabinet_id',
            'rack_id',
            'slot_number',
            'notes_physical_location',
        ]);

        $physicalLocationData = $request->safe()->only([
            'cabinet_id',
            'rack_id',
            'slot_number',
            'notes_physical_location',
        ]);

        if (array_key_exists('notes_physical_location', $physicalLocationData)) {
            $physicalLocationData['notes'] = $physicalLocationData['notes_physical_location'];
            unset($physicalLocationData['notes_physical_location']);
        }

        $existingPhysicalLocation = $archive->physicalLocation;
        $labelParts = [
            'cabinet_id' => $physicalLocationData['cabinet_id'] ?? $existingPhysicalLocation?->cabinet_id, // buat ngecek null apa nggaknya (kalau null dijadiin default dari database)  trus dijadiin label code terbaru
            'rack_id' => $physicalLocationData['rack_id'] ?? $existingPhysicalLocation?->rack_id,
            'slot_number' => $physicalLocationData['slot_number'] ?? $existingPhysicalLocation?->slot_number,
        ];
        if ($labelParts['cabinet_id'] && $labelParts['rack_id'] && $labelParts['slot_number']) {
            $physicalLocationData['label_code'] = 'L'.$labelParts['cabinet_id'].'-R'.$labelParts['rack_id'].'-S'.$labelParts['slot_number'];
        }

        $path = null;
        $filename = null;
        $file = null;
        $previousPaths = $archive->files->pluck('file_url')->filter()->all();
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $timestamp = now()->format('YmdHisv');
            $random = Str::random(10);
            $filename = str(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME).' '.$random.' '.$timestamp)->slug('_').'.'.strtolower($file->getClientOriginalExtension());
            $path = $file->storeAs('uploads', $filename, 'public');
            $archiveData['status'] = 'uploaded';
        }

        try {
            DB::transaction(function () use ($archive, $archiveData, $physicalLocationData, $file, $filename, $path) {
                if ($archiveData !== []) {
                    $archive->update($archiveData);
                }

                if ($physicalLocationData !== []) {
                    $archive->physicalLocation()->updateOrCreate(
                        ['archive_id' => $archive->id],
                        $physicalLocationData
                    );
                }

                if ($file && $filename && $path) {
                    $archive->files()->updateOrCreate(
                        ['archive_id' => $archive->id],
                        [
                            'file_name' => $filename,
                            'file_size' => $file->getSize(),
                            'file_type' => strtolower($file->getClientOriginalExtension()),
                            'file_url' => $path,
                        ]
                    );
                }
            });
        } catch (\Throwable $th) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }

            throw $th;
        }

        if ($path) {
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
        $filePaths = $archive->files->pluck('file_url')->filter()->all();

        DB::transaction(function () use ($archive) {
            $archive->delete();
        });

        Storage::disk('public')->delete($filePaths);

        return response()->json([
            'status' => 'success',
            'message' => 'sukses menghapus archive',
        ]);
    }
}
