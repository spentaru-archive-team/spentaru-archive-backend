<?php

namespace App\Http\Controllers;

use App\Models\Archive;
use App\Models\Subcategory;
use App\Models\ArchiveCategory;
use App\Models\User;

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

        // total subkategori arsip
        $archive_subcategory_total = Subcategory::count();
        
        // total user
        $user_total = User::count();

        $total = [
            'archive_total' => $archive_total,
            'archive_category_total' => $archive_category_total,
            'archive_subcategory_total' => $archive_subcategory_total,
            'user_total' => $user_total
        ];

        return response()->json([
            'status' => 'success',
            'message' => 'sukses mengambil jumlah arsip, user, kategori, dan subkategori',
            'data' => $total,
        ]);
    }
}
