<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpsQuery extends Model
{
    protected $fillable = [
        'ops_event_id',
        'sql',
        'duration_ms',
        'connection',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'duration_ms' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(OpsEvent::class, 'ops_event_id');
    }
}
