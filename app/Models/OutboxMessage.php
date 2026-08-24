<?php

namespace App\Models;

use App\Enums\OutboxEventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'event_type',
    'aggregate_type',
    'aggregate_id',
    'deduplication_key',
    'payload',
    'available_at',
    'processing_at',
    'processing_token',
    'published_at',
    'attempts',
    'last_error',
])]
class OutboxMessage extends Model
{
    protected function casts(): array
    {
        return [
            'event_type' => OutboxEventType::class,
            'aggregate_id' => 'integer',
            'payload' => 'array',
            'available_at' => 'datetime',
            'processing_at' => 'datetime',
            'published_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }
}
