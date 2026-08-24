<?php

namespace App\Services;

use App\Events\BadgeUnlocked;
use App\Models\Badge;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BadgeEvaluator
{
    public function evaluate(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
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

                BadgeUnlocked::dispatch($badge->name, $lockedUser);
            }
        });
    }
}
