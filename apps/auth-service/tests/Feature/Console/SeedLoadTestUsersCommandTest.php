<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

it('seeds load test users and returns access tokens as json', function () {
    $exitCode = Artisan::call('auth:seed-load-test-users', [
        '--count' => 2,
        '--prefix' => 'perf-third',
        '--format' => 'json',
    ]);

    expect($exitCode)->toBe(0);

    $json = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

    expect($json['count'])->toBe(2)
        ->and($json['users'])->toHaveCount(2)
        ->and($json['users'][0]['id'])->toBeInt()
        ->and($json['users'][0]['email'])->toContain('perf-third+')
        ->and($json['users'][0]['access_token'])->toBeString()
        ->and($json['users'][0]['expires_in'])->toBeInt();

    expect(User::query()->count())->toBe(2);
});
