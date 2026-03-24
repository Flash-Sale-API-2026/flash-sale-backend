<?php

namespace App\Services\Auth;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RefreshTokenService
{
    public function issue(User $user, ?string $ipAddress = null, ?string $userAgent = null): string
    {
        $secret = Str::random(96);

        $refreshToken = RefreshToken::query()->create([
            'user_id' => $user->id,
            'token_hash' => Hash::make($secret),
            'expires_at' => now()->addDays($this->ttlDays()),
            'created_by_ip' => $ipAddress,
            'user_agent' => Str::limit((string) $userAgent, 255, ''),
        ]);

        return $refreshToken->id.'.'.$secret;
    }

    /**
     * @return array{user: User, refresh_token: string}
     */
    public function rotate(string $plainTextToken, ?string $ipAddress = null, ?string $userAgent = null): array
    {
        [$tokenId, $secret] = $this->parse($plainTextToken);

        return DB::transaction(function () use ($tokenId, $secret, $ipAddress, $userAgent): array {
            $storedToken = RefreshToken::query()
                ->with('user')
                ->lockForUpdate()
                ->find($tokenId);

            if ($storedToken === null || $storedToken->revoked_at !== null || $storedToken->expires_at->isPast()) {
                throw new InvalidRefreshTokenException('The refresh token is invalid.');
            }

            if (! Hash::check($secret, $storedToken->token_hash)) {
                throw new InvalidRefreshTokenException('The refresh token is invalid.');
            }

            $storedToken->forceFill([
                'last_used_at' => now(),
                'revoked_at' => now(),
            ])->save();

            return [
                'user' => $storedToken->user,
                'refresh_token' => $this->issue($storedToken->user, $ipAddress, $userAgent),
            ];
        });
    }

    private function ttlDays(): int
    {
        return (int) config('services.auth_tokens.refresh_token_ttl_days', 30);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parse(string $plainTextToken): array
    {
        $parts = explode('.', $plainTextToken, 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new InvalidRefreshTokenException('The refresh token is invalid.');
        }

        return [$parts[0], $parts[1]];
    }
}
