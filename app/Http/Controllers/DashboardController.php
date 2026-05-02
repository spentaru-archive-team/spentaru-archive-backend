<?php

namespace App\Http\Controllers;

use App\Models\Archive;
use App\Models\ArchiveCategory;
use App\Models\Cabinet;
use App\Models\Event;
use App\Models\Subcategory;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // total arsip
        $archive_total = Archive::count();

        // total kategori arsip
        $archive_category_total = ArchiveCategory::count();

        // total lemari arsip
        $cabinet_total = Cabinet::count();

        // total subkategori arsip
        $archive_subcategory_total = Subcategory::count();

        // total user
        $user_total = User::count();

        $total = [
            'archive_total' => $archive_total,
            'archive_category_total' => $archive_category_total,
            'cabinet_total' => $cabinet_total,
            'archive_subcategory_total' => $archive_subcategory_total,
            'user_total' => $user_total,
        ];

        return response()->json([
            'status' => 'success',
            'message' => 'sukses mengambil jumlah arsip, user, kategori, dan subkategori',
            'data' => $total,
        ]);
    }

    public function teachersWithoutArchives(Request $request): JsonResponse
    {
        $query = Event::with('user')
            ->whereDoesntHave('archives')
            ->whereHas('user', fn ($query) => $query->where('role', 'guru'))
            ->orderByDesc('date')
            ->orderByDesc('created_at');

        if ($request->boolean('all')) {
            $data = $query->get();
        } else {
            $perPage = $request->query('per_page', 10);
            $data = $query->paginate($perPage);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'sukses mengambil daftar guru yang belum upload arsip',
            'data' => $data,
        ]);
    }
}
