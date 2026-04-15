<?php

namespace App\Services\Tenant;

use Illuminate\Http\Request;
use Laravel\Passport\Client;
use Laravel\Passport\RefreshToken;

class AuthService
{
    public function callPassportRefresh(string $refreshToken): array
    {
        $client = Client::where('personal_access_client', true)
            ->where('revoked', false)
            ->firstOrFail();

        $request = Request::create('/oauth/token', 'POST', [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id'     => $client->id,
            'client_secret' => $client->secret,
            'scope'         => '',
        ]);

        $response = app()->handle($request);
        return json_decode($response->getContent(), true);
    }

    // public function getRefreshToken(string $tokenId): string
    // {
    //     $refreshToken = RefreshToken::where('access_token_id', $tokenId)
    //         ->where('revoked', false)
    //         ->first();

    //     return $refreshToken ? $refreshToken->id : '';
    // }
}
