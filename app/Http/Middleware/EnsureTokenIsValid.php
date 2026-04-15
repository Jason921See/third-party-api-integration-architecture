<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EnsureTokenIsValid
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Illuminate\Http\JsonResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            Log::warning('Authorization header or token missing.');
            return $this->unauthorizedResponse('Token missing');
        }

        // Log::info('Authorization Token: ' . $token);

        try {
            // Validate the token and get user details
            $userId = $this->validateToken($token);

            if ($userId) {
                $user = User::find($userId);

                if (!$user) {
                    return $this->unauthorizedResponse('User not found.');
                }

                Auth::login($user);

                return $next($request);
            } else {
                return $this->unauthorizedResponse('Invalid token');
            }
        } catch (\Exception $e) {
            Log::error('Error during token validation: ' . $e->getMessage());
            return response()->json(['message' => 'Server Error: Unable to process the request'], 500);
        }
    }

    /**
     * Validate the provided token and return the associated user ID.
     *
     * @param string $token
     * @return int|null
     */
    private function validateToken(string $token)
    {
        $tenantId = tenant('id');

        $impersonationTokenUser = DB::connection('central')
            ->table('tenant_user_impersonation_tokens')
            ->where('tenant_id', $tenantId)
            ->where('token', $token)
            ->first();

        return $impersonationTokenUser ? $impersonationTokenUser->user_id : null;
    }


    /**
     * Return an unauthorized JSON response.
     *
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    private function unauthorizedResponse(string $message)
    {
        Log::warning($message);
        return response()->json(['message' => 'Unauthorized: ' . $message], 401);
    }
}
