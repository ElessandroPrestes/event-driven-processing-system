<?php

namespace App\Infrastructure\Persistence\Models;

use App\Domain\Events\Enums\EventStatus;
use Illuminate\Database\Eloquent\Model;

final class EventRecord extends Model
{
    protected $table = 'events';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'trace_id',
        'event_name',
        'payload',
        'metadata',
        'status',
        'idempotency_key',
        'content_hash',
        'occurred_at',
        'queued_at',
        'consumed_at',
        'processed_at',
        'processing_attempts',
        'processing_result',
        'failure_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'metadata' => 'array',
            'status' => EventStatus::class,
            'occurred_at' => 'immutable_datetime',
            'queued_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
            'processed_at' => 'immutable_datetime',
            'processing_attempts' => 'integer',
            'processing_result' => 'array',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
