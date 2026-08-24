<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\Badge;
use App\Models\User;

class AchievementStatusService
{
    /**
     * @return array<string, mixed>
     */
    public function getFor(User $user): array
    {
        $unlockedAchievements = $user->achievements()
            ->orderBy('achievement_group')
            ->orderBy('sort_order')
            ->get();
        $unlockedAchievementIds = $unlockedAchievements->pluck('id')->map(fn ($id): int => (int) $id);
        $nextAchievements = Achievement::query()
            ->orderBy('achievement_group')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('achievement_group')
            ->map(fn ($achievements) => $achievements->first(
                fn (Achievement $achievement): bool => ! $unlockedAchievementIds->contains($achievement->id)
            ))
            ->filter()
            ->values();

        $badges = Badge::query()
            ->with('achievements')
            ->orderBy('sort_order')
            ->get();
        $unlockedBadges = $user->badges()
            ->orderBy('sort_order')
            ->get();
        $unlockedBadgeIds = $unlockedBadges->pluck('id')->map(fn ($id): int => (int) $id);
        $nextBadge = $badges->first(
            fn (Badge $badge): bool => ! $unlockedBadgeIds->contains($badge->id)
        );

        return [
            'unlocked_achievements' => $unlockedAchievements->pluck('name')->values()->all(),
            'next_available_achievements' => $nextAchievements->pluck('name')->values()->all(),
            'current_badge' => $unlockedBadges->last()?->name,
            'next_badge' => $nextBadge?->name,
            'remaining_to_unlock_next_badge' => $nextBadge
                ? $nextBadge->achievements->whereNotIn('id', $unlockedAchievementIds)->count()
                : 0,
        ];
    }
}
