<?php

namespace App\Infrastructure\Persistence\Models;

use App\Domain\Events\Enums\EventStatus;
use Illuminate\Database\Eloquent\Model;

final class EventHistoryRecord extends Model
{
    protected $table = 'event_history';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'event_id',
        'action',
        'source',
        'from_status',
        'to_status',
        'context',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => EventStatus::class,
            'to_status' => EventStatus::class,
            'context' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
