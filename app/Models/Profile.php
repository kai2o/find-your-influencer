<?php

namespace App\Models;

use App\Enums\ProfileStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Profile extends Model
{
    /** @use HasFactory<\Database\Factories\ProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'username',
        'platform',
        'status',
        'bio',
        'profile_picture_url',
        'followers_count',
        'following_count',
        'posts_count',
        'last_error',
        'last_refreshed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProfileStatus::class,
            'followers_count' => 'integer',
            'following_count' => 'integer',
            'posts_count' => 'integer',
            'last_refreshed_at' => 'datetime',
        ];
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(ProfileSnapshot::class);
    }

    public static function normalizeUsername(string $username): string
    {
        return strtolower(ltrim(trim($username), '@'));
    }
}
