<?php

namespace App\Services;

use App\DataTransferObjects\ProfileData;
use App\Enums\ProfileStatus;
use App\Models\Profile;
use App\Models\ProfileSnapshot;
use Illuminate\Support\Facades\DB;

class ProfileSnapshotWriter
{
    public function write(Profile $profile, ProfileData $data): void
    {
        DB::transaction(function () use ($profile, $data) {
            $previousFollowers = $profile->followers_count;
            $delta = $previousFollowers === null
                ? null
                : $data->followersCount - $previousFollowers;

            ProfileSnapshot::query()->create([
                'profile_id' => $profile->id,
                'followers_count' => $data->followersCount,
                'following_count' => $data->followingCount,
                'posts_count' => $data->postsCount,
                'bio' => $data->bio,
                'profile_picture_url' => $data->profilePictureUrl,
                'followers_delta' => $delta,
                'captured_at' => now('UTC'),
            ]);

            $profile->update([
                'bio' => $data->bio,
                'profile_picture_url' => $data->profilePictureUrl,
                'followers_count' => $data->followersCount,
                'following_count' => $data->followingCount,
                'posts_count' => $data->postsCount,
                'status' => ProfileStatus::Fetched,
                'last_error' => null,
                'last_refreshed_at' => now('UTC'),
            ]);
        });
    }
}
