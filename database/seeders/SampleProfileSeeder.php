<?php

namespace Database\Seeders;

use App\Enums\ProfileStatus;
use App\Models\Profile;
use App\Models\ProfileSnapshot;
use Illuminate\Database\Seeder;

class SampleProfileSeeder extends Seeder
{
    public function run(): void
    {
        $samples = [
            ['username' => 'cristiano', 'followers_count' => 600_000_000],
            ['username' => 'natgeo', 'followers_count' => 280_000_000],
            ['username' => 'nasa', 'followers_count' => 90_000_000],
        ];

        foreach ($samples as $sample) {
            $profile = Profile::query()->updateOrCreate(
                ['username' => $sample['username']],
                [
                    'platform' => 'instagram',
                    'status' => ProfileStatus::Fetched,
                    'bio' => 'Sample seeded profile',
                    'followers_count' => $sample['followers_count'],
                    'following_count' => 100,
                    'posts_count' => 1000,
                    'last_refreshed_at' => now('UTC'),
                ]
            );

            if ($profile->snapshots()->count() === 0) {
                $followers = $sample['followers_count'];

                for ($i = 5; $i >= 0; $i--) {
                    $delta = $i === 5 ? null : random_int(-1000, 5000);
                    $followers = $i === 5 ? $followers : max(0, $followers + (int) $delta);

                    ProfileSnapshot::query()->create([
                        'profile_id' => $profile->id,
                        'followers_count' => $followers,
                        'following_count' => 100,
                        'posts_count' => 1000,
                        'bio' => 'Sample seeded profile',
                        'followers_delta' => $delta,
                        'captured_at' => now('UTC')->subDays($i),
                    ]);
                }
            }
        }
    }
}
