<?php

namespace App\Actions\Orders;

use App\Models\InboxMessage;
use App\Models\Ticket;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class HandleOrderCreatedAction
{
    /**
     * @param  array{
     *     event_id: string,
     *     event_type: string,
     *     occurred_at: string,
     *     order: array{id: int, user_id: int, ticket_id: int, amount: string, status: string}
     * }  $payload
     */
    public function __invoke(array $payload): void
    {
        DB::transaction(function () use ($payload): void {
            $existingInboxMessage = InboxMessage::query()
                ->where('event_id', $payload['event_id'])
                ->lockForUpdate()
                ->first();

            if ($existingInboxMessage !== null) {
                return;
            }

            $ticket = Ticket::query()
                ->whereKey($payload['order']['ticket_id'])
                ->lockForUpdate()
                ->first();

            if ($ticket === null) {
                $this->recordRejectedMessage($payload, 'The ordered ticket does not exist in inventory.');

                return;
            }

            if (
                $ticket->status === Ticket::STATUS_SOLD
                && $ticket->user_id === $payload['order']['user_id']
            ) {
                $this->recordProcessedMessage($payload);

                return;
            }

            $occurredAt = CarbonImmutable::parse($payload['occurred_at']);

            if ($ticket->status !== Ticket::STATUS_RESERVED) {
                $this->recordRejectedMessage($payload, 'The ordered ticket is not currently reserved.');

                return;
            }

            if ($ticket->user_id !== $payload['order']['user_id']) {
                $this->recordRejectedMessage($payload, 'The ordered ticket is reserved for another user.');

                return;
            }

            if ($ticket->reserved_until === null || $ticket->reserved_until->lt($occurredAt)) {
                $this->recordRejectedMessage($payload, 'The reservation had already expired when the order was created.');

                return;
            }

            $ticket->forceFill([
                'status' => Ticket::STATUS_SOLD,
                'reserved_until' => null,
            ])->save();

            $this->recordProcessedMessage($payload);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordProcessedMessage(array $payload): void
    {
        InboxMessage::query()->create([
            'event_id' => (string) $payload['event_id'],
            'event_type' => (string) $payload['event_type'],
            'payload' => $payload,
            'status' => InboxMessage::STATUS_PROCESSED,
            'failure_reason' => null,
            'processed_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordRejectedMessage(array $payload, string $reason): void
    {
        InboxMessage::query()->create([
            'event_id' => (string) $payload['event_id'],
            'event_type' => (string) $payload['event_type'],
            'payload' => $payload,
            'status' => InboxMessage::STATUS_REJECTED,
            'failure_reason' => $reason,
            'processed_at' => now(),
        ]);
    }
}
