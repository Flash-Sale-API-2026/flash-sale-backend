<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Auth\AccessTokenService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class SeedLoadTestUsersCommand extends Command
{
    protected $signature = 'auth:seed-load-test-users
        {--count=100 : Number of users to create}
        {--prefix=loadtest : Email/name prefix}
        {--password=Password123! : Shared password for seeded users}
        {--format=json : Output format: json|text}';

    protected $description = 'Seed authenticated users for local load testing and issue access tokens for them.';

    public function handle(AccessTokenService $accessTokenService): int
    {
        $count = (int) $this->option('count');
        $prefix = (string) $this->option('prefix');
        $password = (string) $this->option('password');
        $format = (string) $this->option('format');

        if ($count < 1) {
            throw new InvalidArgumentException('The --count option must be at least 1.');
        }

        if (! in_array($format, ['json', 'text'], true)) {
            throw new InvalidArgumentException('The --format option must be either json or text.');
        }

        $nonce = now()->format('YmdHisv');
        $now = now();
        $passwordHash = Hash::make($password);
        $rows = [];

        for ($index = 0; $index < $count; $index++) {
            $rows[] = [
                'name' => sprintf('%s user %d', $prefix, $index),
                'email' => sprintf('%s+%s-%d@example.com', $prefix, $nonce, $index),
                'password' => $passwordHash,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($rows): void {
            User::query()->insert($rows);
        });

        $users = User::query()
            ->where('email', 'like', sprintf('%s+%s-%%@example.com', $prefix, $nonce))
            ->orderBy('id')
            ->get();

        $payload = [
            'count' => $users->count(),
            'users' => $users->map(function (User $user) use ($accessTokenService): array {
                $issuedAccessToken = $accessTokenService->issue($user);

                return [
                    'id' => $user->id,
                    'email' => $user->email,
                    'access_token' => $issuedAccessToken['token'],
                    'expires_in' => $issuedAccessToken['expires_in'],
                ];
            })->all(),
        ];

        if ($format === 'json') {
            $this->line(json_encode($payload, JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $this->info(sprintf('Seeded %d load test users with access tokens.', $users->count()));

        return self::SUCCESS;
    }
}
