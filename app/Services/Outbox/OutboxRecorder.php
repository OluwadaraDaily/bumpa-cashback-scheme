<?php

namespace App\Services\Outbox;

use App\Enums\OutboxEventType;
use App\Models\Achievement;
use App\Models\Badge;
use App\Models\Cashback;
use App\Models\Order;
use App\Models\OutboxMessage;
use App\Models\User;

class OutboxRecorder
{
    public function recordOrderCompleted(Order $order): OutboxMessage
    {
        return $this->record(
            eventType: OutboxEventType::ORDER_COMPLETED,
            aggregateType: Order::class,
            aggregateId: $order->id,
            deduplicationKey: (string) $order->id,
            payload: ['order_id' => $order->id],
        );
    }

    public function recordAchievementUnlocked(
        User $user,
        Achievement $achievement,
    ): OutboxMessage {
        return $this->record(
            eventType: OutboxEventType::ACHIEVEMENT_UNLOCKED,
            aggregateType: User::class,
            aggregateId: $user->id,
            deduplicationKey: "{$user->id}:{$achievement->id}",
            payload: [
                'achievement_id' => $achievement->id,
                'achievement_name' => $achievement->name,
                'user_id' => $user->id,
            ],
        );
    }

    public function recordAchievementsEvaluated(
        User $user,
        string $evaluationKey,
    ): OutboxMessage {
        return $this->record(
            eventType: OutboxEventType::ACHIEVEMENTS_EVALUATED,
            aggregateType: User::class,
            aggregateId: $user->id,
            deduplicationKey: $evaluationKey,
            payload: ['user_id' => $user->id],
        );
    }

    public function recordBadgeUnlocked(User $user, Badge $badge): OutboxMessage
    {
        return $this->record(
            eventType: OutboxEventType::BADGE_UNLOCKED,
            aggregateType: User::class,
            aggregateId: $user->id,
            deduplicationKey: "{$user->id}:{$badge->id}",
            payload: [
                'badge_id' => $badge->id,
                'badge_name' => $badge->name,
                'user_id' => $user->id,
            ],
        );
    }

    public function recordCashbackCreated(Cashback $cashback): OutboxMessage
    {
        return $this->record(
            eventType: OutboxEventType::CASHBACK_CREATED,
            aggregateType: Cashback::class,
            aggregateId: $cashback->id,
            deduplicationKey: (string) $cashback->id,
            payload: ['cashback_id' => $cashback->id],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function record(
        OutboxEventType $eventType,
        string $aggregateType,
        int $aggregateId,
        string $deduplicationKey,
        array $payload,
    ): OutboxMessage {
        return OutboxMessage::query()->firstOrCreate(
            ['deduplication_key' => "{$eventType->value}:{$deduplicationKey}"],
            [
                'event_type' => $eventType,
                'aggregate_type' => $aggregateType,
                'aggregate_id' => $aggregateId,
                'payload' => $payload,
                'available_at' => now(),
            ],
        );
    }
}
