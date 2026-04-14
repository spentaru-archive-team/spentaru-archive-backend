<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreArchiveRequest;
use App\Models\Archive;
use Illuminate\Http\Request;
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
        $req = $request->safe()->except('file');
        $file = $request->file('file');
        $timestamp = now()->format('YmdHisv');
        $random = Str::random(10);
        $filename = str(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME).' '.$random.' '.$timestamp)->slug('_').'.'.strtolower($file->getClientOriginalExtension());
        $path = $file->storeAs('uploads', $filename, 'public');

        try {
            $archive = DB::transaction(function () use ($path, $filename, $file, $req) {
                $archive = Archive::create($req + ['status' => 'uploaded']);

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
            'ocrText',
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
    public function update(Request $request, string $id)
    {
        $archive = Archive::with('files')->findOrFail($id);
        $validated = $request->validate([
            'title' => 'sometimes|required|string',
            'year' => 'sometimes|required|integer',
            'notes' => 'nullable|string',
            'file' => 'sometimes|required|mimes:pdf,doc,docx,xls,xlsx|max:10240',
            'event_id' => 'nullable|integer|min:0',
            'category_id' => 'sometimes|required|integer|min:0',
            'subcategory_id' => 'nullable|integer|min:0',
        ]);
        $payload = collect($validated)->except('file')->all();

        if (! $request->hasFile('file')) {
            $archive->update($payload);

            return response()->json([
                'status' => 'success',
                'message' => 'sukses memperbarui archive',
                'data' => $archive->fresh(['event', 'category', 'subcategory', 'files', 'physicalLocation.cabinet', 'physicalLocation.rack', 'ocrText']),
            ]);
        }

        $file = $request->file('file');
        $timestamp = now()->format('YmdHisv');
        $random = Str::random(10);
        $filename = str(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME).' '.$random.' '.$timestamp)->slug('_').'.'.strtolower($file->getClientOriginalExtension());
        $path = $file->storeAs('uploads', $filename, 'public');
        $previousPaths = $archive->files->pluck('file_url')->filter()->all();

        try {
            DB::transaction(function () use ($archive, $payload, $file, $filename, $path) {
                $archive->update($payload + ['status' => 'uploaded']);

                $archive->files()->updateOrCreate(
                    ['archive_id' => $archive->id],
                    [
                        'file_name' => $filename,
                        'file_size' => $file->getSize(),
                        'file_type' => strtolower($file->getClientOriginalExtension()),
                        'file_url' => $path,
                    ]
                );
            });
        } catch (\Throwable $th) {
            Storage::disk('public')->delete($path);

            throw $th;
        }

        Storage::disk('public')->delete($previousPaths);

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
