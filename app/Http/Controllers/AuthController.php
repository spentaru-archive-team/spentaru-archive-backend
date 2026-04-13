<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|string|email|max:120',
            'password' => 'required|string',
        ]);

        // dd($credentials);

        if (! Auth::attempt($credentials)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid credentials',
            ], 401);
        }

        $user = Auth::user();
        $token = $user->createToken('auth_token')->plainTextToken;
        
        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            'data' => [
                'id' => $user->getKey(),
                'email' => $user->email,
                'role' => $user->role,
                'created_at' => optional($user->created_at)->toJSON(),
                'updated_at' => optional($user->updated_at)->toJSON(),
                'token' => $token,
            ],
        ]);
    }
    
    public function logout(Request $request)
    {
        $token = $request->user()?->currentAccessToken();
        
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Logout sukses',
        ]);
    }
    
    public function me(Request $request) {
        
        $user = $request->user();
        return response()->json([
            'status' => 'success',
            'message' => 'Token Verified',
            'data' => [
                'id' => $user->getKey(),
                'email' => $user->email,
                'role' => $user->role,
                'created_at' => optional($user->created_at)->toJSON(),
                'updated_at' => optional($user->updated_at)->toJSON()
            ],
        ]);
    }
}
