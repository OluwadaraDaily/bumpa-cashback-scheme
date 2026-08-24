<?php

namespace App\Services;

use App\Enums\AchievementMetric;
use App\Models\Achievement;
use App\Models\Order;
use App\Models\User;
use App\Services\Outbox\OutboxRecorder;
use App\Services\Outbox\OutboxRelay;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AchievementEvaluator
{
    public function __construct(
        private readonly OutboxRecorder $outbox,
        private readonly OutboxRelay $outboxRelay,
    ) {}

    public function evaluate(User $user, ?string $evaluationKey = null): void
    {
        $evaluationKey ??= 'manual:'.Str::uuid();

        $result = DB::transaction(function () use ($user, $evaluationKey): array {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $unlockedAchievements = [];
            $outboxMessages = [];
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
                $outboxMessages[] = $this->outbox->recordAchievementUnlocked($lockedUser, $achievement);
            }

            $outboxMessages[] = $this->outbox->recordAchievementsEvaluated(
                $lockedUser,
                $evaluationKey,
            );

            return [
                'unlocked_achievements' => $unlockedAchievements,
                'outbox_messages' => $outboxMessages,
            ];
        });

        foreach ($result['outbox_messages'] as $outboxMessage) {
            $this->outboxRelay->publishSafely($outboxMessage);
        }

        foreach ($result['unlocked_achievements'] as $achievement) {
            Log::info('Achievement unlocked', [
                'achievement_id' => $achievement['id'],
                'achievement_name' => $achievement['name'],
                'user_id' => $user->id,
            ]);
        }

        Log::info('Achievements evaluated', [
            'user_id' => $user->id,
            'unlocked_count' => count($result['unlocked_achievements']),
        ]);
    }
}
