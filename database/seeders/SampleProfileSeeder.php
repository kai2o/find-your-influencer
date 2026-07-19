<?php

namespace Database\Seeders;

use App\Enums\ProfileStatus;
use App\Models\Profile;
use Illuminate\Database\Seeder;

class SampleProfileSeeder extends Seeder
{
    public function run(): void
    {
        $samples = [
            'cristiano',
            'natgeo',
            'nasa',
        ];

        foreach ($samples as $username) {
            Profile::query()->updateOrCreate(
                ['username' => $username],
                [
                    'platform' => 'instagram',
                    'status' => ProfileStatus::Pending,
                    'bio' => null,
                    'profile_picture_url' => null,
                    'followers_count' => null,
                    'following_count' => null,
                    'posts_count' => null,
                    'last_refreshed_at' => null,
                    'last_error' => null,
                ]
            );
        }
    }
}
