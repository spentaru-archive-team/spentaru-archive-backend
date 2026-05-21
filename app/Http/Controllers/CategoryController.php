<?php

namespace App\Http\Controllers;

use App\Models\ArchiveCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    private function applyCategorySearch(Builder $query, string $q): void
    {
        $escaped = str_replace(['%', '_', '\\'], ['\%', '\_', '\\\\'], $q);

        $query->where(function (Builder $searchQuery) use ($escaped) {
            $searchQuery->where('archive_categories.name', 'like', "%{$escaped}%")
                ->orWhere('archive_categories.description', 'like', "%{$escaped}%")
                ->orWhereHas('subcategories', function (Builder $categoryQuery) use ($escaped) {
                    $categoryQuery->where('name', 'like', "%{$escaped}%");
                });
        });
    }

    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        $categories = ArchiveCategory::search('')
            ->query(function ($query) use ($q) {
                $query->with('subcategories');

                if (filled($q)) {
                    $this->applyCategorySearch($query, $q);
                }
            })->orderBy('id');

        if ($request->boolean('all')) {
            $categories = $categories->get();
        } else {
            $perPage = (int) $request->query('per_page', 10);
            $categories = $categories->paginate($perPage);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Sukses mengambil semua kategori',
            'data' => $categories,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:archive_categories,name',
            'description' => 'nullable|string',
            'subcategories' => 'nullable|array',
            'subcategories.*.name' => 'required_with:subcategories|string|max:255',
        ]);

        $category = ArchiveCategory::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'has_subcategory' => !empty($validated['subcategories']),
        ]);

        $subcategories = $validated['subcategories'] ?? [];
        foreach ($subcategories as $subcat) {
            $category->subcategories()->create([
                'name' => $subcat['name'],
            ]);
        }

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
            'name' => 'sometimes|required|string|max:255|unique:archive_categories,name,'.$id,
            'description' => 'nullable|string',
            'subcategories' => 'nullable|array',
            'subcategories.*.id' => 'sometimes|integer|exists:subcategories,id',
            'subcategories.*.name' => 'required_with:subcategories|string|max:255',
        ]);

        $category->update([
            'name' => $validated['name'] ?? $category->name,
            'description' => $validated['description'] ?? $category->description,
            'has_subcategory' => !empty($validated['subcategories']),
        ]);

        $subcategories = $validated['subcategories'] ?? [];
        foreach ($subcategories as $subcat) {
            if (isset($subcat['id'])) {
                $subcategory = $category->subcategories()->find($subcat['id']);
                if ($subcategory) {
                    $subcategory->update([
                        'name' => $subcat['name'],
                    ]);
                }
            } else {
                $category->subcategories()->create([
                    'name' => $subcat['name'],
                ]);
            }
        }

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
