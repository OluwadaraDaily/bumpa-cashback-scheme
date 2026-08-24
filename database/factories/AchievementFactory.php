<?php

namespace Database\Factories;

use App\Enums\AchievementMetric;
use App\Models\Achievement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Achievement>
 */
class AchievementFactory extends Factory
{
    protected $model = Achievement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->sentence(3),
            'description' => fake()->sentence(),
            'achievement_group' => 'purchases',
            'metric' => AchievementMetric::PURCHASE_COUNT,
            'threshold' => fake()->numberBetween(1, 10),
            'sort_order' => fake()->unique()->numberBetween(1, 100),
        ];
    }
}
