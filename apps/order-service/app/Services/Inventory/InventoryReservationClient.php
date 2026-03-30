<?php

namespace App\Services\Inventory;

use Illuminate\Support\Facades\Http;
use Throwable;

class InventoryReservationClient
{
    /**
     * @return array{ticket_id: int, event_id: int, user_id: int, amount: string, reserved_until: string}
     */
    public function confirm(int $userId, int $ticketId): array
    {
        try {
            $response = Http::acceptJson()
                ->baseUrl($this->baseUrl())
                ->withHeaders([
                    $this->tokenHeader() => $this->token(),
                ])
                ->post("/tickets/{$ticketId}/reservation/confirm", [
                    'user_id' => $userId,
                ]);
        } catch (Throwable $exception) {
            throw new InventoryReservationConfirmationException('Inventory reservation confirmation is currently unavailable.', 0, $exception);
        }

        if (! $response->successful()) {
            throw new InventoryReservationConfirmationException(
                (string) ($response->json('message') ?? 'The ticket reservation could not be confirmed.')
            );
        }

        return [
            'ticket_id' => (int) $response->json('ticket_id'),
            'event_id' => (int) $response->json('event_id'),
            'user_id' => (int) $response->json('user_id'),
            'amount' => (string) $response->json('amount'),
            'reserved_until' => (string) $response->json('reserved_until'),
        ];
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.inventory.internal_base_url'), '/');
    }

    private function tokenHeader(): string
    {
        return (string) config('services.internal.token_header', 'X-Internal-Service-Token');
    }

    private function token(): string
    {
        return (string) config('services.internal.token');
    }
}
