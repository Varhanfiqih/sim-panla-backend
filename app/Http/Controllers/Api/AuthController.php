<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'nip' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('nip', $request->nip)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'NIP atau Password salah.'
            ], 401);
        }

        // Hapus token lama sebelum buat yang baru (cegah penumpukan di DB)
        $user->tokens()->delete();
        $token = $user->createToken('sim_panla_auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login Berhasil',
            'data' => [
                'user' => $user,
                'token' => $token
            ]
        ], 200);
    }

    public function logout(Request $request)
    {
        // Revoke the token that was used to authenticate the request
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil.'
        ], 200);
    }
}
