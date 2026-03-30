<?php

namespace App\Services\Auth;

use Illuminate\Http\Request;

class InternalServiceAuthenticator
{
    public function headerName(): string
    {
        return (string) config('services.internal.token_header', 'X-Internal-Service-Token');
    }

    public function rawToken(Request $request): ?string
    {
        $value = $request->header($this->headerName());

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    public function isAuthorized(Request $request): bool
    {
        $configuredToken = (string) config('services.internal.token');
        $requestToken = $this->rawToken($request);

        if ($configuredToken === '' || $requestToken === null) {
            return false;
        }

        return hash_equals($configuredToken, $requestToken);
    }
}
