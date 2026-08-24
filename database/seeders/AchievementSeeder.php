<?php

namespace Database\Seeders;

use App\Enums\AchievementMetric;
use App\Models\Achievement;
use Illuminate\Database\Seeder;

class AchievementSeeder extends Seeder
{
    public function run(): void
    {
        $achievements = [
            [
                'name' => 'First Purchase',
                'description' => 'Complete your first purchase.',
                'achievement_group' => 'purchases',
                'metric' => AchievementMetric::PURCHASE_COUNT,
                'threshold' => 1,
                'sort_order' => 1,
            ],
            [
                'name' => '5 Purchases',
                'description' => 'Complete five purchases.',
                'achievement_group' => 'purchases',
                'metric' => AchievementMetric::PURCHASE_COUNT,
                'threshold' => 5,
                'sort_order' => 2,
            ],
            [
                'name' => '₦10,000 Spent',
                'description' => 'Spend ₦10,000 across completed purchases.',
                'achievement_group' => 'spend',
                'metric' => AchievementMetric::SPEND_TOTAL,
                'threshold' => 1_000_000,
                'sort_order' => 1,
            ],
        ];

        foreach ($achievements as $achievement) {
            Achievement::query()->updateOrCreate(
                ['name' => $achievement['name']],
                $achievement,
            );
        }
    }
}
