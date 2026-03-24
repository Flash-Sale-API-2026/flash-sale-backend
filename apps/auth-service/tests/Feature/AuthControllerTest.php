<?php

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('registers a user and returns an auth session', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Demo User',
        'email' => 'demo@example.com',
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('user.email', 'demo@example.com')
        ->assertJsonStructure([
            'access_token',
            'refresh_token',
            'token_type',
            'expires_in',
            'user' => ['id', 'name', 'email'],
        ]);

    $this->assertDatabaseHas('users', [
        'email' => 'demo@example.com',
    ]);

    expect(RefreshToken::query()->count())->toBe(1);
});

it('logs in an existing user and returns an auth session', function () {
    $user = User::query()->create([
        'name' => 'Demo User',
        'email' => 'demo@example.com',
        'password' => 'Password123',
    ]);

    $response = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'Password123',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.email', $user->email);

    expect(RefreshToken::query()->count())->toBe(1);
});

it('rotates refresh tokens and returns a new session', function () {
    User::query()->create([
        'name' => 'Demo User',
        'email' => 'demo@example.com',
        'password' => 'Password123',
    ]);

    $loginResponse = $this->postJson('/api/login', [
        'email' => 'demo@example.com',
        'password' => 'Password123',
    ]);

    $oldRefreshToken = $loginResponse->json('refresh_token');

    $refreshResponse = $this->postJson('/api/refresh', [
        'refresh_token' => $oldRefreshToken,
    ]);

    $refreshResponse
        ->assertOk()
        ->assertJsonStructure([
            'access_token',
            'refresh_token',
            'token_type',
            'expires_in',
            'user' => ['id', 'name', 'email'],
        ]);

    expect($refreshResponse->json('refresh_token'))->not->toBe($oldRefreshToken);
    expect(RefreshToken::query()->whereNotNull('revoked_at')->count())->toBe(1);
    expect(RefreshToken::query()->count())->toBe(2);
});

it('rejects invalid credentials', function () {
    User::query()->create([
        'name' => 'Demo User',
        'email' => 'demo@example.com',
        'password' => 'Password123',
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'demo@example.com',
        'password' => 'WrongPassword123',
    ]);

    $response
        ->assertUnauthorized()
        ->assertJsonPath('message', 'The provided credentials are invalid.');
});
