<?php

namespace App\Actions\Auth;

use App\Data\AuthSession;
use App\Services\Auth\AccessTokenService;
use App\Services\Auth\RefreshTokenService;
use Illuminate\Http\Request;

class RefreshSessionAction
{
    public function __construct(
        private readonly AccessTokenService $accessTokenService,
        private readonly RefreshTokenService $refreshTokenService,
    ) {
    }

    public function __invoke(string $refreshToken, Request $request): AuthSession
    {
        $rotation = $this->refreshTokenService->rotate(
            $refreshToken,
            $request->ip(),
            $request->userAgent(),
        );

        $issuedAccessToken = $this->accessTokenService->issue($rotation['user']);

        return new AuthSession(
            user: $rotation['user'],
            accessToken: $issuedAccessToken['token'],
            refreshToken: $rotation['refresh_token'],
            expiresIn: $issuedAccessToken['expires_in'],
        );
    }
}
