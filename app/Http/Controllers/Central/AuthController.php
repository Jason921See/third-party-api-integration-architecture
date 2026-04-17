<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Central\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Central login (SaaS admin / system user)
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }
        // \Log::info($user);
        // exit;
        // revoke old tokens (central only)
        $user->tokens()->delete();
        $expiresAt   = now()->addDays(1);
        $tokenResult = $user->createToken(
            'auth_token',
            ['*'],           // abilities
            $expiresAt,      // expiry
        );

        return response()->json([
            'access_token' => $tokenResult->plainTextToken,
            'token_type'   => 'Bearer',
            'expires_at'   => $expiresAt->toDateTimeString(),
            // 'user'         => [
            //     'id'    => $user->id,
            //     'name'  => $user->name,
            //     'email' => $user->email,
            // ]
        ]);
    }

    /**
     * Get authenticated central user
     */
    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user()
        ]);
    }

    /**
     * Logout (central)
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }
}
