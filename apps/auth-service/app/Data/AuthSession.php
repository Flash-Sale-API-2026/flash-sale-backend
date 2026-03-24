<?php

namespace App\Data;

use App\Models\User;

readonly class AuthSession
{
    public function __construct(
        public User $user,
        public string $accessToken,
        public string $refreshToken,
        public int $expiresIn,
    ) {
    }
}
