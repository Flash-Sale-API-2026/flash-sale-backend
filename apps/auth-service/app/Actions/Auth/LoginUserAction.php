<?php

namespace App\Actions\Auth;

use App\Data\AuthSession;
use App\Models\User;
use App\Services\Auth\AccessTokenService;
use App\Services\Auth\InvalidCredentialsException;
use App\Services\Auth\RefreshTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class LoginUserAction
{
    public function __construct(
        private readonly AccessTokenService $accessTokenService,
        private readonly RefreshTokenService $refreshTokenService,
    ) {
    }

    public function __invoke(string $email, string $password, Request $request): AuthSession
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw new InvalidCredentialsException('The provided credentials are invalid.');
        }

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
    }
}
