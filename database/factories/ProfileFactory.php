<?php

namespace Database\Factories;

use App\Enums\ProfileStatus;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Profile>
 */
class ProfileFactory extends Factory
{
    protected $model = Profile::class;

    public function definition(): array
    {
        return [
            'username' => strtolower($this->faker->unique()->userName()),
            'platform' => 'instagram',
            'status' => ProfileStatus::Fetched,
            'bio' => $this->faker->sentence(),
            'profile_picture_url' => $this->faker->imageUrl(),
            'followers_count' => $this->faker->numberBetween(100, 1_000_000),
            'following_count' => $this->faker->numberBetween(10, 5000),
            'posts_count' => $this->faker->numberBetween(1, 2000),
            'last_refreshed_at' => now('UTC')->subMinutes($this->faker->numberBetween(1, 500)),
        ];
    }
}
