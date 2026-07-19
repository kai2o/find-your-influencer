<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\ProfileSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfileSnapshot>
 */
class ProfileSnapshotFactory extends Factory
{
    protected $model = ProfileSnapshot::class;

    public function definition(): array
    {
        $followers = $this->faker->numberBetween(100, 1_000_000);

        return [
            'profile_id' => Profile::factory(),
            'followers_count' => $followers,
            'following_count' => $this->faker->numberBetween(10, 5000),
            'posts_count' => $this->faker->numberBetween(1, 2000),
            'bio' => $this->faker->sentence(),
            'profile_picture_url' => $this->faker->imageUrl(),
            'followers_delta' => $this->faker->numberBetween(-5000, 5000),
            'captured_at' => now('UTC')->subDays($this->faker->numberBetween(0, 30)),
        ];
    }
}
