<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\User;
use App\Services\Outbox\OutboxRecorder;
use App\Services\Outbox\OutboxRelay;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BadgeEvaluator
{
    public function __construct(
        private readonly OutboxRecorder $outbox,
        private readonly OutboxRelay $outboxRelay,
    ) {}

    public function evaluate(User $user): void
    {
        $result = DB::transaction(function () use ($user): array {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $unlockedBadges = [];
            $outboxMessages = [];
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
                $outboxMessages[] = $this->outbox->recordBadgeUnlocked($lockedUser, $badge);
            }

            return [
                'unlocked_badges' => $unlockedBadges,
                'outbox_messages' => $outboxMessages,
            ];
        });

        foreach ($result['outbox_messages'] as $outboxMessage) {
            $this->outboxRelay->publishSafely($outboxMessage);
        }

        foreach ($result['unlocked_badges'] as $badge) {
            Log::info('Badge unlocked', [
                'badge_id' => $badge['id'],
                'badge_name' => $badge['name'],
                'user_id' => $user->id,
            ]);
        }
    }
}
