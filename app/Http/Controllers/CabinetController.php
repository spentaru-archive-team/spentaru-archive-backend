<?php

namespace App\Http\Controllers;

use App\Models\Cabinet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CabinetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->query('per_page', 10);
        $cabinets = Cabinet::with('racks')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'sukses mengambil semua lemari',
            'data' => $cabinets,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:cabinets,name',
        ]);

        $cabinet = Cabinet::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'sukses membuat lemari',
            'data' => $cabinet,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $cabinet = Cabinet::with(['racks' => function ($query) {
            $query->orderBy('rack_number');
        }])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'sukses mengambil detail lemari',
            'data' => $cabinet,
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $cabinet = Cabinet::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:cabinets,name,'.$id,
        ]);

        $cabinet->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'sukses memperbarui lemari',
            'data' => $cabinet,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $cabinet = Cabinet::with('racks.physicalLocations')->findOrFail($id);

        $hasOccupiedSlots = $cabinet->racks->contains(function ($rack) {
            return $rack->physicalLocations->isNotEmpty();
        });

        if ($hasOccupiedSlots) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tidak dapat menghapus lemari yang memiliki arsip tersimpan',
            ], 422);
        }

        $cabinet->racks()->delete();
        $cabinet->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'sukses menghapus lemari',
        ]);
    }
}
