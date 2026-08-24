<?php

namespace Tests\Feature;

use App\Events\OrderCompleted;
use App\Models\Order;
use App\Models\OutboxMessage;
use App\Models\Product;
use App\Models\User;
use App\Services\Outbox\OutboxEventPublisher;
use App\Services\Outbox\OutboxRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class OrderOutboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_rolls_back_when_its_outbox_message_cannot_be_saved(): void
    {
        $this->mock(OutboxRecorder::class, function (MockInterface $mock): void {
            $mock->shouldReceive('recordOrderCompleted')
                ->once()
                ->andThrow(new RuntimeException('Outbox database write failed.'));
        });

        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 20_000, 'quantity' => 5]);
        $this->withoutExceptionHandling();

        try {
            $this->actingAs($user, 'sanctum')
                ->withHeader('Idempotency-Key', 'outbox-order-atomic')
                ->postJson('/orders', [
                    'items' => [['product_id' => $product->id, 'quantity' => 2]],
                ]);

            $this->fail('The outbox write exception was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Outbox database write failed.', $exception->getMessage());
        }

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('outbox_messages', 0);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'quantity' => 5]);
    }

    public function test_order_stays_committed_when_immediate_event_publishing_fails(): void
    {
        $this->mock(OutboxEventPublisher::class, function (MockInterface $mock): void {
            $mock->shouldReceive('publish')
                ->once()
                ->andThrow(new RuntimeException('Queue is unavailable.'));
        });

        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 20_000, 'quantity' => 5]);

        $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', 'outbox-order-001')
            ->postJson('/orders', [
                'items' => [['product_id' => $product->id, 'quantity' => 2]],
            ])
            ->assertCreated();

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'quantity' => 3]);

        $message = OutboxMessage::query()->sole();

        $this->assertNull($message->published_at);
        $this->assertNull($message->processing_at);
        $this->assertSame(1, $message->attempts);
        $this->assertSame('Queue is unavailable.', $message->last_error);
    }

    public function test_idempotent_order_replay_retries_an_unpublished_event(): void
    {
        $publishAttempts = 0;

        $this->mock(OutboxEventPublisher::class, function (MockInterface $mock) use (&$publishAttempts): void {
            $mock->shouldReceive('publish')
                ->twice()
                ->andReturnUsing(function () use (&$publishAttempts): void {
                    $publishAttempts++;

                    if ($publishAttempts === 1) {
                        throw new RuntimeException('Queue is unavailable.');
                    }
                });
        });

        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 20_000, 'quantity' => 5]);
        $payload = ['items' => [['product_id' => $product->id, 'quantity' => 2]]];

        $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', 'outbox-order-002')
            ->postJson('/orders', $payload)
            ->assertCreated();

        $this->withHeader('Idempotency-Key', 'outbox-order-002')
            ->postJson('/orders', $payload)
            ->assertOk();

        $message = OutboxMessage::query()->sole();

        $this->assertNotNull($message->published_at);
        $this->assertSame(2, $message->attempts);
        $this->assertNull($message->last_error);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'quantity' => 3]);
    }

    public function test_relay_command_publishes_a_pending_order_event_once(): void
    {
        Event::fake([OrderCompleted::class]);
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create();
        $message = app(OutboxRecorder::class)->recordOrderCompleted($order);

        $this->artisan('outbox:relay')
            ->expectsOutput('Published 1 outbox message(s); 0 failed.')
            ->assertSuccessful();
        $this->artisan('outbox:relay')
            ->expectsOutput('Published 0 outbox message(s); 0 failed.')
            ->assertSuccessful();

        Event::assertDispatchedTimes(OrderCompleted::class, 1);
        $this->assertNotNull($message->refresh()->published_at);
        $this->assertSame(1, $message->attempts);
    }
}
