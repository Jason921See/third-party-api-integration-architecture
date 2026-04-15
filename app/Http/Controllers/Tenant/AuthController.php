<?php

namespace App\Http\Controllers\Tenant;

use App\Models\Tenant\User;
use App\Services\Tenant\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\RefreshToken;

class AuthController extends BaseController
{
    public function __construct(protected AuthService $authService) {}

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // Revoke all existing tokens
        $user->tokens()->delete();

        // Create Sanctum token
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
        ]);
    }

    public function refresh(Request $request)
    {
        $request->validate([
            'refresh_token' => 'required|string',
        ]);

        try {
            // Use Passport's internal server to refresh token
            $response = $this->authService->callPassportRefresh($request->refresh_token);

            if (isset($response['error'])) {
                return response()->json(['message' => 'Invalid or expired refresh token'], 401);
            }

            return response()->json([
                'access_token'  => $response['access_token'],
                'refresh_token' => $response['refresh_token'],
                'token_type'    => 'Bearer',
                'expires_in'    => $response['expires_in'],
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Could not refresh token: ' . $e->getMessage()], 401);
        }
    }


    public function logout(Request $request)
    {
        // Revoke current token only
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}
