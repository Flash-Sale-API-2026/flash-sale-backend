<?php

namespace App\Actions\Checkout;

use App\Models\Ticket;
use Symfony\Component\HttpFoundation\Response;

class CheckoutResult
{
    private const STATUS_RESERVED = 'reserved';
    private const STATUS_ALREADY_RESERVED = 'already_reserved';
    private const STATUS_SALES_NOT_STARTED = 'sales_not_started';
    private const STATUS_ALREADY_IN_PROGRESS = 'already_in_progress';
    private const STATUS_SOLD_OUT = 'sold_out';

    private function __construct(
        public readonly string $status,
        public readonly ?Ticket $ticket = null,
    ) {
    }

    public static function reserved(Ticket $ticket): self
    {
        return new self(self::STATUS_RESERVED, $ticket);
    }

    public static function alreadyReserved(Ticket $ticket): self
    {
        return new self(self::STATUS_ALREADY_RESERVED, $ticket);
    }

    public static function salesNotStarted(): self
    {
        return new self(self::STATUS_SALES_NOT_STARTED);
    }

    public static function alreadyInProgress(): self
    {
        return new self(self::STATUS_ALREADY_IN_PROGRESS);
    }

    public static function soldOut(): self
    {
        return new self(self::STATUS_SOLD_OUT);
    }

    public function successful(): bool
    {
        return in_array($this->status, [
            self::STATUS_RESERVED,
            self::STATUS_ALREADY_RESERVED,
        ], true);
    }

    public function created(): bool
    {
        return $this->status === self::STATUS_RESERVED;
    }

    public function httpStatus(): int
    {
        return match ($this->status) {
            self::STATUS_RESERVED => Response::HTTP_CREATED,
            self::STATUS_ALREADY_RESERVED => Response::HTTP_OK,
            default => Response::HTTP_CONFLICT,
        };
    }

    public function message(): string
    {
        return match ($this->status) {
            self::STATUS_SALES_NOT_STARTED => 'Ticket sales have not started yet.',
            self::STATUS_ALREADY_IN_PROGRESS => 'A checkout attempt is already in progress for this user.',
            self::STATUS_SOLD_OUT => 'Tickets are sold out for this event.',
            default => '',
        };
    }
}
