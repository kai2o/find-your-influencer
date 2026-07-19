<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BenchmarkSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Seeding 1000 profiles + 10000 snapshots (bulk)...');

        $now = now('UTC');
        $profiles = [];

        for ($i = 1; $i <= 1000; $i++) {
            $profiles[] = [
                'username' => 'bench_user_'.$i.'_'.Str::lower(Str::random(4)),
                'platform' => 'instagram',
                'status' => $i % 5 === 0 ? 'pending' : 'fetched',
                'bio' => 'Benchmark profile '.$i,
                'followers_count' => 1000 + $i,
                'following_count' => 100,
                'posts_count' => 50,
                'last_refreshed_at' => $now->copy()->subMinutes($i),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($profiles, 200) as $chunk) {
            DB::table('profiles')->insert($chunk);
        }

        $ids = DB::table('profiles')
            ->where('username', 'like', 'bench_user_%')
            ->orderBy('id')
            ->pluck('id');

        $snapshots = [];
        foreach ($ids as $profileId) {
            for ($s = 0; $s < 10; $s++) {
                $snapshots[] = [
                    'profile_id' => $profileId,
                    'followers_count' => 1000 + (int) $profileId + $s,
                    'following_count' => 100,
                    'posts_count' => 50,
                    'bio' => 'snap',
                    'followers_delta' => $s === 0 ? null : random_int(-50, 50),
                    'captured_at' => $now->copy()->subDays($s),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($snapshots) >= 500) {
                    DB::table('profile_snapshots')->insert($snapshots);
                    $snapshots = [];
                }
            }
        }

        if ($snapshots !== []) {
            DB::table('profile_snapshots')->insert($snapshots);
        }

        $this->command?->info(
            'Done. profiles='.DB::table('profiles')->count()
            .' snapshots='.DB::table('profile_snapshots')->count()
        );
    }
}
