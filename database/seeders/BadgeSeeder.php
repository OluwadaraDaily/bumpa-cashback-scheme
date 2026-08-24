<?php

namespace Database\Seeders;

use App\Models\Achievement;
use App\Models\Badge;
use Illuminate\Database\Seeder;

class BadgeSeeder extends Seeder
{
    public function run(): void
    {
        $badges = [
            [
                'name' => 'Starter',
                'description' => 'Complete your first purchase.',
                'sort_order' => 1,
                'achievements' => ['First Purchase'],
            ],
            [
                'name' => 'Loyal',
                'description' => 'Complete five purchases.',
                'sort_order' => 2,
                'achievements' => ['First Purchase', '5 Purchases'],
            ],
            [
                'name' => 'Premium',
                'description' => 'Complete five purchases and spend ₦10,000.',
                'sort_order' => 3,
                'achievements' => ['5 Purchases', '₦10,000 Spent'],
            ],
        ];

        foreach ($badges as $badgeData) {
            $achievementNames = $badgeData['achievements'];
            unset($badgeData['achievements']);

            $badge = Badge::query()->updateOrCreate(
                ['name' => $badgeData['name']],
                $badgeData,
            );
            $achievementIds = Achievement::query()
                ->whereIn('name', $achievementNames)
                ->orderBy('id')
                ->pluck('id')
                ->all();

            $badge->achievements()->sync($achievementIds);
        }
    }
}
