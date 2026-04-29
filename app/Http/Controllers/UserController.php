<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\UpdateResetPasswordRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->boolean('all')) {
            $user = User::all();
        } else {
            $user = User::paginate(10);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'sukses menampilkan semua data user',
            'data' => $user,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request)
    {
        $hashed_password = bcrypt($request->password);
        $data = User::create($request->safe()->except('password') + ['password' => $hashed_password]);

        return response()->json([
            'status' => 'success',
            'message' => 'sukses membuat user',
            'data' => $data,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = User::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'message' => 'sukses menampilkan data user dengan id ' . $id,
            'data' => $user,
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, string $id)
    {
        $user = User::findOrFail($id);

        if ($request->filled('password')) {
            $hashed_password = bcrypt($request->password);
            $user->update($request->safe()->except('password') + ['password' => $hashed_password]);
        } else {
            $user->update($request->safe()->except('password'));
        }

        return response()->json([
            'status' => 'success',
            'message' => 'sukses mengupdate user',
            'data' => $request->safe()->except('password'),
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $user = User::findOrFail($id);
        $username = $user->username;
        $user->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'sukses menghapus user ' . $username,
        ]);
    }

    public function reset_password(UpdateResetPasswordRequest $request, string $id)
    {
        $user = User::findOrFail($id);
        $username = $user->username;

        if ($request->filled('password')) {
            $hashed_password = bcrypt($request->password);
            $user->update($request->safe()->except('password') + ['password' => $hashed_password]);
        } else {
            $user->update($request->safe()->except('password'));
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Sukses mengubah password ' . $username,
        ], 200);
    }

    public function updateMe(UpdateProfileRequest $request)
    {
        $user = $request->user();
        $validated = $request->validated();

        if (isset($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'sukses mengupdate profil',
            'data' => $user->fresh(),
        ]);
    }
}
