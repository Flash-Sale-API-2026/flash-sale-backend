<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InboxMessage extends Model
{
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'event_id',
        'event_type',
        'payload',
        'status',
        'failure_reason',
        'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
