<?php

namespace App\Console\Commands;

use App\Services\Events\LoadTestEventSeeder;
use Illuminate\Console\Command;
use InvalidArgumentException;

class SeedLoadTestEventCommand extends Command
{
    protected $signature = 'inventory:seed-load-test-event
        {--tickets=50 : Number of tickets to create}
        {--name= : Optional event name}
        {--sales-started-minutes-ago=5 : How many minutes ago ticket sales started}
        {--seat-prefix=LT : Seat number prefix}
        {--format=text : Output format: text|json}';

    protected $description = 'Seed a flash-sale event fixture for local load testing.';

    public function handle(LoadTestEventSeeder $loadTestEventSeeder): int
    {
        $ticketCount = (int) $this->option('tickets');
        $salesStartedMinutesAgo = (int) $this->option('sales-started-minutes-ago');
        $seatPrefix = (string) $this->option('seat-prefix');
        $format = (string) $this->option('format');

        if ($ticketCount < 1) {
            throw new InvalidArgumentException('The --tickets option must be at least 1.');
        }

        if ($salesStartedMinutesAgo < 0) {
            throw new InvalidArgumentException('The --sales-started-minutes-ago option must be 0 or greater.');
        }

        if (! in_array($format, ['text', 'json'], true)) {
            throw new InvalidArgumentException('The --format option must be either text or json.');
        }

        $name = (string) ($this->option('name') ?: 'Load Test Event '.now()->format('Y-m-d H:i:s'));

        $event = $loadTestEventSeeder->create(
            ticketCount: $ticketCount,
            name: $name,
            salesStartedMinutesAgo: $salesStartedMinutesAgo,
            seatPrefix: $seatPrefix,
        );

        $payload = [
            'event_id' => $event->id,
            'name' => $event->name,
            'total_tickets' => $event->total_tickets,
            'start_sales_at' => $event->start_sales_at?->toIso8601String(),
        ];

        if ($format === 'json') {
            $this->line(json_encode($payload, JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Seeded load test event %d ("%s") with %d tickets.',
            $event->id,
            $event->name,
            $event->total_tickets,
        ));

        return self::SUCCESS;
    }
}
