<?php

namespace App\Services;

use App\Events\BadgeUnlocked;
use App\Models\Badge;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BadgeEvaluator
{
    public function evaluate(User $user): void
    {
        $unlockedBadges = DB::transaction(function () use ($user): array {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $unlockedBadges = [];
            $unlockedAchievementIds = $lockedUser->userAchievements()
                ->pluck('achievement_id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            $unlockedBadgeIds = $lockedUser->userBadges()
                ->pluck('badge_id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $badges = Badge::query()
                ->with('achievements')
                ->orderBy('sort_order')
                ->get();

            foreach ($badges as $badge) {
                if (in_array($badge->id, $unlockedBadgeIds, true)) {
                    continue;
                }

                $requiredAchievementIds = $badge->achievements
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->all();

                if (array_diff($requiredAchievementIds, $unlockedAchievementIds) !== []) {
                    continue;
                }

                $lockedUser->userBadges()->create([
                    'badge_id' => $badge->id,
                    'unlocked_at' => now(),
                ]);
                $unlockedBadgeIds[] = $badge->id;
                $unlockedBadges[] = [
                    'id' => $badge->id,
                    'name' => $badge->name,
                ];

                BadgeUnlocked::dispatch($badge->name, $lockedUser);
            }

            return $unlockedBadges;
        });

        foreach ($unlockedBadges as $badge) {
            Log::info('Badge unlocked', [
                'badge_id' => $badge['id'],
                'badge_name' => $badge['name'],
                'user_id' => $user->id,
            ]);
        }
    }
}
