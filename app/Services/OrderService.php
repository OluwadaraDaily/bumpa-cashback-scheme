<?php

namespace App\Services;

use App\Exceptions\IdempotencyKeyConflict;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Outbox\OutboxRecorder;
use App\Services\Outbox\OutboxRelay;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class OrderService
{
    public function __construct(
        private readonly OutboxRecorder $outbox,
        private readonly OutboxRelay $outboxRelay,
    ) {}

    /**
     * @param  array<int, array{product_id: int, quantity: int}>  $items
     */
    public function create(User $user, array $items, string $idempotencyKey): OrderCreationResult
    {
        $normalizedItems = collect($items)
            ->map(fn (array $item): array => [
                'product_id' => (int) $item['product_id'],
                'quantity' => (int) $item['quantity'],
            ])
            ->sortBy('product_id')
            ->values()
            ->all();
        $requestHash = hash('sha256', json_encode($normalizedItems, JSON_THROW_ON_ERROR));

        $existing = $this->findExistingOrder($user, $idempotencyKey);

        if ($existing) {
            return $this->finish($this->replayOrConflict($existing, $requestHash));
        }

        try {
            $result = DB::transaction(function () use ($user, $normalizedItems, $idempotencyKey, $requestHash): OrderCreationResult {
                $existing = Order::query()
                    ->where('user_id', $user->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    return $this->replayOrConflict($existing->load('items'), $requestHash);
                }

                $products = Product::query()
                    ->whereIn('id', array_column($normalizedItems, 'product_id'))
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $orderItems = [];
                $total = 0;

                foreach ($normalizedItems as $item) {
                    $product = $products->get($item['product_id']);

                    if (! $product) {
                        throw ValidationException::withMessages([
                            'items' => ['One or more selected products no longer exist.'],
                        ]);
                    }

                    if ($item['quantity'] > $product->quantity) {
                        throw ValidationException::withMessages([
                            'items' => ["There is not enough stock for {$product->name}."],
                        ]);
                    }

                    $lineTotal = $product->price * $item['quantity'];
                    $total += $lineTotal;

                    $orderItems[] = [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'unit_price' => $product->price,
                        'quantity' => $item['quantity'],
                        'line_total' => $lineTotal,
                    ];

                    $product->decrement('quantity', $item['quantity']);
                }

                $order = Order::create([
                    'user_id' => $user->id,
                    'status' => Order::STATUS_COMPLETED,
                    'total' => $total,
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => $requestHash,
                ]);

                $order->items()->createMany($orderItems);
                $this->outbox->recordOrderCompleted($order);

                return new OrderCreationResult($order->load('items'), false);
            });
        } catch (QueryException $exception) {
            $existing = $this->findExistingOrder($user, $idempotencyKey);

            if (! $existing) {
                throw $exception;
            }

            $result = $this->replayOrConflict($existing, $requestHash);
        }

        return $this->finish($result);
    }

    private function finish(OrderCreationResult $result): OrderCreationResult
    {
        $outboxMessage = $this->outbox->recordOrderCompleted($result->order);

        try {
            $this->outboxRelay->publish($outboxMessage);
        } catch (Throwable $exception) {
            Log::warning('Immediate order event publish failed; outbox will retry', [
                'order_id' => $result->order->id,
                'outbox_message_id' => $outboxMessage->id,
                'exception' => $exception::class,
            ]);
        }

        if (! $result->replayed) {
            Log::info('Order completed', [
                'order_id' => $result->order->id,
                'user_id' => $result->order->user_id,
                'total' => $result->order->total,
                'item_count' => $result->order->items->count(),
            ]);
        } else {
            Log::info('Order request replayed', [
                'order_id' => $result->order->id,
                'user_id' => $result->order->user_id,
            ]);
        }

        return $result;
    }

    private function findExistingOrder(User $user, string $idempotencyKey): ?Order
    {
        return Order::query()
            ->where('user_id', $user->id)
            ->where('idempotency_key', $idempotencyKey)
            ->with('items')
            ->first();
    }

    private function replayOrConflict(Order $order, string $requestHash): OrderCreationResult
    {
        if (! hash_equals($order->request_hash, $requestHash)) {
            throw new IdempotencyKeyConflict;
        }

        return new OrderCreationResult($order->load('items'), true);
    }
}
