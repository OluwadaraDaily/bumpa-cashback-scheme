<?php

namespace App\Services\Outbox;

use App\Enums\OutboxEventType;
use App\Events\OrderCompleted;
use App\Models\Order;
use App\Models\OutboxMessage;
use UnexpectedValueException;

class OutboxEventPublisher
{
    public function publish(OutboxMessage $message): void
    {
        match ($message->event_type) {
            OutboxEventType::ORDER_COMPLETED => $this->publishOrderCompleted($message),
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
}
