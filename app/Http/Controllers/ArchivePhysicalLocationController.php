<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreArchivePhysicalLocationRequest;
use App\Http\Requests\UpdateArchivePhysicalLocationRequest;
use App\Models\Archive;
use App\Models\ArchivePhysicalLocation;
use App\Models\Cabinet;
use App\Models\Rack;
use Illuminate\Http\Request;

class ArchivePhysicalLocationController extends Controller
{
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

    private function normalizePayload(array $payload): array
    {
        if (array_key_exists('notes_physical_location', $payload)) {
            $payload['notes'] = $payload['notes_physical_location'];
            unset($payload['notes_physical_location']);
        }

        return $payload;
    }

    private function buildLabelCode(array $payload): string
    {
        $cabinet_number = Cabinet::where('id', $payload['cabinet_id'])->value('cabinet_number');
        $rack_number = Rack::where('id', $payload['rack_id'])->value('rack_number');
        return 'L' . $cabinet_number . '-R' . $rack_number . '-S' . $payload['slot_number'];
    }

    public function index(Request $request)
    {
        $q = $request->query('q');
        if ($request->boolean('all')) {
            $physicalLocations = ArchivePhysicalLocation::search($q ?? '')->query(function ($query) use ($q) {
                $query->with(['archive.files', 'cabinet', 'rack'])->filter()->sort();

                if (filled($q)) {
                    $query->orWhereHas('archive', function ($archiveQuery) use ($q) {
                        $archiveQuery->where('title', 'like', "%{$q}%");
                    })->orWhereHas('cabinet', function ($cabinetQuery) use ($q) {
                        $cabinetQuery->where('name', 'like', "%{$q}%")
                            ->orWhere('cabinet_number', 'like', "%{$q}%");
                    });
                }
            })->get();
        } else {
            $physicalLocations = ArchivePhysicalLocation::search($q ?? '')->query(function ($query) use ($q) {
                $query->with(['archive.files', 'cabinet', 'rack'])->filter()->sort();

                if (filled($q)) {
                    $query->orWhereHas('archive', function ($archiveQuery) use ($q) {
                        $archiveQuery->where('title', 'like', "%{$q}%");
                    })->orWhereHas('cabinet', function ($cabinetQuery) use ($q) {
                        $cabinetQuery->where('name', 'like', "%{$q}%")
                            ->orWhere('cabinet_number', 'like', "%{$q}%");
                    });
                }
            })->paginate(10);
        }

        if (empty($physicalLocations)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Physical location tidak ditemukan',
                ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'sukses menampilkan semua physical location dari arsip',
            'data' => $physicalLocations,
        ]);
    }

    public function show(string $id)
    {
        $archive = Archive::with(['physicalLocation.cabinet', 'physicalLocation.rack'])->findOrFail($id);
        $physicalLocation = $archive->physicalLocation;

        if (! $physicalLocation) {
            return response()->json([
                'status' => 'error',
                'message' => 'Physical location tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'sukses mengambil physical location archive',
            'data' => $physicalLocation,
        ]);
    }

    public function store(StoreArchivePhysicalLocationRequest $request, string $id)
    {
        $archive = Archive::with('physicalLocation')->findOrFail($id);
        $payload = $this->normalizePayload($request->validated());
        $payload['label_code'] = $this->buildLabelCode($payload);

        if ($archive->physicalLocation) {
            return response()->json([
                'status' => 'error',
                'message' => 'Physical location archive sudah ada',
            ], 422);
        }

        $physicalLocation = $archive->physicalLocation()->create($payload);

        Rack::find($payload['rack_id'])->increment('used_capacity');

        return response()->json([
            'status' => 'success',
            'message' => 'sukses menyimpan physical location archive',
            'data' => $physicalLocation->load(['cabinet', 'rack']),
        ], 201);
    }

    public function update(UpdateArchivePhysicalLocationRequest $request, string $id)
    {
        $archive = Archive::with('physicalLocation.rack')->findOrFail($id);
        $physicalLocation = $archive->physicalLocation;

        if (! $physicalLocation) {
            return response()->json([
                'status' => 'error',
                'message' => 'Physical location tidak ditemukan',
            ], 404);
        }

        $payload = $this->normalizePayload($request->validated());
        $updateData = [];

        foreach ($payload as $key => $value) {
            if (! $this->hasSameValue($physicalLocation->{$key}, $value)) {
                $updateData[$key] = $value;
            }
        }

        $mergedData = [
            'cabinet_id' => array_key_exists('cabinet_id', $payload) ? $payload['cabinet_id'] : $physicalLocation->cabinet_id,
            'rack_id' => array_key_exists('rack_id', $payload) ? $payload['rack_id'] : $physicalLocation->rack_id,
            'slot_number' => array_key_exists('slot_number', $payload) ? $payload['slot_number'] : $physicalLocation->slot_number,
        ];

        $labelCode = $this->buildLabelCode($mergedData);
        if (! $this->hasSameValue($physicalLocation->label_code, $labelCode)) {
            $updateData['label_code'] = $labelCode;
        }

        $oldRackId = $physicalLocation->rack_id;
        $newRackId = array_key_exists('rack_id', $payload) ? $payload['rack_id'] : $oldRackId;
        $rackChanged = $oldRackId !== $newRackId;

        if ($updateData !== []) {
            $physicalLocation->update($updateData);
        }

        if ($rackChanged) {
            Rack::find($oldRackId)->decrement('used_capacity');
            Rack::find($newRackId)->increment('used_capacity');
        }

        return response()->json([
            'status' => 'success',
            'message' => 'sukses memperbarui physical location archive',
            'data' => $physicalLocation->fresh(['cabinet', 'rack']),
        ]);
    }

    public function destroy(string $id)
    {
        $archive = Archive::with('physicalLocation.rack')->findOrFail($id);
        $physicalLocation = $archive->physicalLocation;

        if (! $physicalLocation) {
            return response()->json([
                'status' => 'error',
                'message' => 'Physical location tidak ditemukan',
            ], 404);
        }

        $physicalLocation->rack->decrement('used_capacity');
        $physicalLocation->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'sukses menghapus physical location archive',
        ]);
    }
}
