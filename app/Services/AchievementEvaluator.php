<?php

namespace App\Services;

use App\Enums\AchievementMetric;
use App\Events\AchievementUnlocked;
use App\Models\Achievement;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AchievementEvaluator
{
    public function evaluate(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $metrics = [
                AchievementMetric::PURCHASE_COUNT->value => Order::query()
                    ->where('user_id', $lockedUser->id)
                    ->where('status', Order::STATUS_COMPLETED)
                    ->count(),
                AchievementMetric::SPEND_TOTAL->value => (int) Order::query()
                    ->where('user_id', $lockedUser->id)
                    ->where('status', Order::STATUS_COMPLETED)
                    ->sum('total'),
            ];

            $unlockedAchievementIds = $lockedUser->userAchievements()
                ->pluck('achievement_id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $achievements = Achievement::query()
                ->orderBy('achievement_group')
                ->orderBy('sort_order')
                ->get();

            foreach ($achievements as $achievement) {
                if (in_array($achievement->id, $unlockedAchievementIds, true)) {
                    continue;
                }

                $metricValue = $metrics[$achievement->metric->value] ?? 0;

                if ($metricValue < $achievement->threshold) {
                    continue;
                }

                $lockedUser->userAchievements()->create([
                    'achievement_id' => $achievement->id,
                    'unlocked_at' => now(),
                ]);
                $unlockedAchievementIds[] = $achievement->id;

                AchievementUnlocked::dispatch($achievement->name, $lockedUser);
            }
        });
    }
}
