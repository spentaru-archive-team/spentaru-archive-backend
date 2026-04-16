<?php

namespace App\Http\Controllers;

use App\Models\Rack;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RackController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->query('per_page', 10);
        $query = Rack::with('cabinet');

        if ($request->has('cabinet_id')) {
            $query->where('cabinet_id', $request->cabinet_id);
        }

        $racks = $query->orderBy('cabinet_id')
            ->orderBy('rack_number')
            ->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'sukses mengambil semua rak',
            'data' => $racks,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cabinet_id' => 'required|integer|exists:cabinets,id',
            'rack_number' => 'required|integer|min:1',
            'capacity' => 'required|integer|min:1|max:100',
        ]);

        $exists = Rack::where('cabinet_id', $validated['cabinet_id'])
            ->where('rack_number', $validated['rack_number'])
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => 'error',
                'message' => 'Rak dengan nomor yang sama sudah ada di lemari ini',
            ], 422);
        }

        $rack = Rack::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'sukses membuat rak',
            'data' => $rack->load('cabinet'),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $rack = Rack::with(['cabinet', 'physicalLocations'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'sukses mengambil detail rak',
            'data' => $rack,
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $rack = Rack::findOrFail($id);

        $validated = $request->validate([
            'cabinet_id' => 'sometimes|required|integer|exists:cabinets,id',
            'rack_number' => 'sometimes|required|integer|min:1',
            'capacity' => 'sometimes|required|integer|min:1|max:100',
        ]);

        if (isset($validated['cabinet_id']) && isset($validated['rack_number'])) {
            $exists = Rack::where('cabinet_id', $validated['cabinet_id'])
                ->where('rack_number', $validated['rack_number'])
                ->where('id', '!=', $id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Rak dengan nomor yang sama sudah ada di lemari ini',
                ], 422);
            }
        }

        $rack->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'sukses memperbarui rak',
            'data' => $rack->load('cabinet'),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $rack = Rack::with('physicalLocations')->findOrFail($id);

        if ($rack->physicalLocations()->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tidak dapat menghapus rak yang memiliki arsip tersimpan',
            ], 422);
        }

        $rack->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'sukses menghapus rak',
        ]);
    }
}
