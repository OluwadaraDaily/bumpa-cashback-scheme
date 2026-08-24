<?php

namespace App\Services;

use App\Enums\AchievementMetric;
use App\Events\AchievementsEvaluated;
use App\Events\AchievementUnlocked;
use App\Models\Achievement;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AchievementEvaluator
{
    public function evaluate(User $user): void
    {
        $unlockedAchievements = DB::transaction(function () use ($user): array {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $unlockedAchievements = [];
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
                $unlockedAchievements[] = [
                    'id' => $achievement->id,
                    'name' => $achievement->name,
                ];

            }

            return $unlockedAchievements;
        });

        foreach ($unlockedAchievements as $achievement) {
            AchievementUnlocked::dispatch($achievement['name'], $user);

            Log::info('Achievement unlocked', [
                'achievement_id' => $achievement['id'],
                'achievement_name' => $achievement['name'],
                'user_id' => $user->id,
            ]);
        }

        AchievementsEvaluated::dispatch($user);
        Log::info('Achievements evaluated', [
            'user_id' => $user->id,
            'unlocked_count' => count($unlockedAchievements),
        ]);
    }
}
