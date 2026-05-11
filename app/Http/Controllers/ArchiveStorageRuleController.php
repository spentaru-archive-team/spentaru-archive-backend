<?php

namespace App\Http\Controllers;

use App\Http\Requests\ArchiveStorageRuleControllerStoreRequest;
use App\Http\Requests\ArchiveStorageRuleControllerUpdateRequest;
use App\Models\ArchiveStorageRule;

class ArchiveStorageRuleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = ArchiveStorageRule::with(['category', 'subcategory', 'cabinet'])->paginate(10);

        return response()->json([
            'status' => 'success',
            'message' => 'sukses mengambil semuaarchive storage rules',
            'data' => $data,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ArchiveStorageRuleControllerStoreRequest $request)
    {
        $data = ArchiveStorageRule::create($request->validated());

        return response()->json(
            [
                'status' => 'success',
                'message' => 'sukses menambahkan peraturan penyimpanan arsip',
                'data' => $data,
            ],
            201,
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $data = ArchiveStorageRule::with(['category', 'subcategory', 'cabinet'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'sukses mengambil 1 peraturan penyimpanan arsip ',
            'data' => $data,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ArchiveStorageRuleControllerUpdateRequest $request, string $id)
    {
        $row = ArchiveStorageRule::findOrFail($id);
        $row->update($request->validated());

        return response()->json(
            [
                'status' => 'success',
                'message' => 'sukses update peraturan penyimpanan arsip',
                'data' => $row,
            ],
            200,
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $archive = ArchiveStorageRule::findOrFail($id);

        $archive->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'sukses menghapus 1 peraturan penyimpanan arsip',
        ]);
    }
}
