<?php

namespace App\Http\Controllers;

use App\Models\Subcategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubcategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Subcategory::with('category')
            ->orderBy('created_at', 'desc');

        // filter by category (untuk dependent dropdown)
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // tanpa pagination
        if ($request->boolean('all')) {
            $subcategories = $query->get();

            return response()->json([
                'status' => 'success',
                'message' => 'sukses mengambil semua subkategori',
                'data' => $subcategories,
            ]);
        }

        // dengan pagination
        $perPage = $request->query('per_page', 10);
        $subcategories = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'sukses mengambil semua subkategori',
            'data' => $subcategories,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => 'required|integer|exists:archive_categories,id',
            'name' => 'required|string|max:255',
        ]);

        $exists = Subcategory::where('category_id', $validated['category_id'])
            ->where('name', $validated['name'])
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => 'error',
                'message' => 'Subkategori dengan nama yang sama sudah ada di kategori ini',
            ], 422);
        }

        $subcategory = Subcategory::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'sukses membuat subkategori',
            'data' => $subcategory->load('category'),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $subcategory = Subcategory::with(['category', 'archives'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'sukses mengambil detail subkategori',
            'data' => $subcategory,
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $subcategory = Subcategory::findOrFail($id);

        $validated = $request->validate([
            'category_id' => 'sometimes|required|integer|exists:archive_categories,id',
            'name' => 'sometimes|required|string|max:255',
        ]);

        if (isset($validated['category_id']) && isset($validated['name'])) {
            $exists = Subcategory::where('category_id', $validated['category_id'])
                ->where('name', $validated['name'])
                ->where('id', '!=', $id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Subkategori dengan nama yang sama sudah ada di kategori ini',
                ], 422);
            }
        }

        $subcategory->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'sukses memperbarui subkategori',
            'data' => $subcategory->load('category'),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $subcategory = Subcategory::with('archives')->findOrFail($id);

        if ($subcategory->archives()->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tidak dapat menghapus subkategori yang memiliki arsip',
            ], 422);
        }

        $subcategory->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'sukses menghapus subkategori',
        ]);
    }
}
