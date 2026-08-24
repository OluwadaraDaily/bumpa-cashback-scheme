<?php

namespace Tests\Feature;

use App\Contracts\Payments\PaymentProvider;
use App\Data\Payments\PaymentTransferRequest;
use App\Data\Payments\PaymentTransferResult;
use App\Enums\CashbackStatus;
use App\Enums\OutboxEventType;
use App\Enums\PaymentAttemptStatus;
use App\Models\Cashback;
use App\Models\OutboxMessage;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\AchievementSeeder;
use Database\Seeders\BadgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class RewardJourneyTest extends TestCase
{
    use RefreshDatabase;

    private const PAYSTACK_SECRET = 'reward-journey-paystack-secret';

    public function test_first_order_completes_the_reward_and_cashback_journey(): void
    {
        config()->set('services.paystack.default_recipient', 'RCP_full_journey_test');
        config()->set('services.paystack.secret_key', self::PAYSTACK_SECRET);
        $this->mock(PaymentProvider::class, function (MockInterface $mock): void {
            $mock->shouldReceive('name')->andReturn('paystack');
            $mock->shouldReceive('transfer')
                ->once()
                ->withArgs(fn (PaymentTransferRequest $request): bool => $request->amount === 30_000
                    && $request->currency === 'NGN'
                    && $request->recipientReference === 'RCP_full_journey_test')
                ->andReturn(PaymentTransferResult::success(
                    'TRF_full_journey_test',
                    ['status' => true],
                ));
        });
        $this->seed([AchievementSeeder::class, BadgeSeeder::class]);
        $product = Product::factory()->create([
            'name' => 'Backend Engineering Handbook',
            'price' => 250_000,
            'quantity' => 10,
        ]);

        $signup = $this->postJson('/signup', [
            'username' => 'reward-journey-user',
            'email' => 'journey@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $this->withToken($signup->json('token'))
            ->withHeader('Idempotency-Key', 'reward-journey-order-001')
            ->postJson('/orders', [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.total', 250_000);

        $user = User::query()->where('email', 'journey@example.com')->firstOrFail();
        $cashback = Cashback::query()->where('user_id', $user->id)->sole();
        $attempt = PaymentAttempt::query()->where('cashback_id', $cashback->id)->sole();

        $this->assertSame(['First Purchase'], $user->achievements()->pluck('name')->all());
        $this->assertSame(['Starter'], $user->badges()->pluck('name')->all());
        $this->assertSame(30_000, $cashback->amount);
        $this->assertSame(CashbackStatus::PROCESSING, $cashback->status);
        $this->assertSame(PaymentAttemptStatus::PROCESSING, $attempt->status);
        $this->assertSame('paystack', $attempt->provider);
        $this->assertSame(30_000, $attempt->amount);
        $this->assertSame('TRF_full_journey_test', $attempt->provider_transfer_reference);
        $this->assertSame('RCP_full_journey_test', $user->paymentAccounts()->sole()->recipient_reference);
        $this->assertDatabaseCount('notifications', 1);

        $webhookPayload = [
            'event' => 'transfer.success',
            'data' => [
                'amount' => $attempt->amount,
                'currency' => $attempt->currency,
                'reference' => $attempt->request_payload['reference'],
                'transfer_code' => 'TRF_full_journey_test',
                'status' => 'success',
            ],
        ];
        $webhookBody = json_encode($webhookPayload, JSON_THROW_ON_ERROR);

        $this->call(
            'POST',
            '/cashback/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $webhookBody, self::PAYSTACK_SECRET),
            ],
            $webhookBody,
        )->assertOk();

        $this->assertSame(CashbackStatus::PAID, $cashback->refresh()->status);
        $this->assertNotNull($cashback->paid_at);
        $this->assertSame(PaymentAttemptStatus::SUCCEEDED, $attempt->refresh()->status);
        $this->assertNotNull($attempt->completed_at);

        $eventTypes = OutboxMessage::query()
            ->orderBy('id')
            ->pluck('event_type')
            ->map(fn (OutboxEventType $eventType): string => $eventType->value)
            ->all();

        $this->assertSame([
            OutboxEventType::ORDER_COMPLETED->value,
            OutboxEventType::ACHIEVEMENT_UNLOCKED->value,
            OutboxEventType::ACHIEVEMENTS_EVALUATED->value,
            OutboxEventType::BADGE_UNLOCKED->value,
            OutboxEventType::CASHBACK_CREATED->value,
        ], $eventTypes);
        $this->assertSame(0, OutboxMessage::query()->whereNull('published_at')->count());
    }
}
