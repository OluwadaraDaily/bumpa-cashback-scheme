<?php

namespace App\Services\Outbox;

use App\Enums\OutboxEventType;
use App\Models\Order;
use App\Models\OutboxMessage;

class OutboxRecorder
{
    public function recordOrderCompleted(Order $order): OutboxMessage
    {
        return OutboxMessage::query()->firstOrCreate(
            ['deduplication_key' => $this->orderCompletedKey($order->id)],
            [
                'event_type' => OutboxEventType::ORDER_COMPLETED,
                'aggregate_type' => Order::class,
                'aggregate_id' => $order->id,
                'payload' => ['order_id' => $order->id],
                'available_at' => now(),
            ],
        );
    }

    public function orderCompletedKey(int $orderId): string
    {
        return OutboxEventType::ORDER_COMPLETED->value.":{$orderId}";
    }
}
