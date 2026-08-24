<?php

namespace Tests\Feature;

use App\Events\OrderCompleted;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_multi_product_order(): void
    {
        Event::fake();

        $user = User::factory()->create();
        $coffee = Product::factory()->create([
            'name' => 'Coffee beans',
            'price' => 25_000,
            'quantity' => 10,
        ]);
        $tea = Product::factory()->create([
            'name' => 'Green tea',
            'price' => 15_000,
            'quantity' => 5,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', 'order-001')
            ->postJson('/orders', [
                'items' => [
                    ['product_id' => $coffee->id, 'quantity' => 2],
                    ['product_id' => $tea->id, 'quantity' => 1],
                ],
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.total', 65_000)
            ->assertJsonCount(2, 'data.items');

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'total' => 65_000,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('order_items', [
            'product_id' => $coffee->id,
            'product_name' => 'Coffee beans',
            'unit_price' => 25_000,
            'quantity' => 2,
            'line_total' => 50_000,
        ]);
        $this->assertDatabaseHas('products', ['id' => $coffee->id, 'quantity' => 8]);
        $this->assertDatabaseHas('products', ['id' => $tea->id, 'quantity' => 4]);
        Event::assertDispatched(OrderCompleted::class);
    }

    public function test_order_requires_an_idempotency_key(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['quantity' => 2]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/orders', [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['idempotency_key']);
    }

    public function test_repeating_an_order_with_the_same_key_does_not_duplicate_it(): void
    {
        Event::fake();

        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 20_000, 'quantity' => 5]);
        $payload = ['items' => [['product_id' => $product->id, 'quantity' => 2]]];

        $first = $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', 'order-002')
            ->postJson('/orders', $payload)
            ->assertCreated();
        $second = $this->withHeader('Idempotency-Key', 'order-002')
            ->postJson('/orders', $payload)
            ->assertOk();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('outbox_messages', 1);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'quantity' => 3]);
        Event::assertDispatchedTimes(OrderCompleted::class, 1);
    }

    public function test_reusing_an_idempotency_key_with_different_items_is_rejected(): void
    {
        $user = User::factory()->create();
        $firstProduct = Product::factory()->create(['quantity' => 5]);
        $secondProduct = Product::factory()->create(['quantity' => 5]);

        $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', 'order-003')
            ->postJson('/orders', [
                'items' => [['product_id' => $firstProduct->id, 'quantity' => 1]],
            ])
            ->assertCreated();

        $this->withHeader('Idempotency-Key', 'order-003')
            ->postJson('/orders', [
                'items' => [['product_id' => $secondProduct->id, 'quantity' => 1]],
            ])
            ->assertConflict();

        $this->assertDatabaseCount('orders', 1);
    }

    public function test_order_fails_without_enough_stock_and_does_not_reduce_stock(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['quantity' => 1]);

        $this->actingAs($user, 'sanctum')
            ->withHeader('Idempotency-Key', 'order-004')
            ->postJson('/orders', [
                'items' => [['product_id' => $product->id, 'quantity' => 2]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'quantity' => 1]);
    }

    public function test_user_can_only_view_their_own_orders(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $ownOrder = $user->orders()->create([
            'status' => 'completed',
            'total' => 10_000,
            'idempotency_key' => 'order-005',
            'request_hash' => hash('sha256', 'order-005'),
        ]);
        $otherOrder = $otherUser->orders()->create([
            'status' => 'completed',
            'total' => 10_000,
            'idempotency_key' => 'order-006',
            'request_hash' => hash('sha256', 'order-006'),
        ]);

        $this->actingAs($user, 'sanctum')->getJson('/orders')
            ->assertOk()
            ->assertJsonPath('data.0.id', $ownOrder->id)
            ->assertJsonCount(1, 'data');

        $this->getJson("/orders/{$otherOrder->id}")->assertNotFound();
        $this->actingAs($user, 'sanctum')->getJson("/orders/{$otherOrder->id}")->assertNotFound();
    }
}
