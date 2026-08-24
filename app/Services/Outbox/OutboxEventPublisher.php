<?php

namespace App\Services\Outbox;

use App\Enums\OutboxEventType;
use App\Events\AchievementsEvaluated;
use App\Events\AchievementUnlocked;
use App\Events\BadgeUnlocked;
use App\Events\CashbackCreated;
use App\Events\OrderCompleted;
use App\Models\Cashback;
use App\Models\Order;
use App\Models\OutboxMessage;
use App\Models\User;
use UnexpectedValueException;

class OutboxEventPublisher
{
    public function publish(OutboxMessage $message): void
    {
        match ($message->event_type) {
            OutboxEventType::ORDER_COMPLETED => $this->publishOrderCompleted($message),
            OutboxEventType::ACHIEVEMENT_UNLOCKED => $this->publishAchievementUnlocked($message),
            OutboxEventType::ACHIEVEMENTS_EVALUATED => $this->publishAchievementsEvaluated($message),
            OutboxEventType::BADGE_UNLOCKED => $this->publishBadgeUnlocked($message),
            OutboxEventType::CASHBACK_CREATED => $this->publishCashbackCreated($message),
        };
    }

    private function publishOrderCompleted(OutboxMessage $message): void
    {
        $orderId = (int) ($message->payload['order_id'] ?? 0);

        if ($orderId < 1) {
            throw new UnexpectedValueException('Order completed outbox payload is missing order_id.');
        }

        OrderCompleted::dispatch(Order::query()->findOrFail($orderId));
    }

    private function publishAchievementUnlocked(OutboxMessage $message): void
    {
        $achievementName = (string) ($message->payload['achievement_name'] ?? '');

        if ($achievementName === '') {
            throw new UnexpectedValueException('Achievement unlocked outbox payload is missing achievement_name.');
        }

        AchievementUnlocked::dispatch($achievementName, $this->user($message));
    }

    private function publishAchievementsEvaluated(OutboxMessage $message): void
    {
        AchievementsEvaluated::dispatch($this->user($message));
    }

    private function publishBadgeUnlocked(OutboxMessage $message): void
    {
        $badgeName = (string) ($message->payload['badge_name'] ?? '');

        if ($badgeName === '') {
            throw new UnexpectedValueException('Badge unlocked outbox payload is missing badge_name.');
        }

        BadgeUnlocked::dispatch($badgeName, $this->user($message));
    }

    private function publishCashbackCreated(OutboxMessage $message): void
    {
        $cashbackId = (int) ($message->payload['cashback_id'] ?? 0);

        if ($cashbackId < 1) {
            throw new UnexpectedValueException('Cashback created outbox payload is missing cashback_id.');
        }

        CashbackCreated::dispatch(Cashback::query()->findOrFail($cashbackId));
    }

    private function user(OutboxMessage $message): User
    {
        $userId = (int) ($message->payload['user_id'] ?? 0);

        if ($userId < 1) {
            throw new UnexpectedValueException('Outbox payload is missing user_id.');
        }

        return User::query()->findOrFail($userId);
    }
}
