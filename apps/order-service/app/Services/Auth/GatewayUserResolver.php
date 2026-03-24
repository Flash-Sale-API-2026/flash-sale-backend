<?php

namespace App\Services\Auth;

use Illuminate\Http\Request;

class GatewayUserResolver
{
    public function headerName(): string
    {
        return (string) config('services.gateway.user_id_header', 'X-Internal-User-Id');
    }

    public function rawUserId(Request $request): ?string
    {
        $value = $request->header($this->headerName());

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
