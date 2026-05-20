<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\UpdateResetPasswordRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $q = $request->query('q');
        $role = $request->query('role');
        $search = User::search($q ?? '')
            ->query(function ($query) use ($role) {
                if ($role !== null && $role !== '') {
                    $query->where('role', $role);
                }
            })
            ->orderBy('id', 'asc');

        $user = $search->paginate(10)->appends($request->query());

        if (($q !== null && $q !== '') || ($role !== null && $role !== '')) {
            if ($user->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User tidak ditemukan',
                ], 404);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'sukses menampilkan data user',
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
            'message' => 'sukses menampilkan data user dengan id '.$id,
            'data' => $user,
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, string $id)
    {
        $user = User::findOrFail($id);

        if ($request->has('role') && $request->role !== $user->role) {
            Log::warning('User role changed', [
                'user_id' => $user->id,
                'username' => $user->username,
                'old_role' => $user->role,
                'new_role' => $request->role,
                'changed_by' => Auth::id(),
            ]);
        }

        $updateData = $request->safe()->except('password');

        if ($request->user()?->role !== 'admin' || $request->route('id') === (string) $request->user()->id) {
            unset($updateData['role']);
        }

        if ($request->filled('password')) {
            $hashed_password = bcrypt($request->password);
            $user->update($updateData + ['password' => $hashed_password]);
        } else {
            $user->update($updateData);
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
        if ($id == Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error: Anda tidak diizinkan menghapus akun Anda sendiri yang sedang aktif.',
            ], 403);
        }

        $user = User::findOrFail($id);
        $username = $user->username;
        $user->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'sukses menghapus user '.$username,
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
            'message' => 'Sukses mengubah password '.$username,
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
