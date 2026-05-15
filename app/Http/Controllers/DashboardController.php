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

        // total arsip aktif
        $archive_active_total = Archive::where('retention_status', 'active')->count();

        // total arsip yang siap untuk dihancurkan
        $archive_ready_total = Archive::where('retention_status', 'ready_for_destruction')->count();

        // total arsip yang ditahan
        $archive_retained_total = Archive::where('retention_status', 'retained')->count();

        // total arsip yang sudah dihancurkan
        $archive_destroyed_total = Archive::where('retention_status', 'destroyed')->count();

        // total kategori arsip
        $archive_category_total = ArchiveCategory::count();

        // total lemari arsip
        $cabinet_total = Cabinet::count();

        // total subkategori arsip
        $archive_subcategory_total = Subcategory::count();

        // total user
        $user_total = User::count();

        $total = [
            'total' => $archive_total,
            'active' => $archive_active_total,
            'ready' => $archive_ready_total,
            'retained' => $archive_retained_total,
            'destroyed' => $archive_destroyed_total,
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
        $user = $request->user();
        $data = Event::with('user')
            ->whereDoesntHave('archives')
            ->where('user_id', $user->id)
            ->orderByDesc('date')
            ->orderByDesc('created_at')->get();

        return response()->json([
            'status' => 'success',
            'message' => 'sukses mengambil daftar event yang belum diupload arsipnya',
            'data' => $data,
        ]);
    }
}
