<?php

use App\Models\User;
use App\Services\Auth\AccessTokenService;
use Tests\TestCase;

uses(TestCase::class);

it('issues jwt access tokens with the expected claims', function () {
    config()->set('services.auth_tokens.issuer', 'flash-sale-auth');
    config()->set('services.auth_tokens.secret', 'flash-sale-demo-jwt-secret');
    config()->set('services.auth_tokens.access_token_ttl_minutes', 15);

    $service = app(AccessTokenService::class);

    $user = new User();
    $user->id = 42;
    $user->email = 'demo@example.com';

    $issuedToken = $service->issue($user);
    $payload = $service->decode($issuedToken['token']);

    expect($payload['iss'])->toBe('flash-sale-auth')
        ->and($payload['sub'])->toBe('42')
        ->and($payload['type'])->toBe('access')
        ->and($issuedToken['expires_in'])->toBe(900);
});
