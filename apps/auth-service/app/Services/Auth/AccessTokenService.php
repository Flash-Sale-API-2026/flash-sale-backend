<?php

namespace App\Services\Auth;

use App\Models\User;
use JsonException;

class AccessTokenService
{
    /**
     * @return array{token: string, expires_in: int}
     */
    public function issue(User $user): array
    {
        $issuedAt = now()->timestamp;
        $expiresAt = now()->addMinutes($this->ttlMinutes())->timestamp;

        $payload = [
            'iss' => $this->issuer(),
            'sub' => (string) $user->id,
            'type' => 'access',
            'iat' => $issuedAt,
            'nbf' => $issuedAt,
            'exp' => $expiresAt,
        ];

        return [
            'token' => $this->encode($payload),
            'expires_in' => $expiresAt - $issuedAt,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function decode(string $token): array
    {
        [$encodedHeader, $encodedPayload, $encodedSignature] = explode('.', $token);

        $signingInput = $encodedHeader.'.'.$encodedPayload;
        $expectedSignature = $this->base64UrlEncode(
            hash_hmac('sha256', $signingInput, $this->secret(), true)
        );

        if (! hash_equals($expectedSignature, $encodedSignature)) {
            throw new InvalidCredentialsException('The access token signature is invalid.');
        }

        return $this->decodeJson($this->base64UrlDecode($encodedPayload));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function encode(array $payload): string
    {
        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256',
        ];

        $encodedHeader = $this->base64UrlEncode($this->encodeJson($header));
        $encodedPayload = $this->base64UrlEncode($this->encodeJson($payload));
        $signature = hash_hmac('sha256', $encodedHeader.'.'.$encodedPayload, $this->secret(), true);

        return implode('.', [
            $encodedHeader,
            $encodedPayload,
            $this->base64UrlEncode($signature),
        ]);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function encodeJson(array $value): string
    {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidCredentialsException('Unable to encode JWT payload.', previous: $exception);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $value): array
    {
        try {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidCredentialsException('Unable to decode JWT payload.', previous: $exception);
        }
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;

        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        if ($decoded === false) {
            throw new InvalidCredentialsException('Unable to decode JWT segment.');
        }

        return $decoded;
    }

    private function issuer(): string
    {
        return (string) config('services.auth_tokens.issuer', 'flash-sale-auth');
    }

    private function secret(): string
    {
        return (string) config('services.auth_tokens.secret', 'flash-sale-demo-jwt-secret');
    }

    private function ttlMinutes(): int
    {
        return (int) config('services.auth_tokens.access_token_ttl_minutes', 15);
    }
}
