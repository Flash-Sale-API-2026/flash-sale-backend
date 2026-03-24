<?php

namespace App\Actions\Auth;

use App\Data\AuthSession;
use App\Models\User;
use App\Services\Auth\AccessTokenService;
use App\Services\Auth\RefreshTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RegisterUserAction
{
    public function __construct(
        private readonly AccessTokenService $accessTokenService,
        private readonly RefreshTokenService $refreshTokenService,
    ) {
    }

    /**
     * @param  array{name: string, email: string, password: string}  $payload
     */
    public function __invoke(array $payload, Request $request): AuthSession
    {
        return DB::transaction(function () use ($payload, $request): AuthSession {
            $user = User::query()->create($payload);

            $issuedAccessToken = $this->accessTokenService->issue($user);
            $refreshToken = $this->refreshTokenService->issue(
                $user,
                $request->ip(),
                $request->userAgent(),
            );

            return new AuthSession(
                user: $user,
                accessToken: $issuedAccessToken['token'],
                refreshToken: $refreshToken,
                expiresIn: $issuedAccessToken['expires_in'],
            );
        });
    }
}
