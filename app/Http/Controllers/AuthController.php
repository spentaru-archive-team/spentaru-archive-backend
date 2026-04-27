<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required|string|max:120',
            'password' => 'required|string',
        ]);


        if (! Auth::attempt($credentials)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid credentials',
            ], 401);
        }

        $request->session()->regenerate();

        $user = Auth::user();
        $user->last_login_at = now();
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            'data' => [
                'id' => $user->getKey(),
                'name' => $user->name,
                'username' => $user->username,
                'role' => $user->role,
                'last_login_at' => $user->last_login_at,
                'created_at' => optional($user->created_at)->toJSON(),
                'updated_at' => optional($user->updated_at)->toJSON(),
            ],
        ]);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'status' => 'success',
            'message' => 'Logout sukses',
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'status' => 'success',
            'message' => 'Profil pengguna berhasil diambil',
            'data' => [
                'id' => $user->getKey(),
                'name' => $user->name,
                'username' => $user->username,
                'role' => $user->role,
                'last_login_at' => $user->last_login_at,
                'created_at' => optional($user->created_at)->toJSON(),
                'updated_at' => optional($user->updated_at)->toJSON(),
            ],
        ]);
    }
}
