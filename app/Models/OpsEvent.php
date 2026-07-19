<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OpsEvent extends Model
{
    protected $fillable = [
        'type',
        'name',
        'status',
        'duration_ms',
        'profile_id',
        'correlation_id',
        'meta',
        'query_count',
        'query_ms_total',
        'tokens_consumed',
        'tokens_remaining',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'duration_ms' => 'integer',
            'query_count' => 'integer',
            'query_ms_total' => 'integer',
            'tokens_consumed' => 'integer',
            'tokens_remaining' => 'float',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function queries(): HasMany
    {
        return $this->hasMany(OpsQuery::class);
    }
}
