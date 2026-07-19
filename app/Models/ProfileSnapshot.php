<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileSnapshot extends Model
{
    /** @use HasFactory<\Database\Factories\ProfileSnapshotFactory> */
    use HasFactory;

    protected $fillable = [
        'profile_id',
        'followers_count',
        'following_count',
        'posts_count',
        'bio',
        'profile_picture_url',
        'followers_delta',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'followers_count' => 'integer',
            'following_count' => 'integer',
            'posts_count' => 'integer',
            'followers_delta' => 'integer',
            'captured_at' => 'datetime',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}
