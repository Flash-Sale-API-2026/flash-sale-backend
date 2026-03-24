<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutboxMessage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'aggregate_type',
        'aggregate_id',
        'type',
        'payload',
        'created_at',
        'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
