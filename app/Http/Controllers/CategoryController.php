<?php

namespace App\Http\Controllers;

use App\Models\ArchiveCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    // PAGINATION DIUBAH JADI BISA DIATUR (all = true/false)
    public function index(Request $request): JsonResponse
    {
        $query = ArchiveCategory::with('subcategories')
            ->orderBy('created_at', 'desc');

        // Kalau minta semua data (tanpa pagination)
        if ($request->boolean('all')) {
            $categories = $query->get();
        } else {
            $perPage = $request->query('per_page', 10);
            $categories = $query->paginate($perPage);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'sukses mengambil semua kategori',
            'data' => $categories,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:archive_categories,name',
            'description' => 'nullable|string',
        ]);

        $category = ArchiveCategory::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'sukses membuat kategori',
            'data' => $category,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $category = ArchiveCategory::with(['subcategories', 'archives'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'sukses mengambil detail kategori',
            'data' => $category,
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $category = ArchiveCategory::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:archive_categories,name,' . $id,
            'description' => 'nullable|string',
        ]);

        $category->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'sukses memperbarui kategori',
            'data' => $category,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $category = ArchiveCategory::with('archives', 'subcategories')->findOrFail($id);

        if ($category->archives()->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tidak dapat menghapus kategori yang memiliki arsip',
            ], 422);
        }

        $category->subcategories()->delete();
        $category->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'sukses menghapus kategori',
        ]);
    }
}
