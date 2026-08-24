<?php

namespace Tests\Feature;

use App\Enums\CashbackStatus;
use App\Enums\OutboxEventType;
use App\Events\AchievementsEvaluated;
use App\Events\AchievementUnlocked;
use App\Events\BadgeUnlocked;
use App\Events\CashbackCreated;
use App\Events\OrderCompleted;
use App\Listeners\CreateCashback;
use App\Listeners\EvaluateAchievements;
use App\Listeners\EvaluateBadges;
use App\Listeners\StoreAchievementNotification;
use App\Listeners\TransferCashback;
use App\Models\Achievement;
use App\Models\Badge;
use App\Models\Cashback;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\User;
use App\Services\CashbackTransferService;
use App\Services\Payments\FakePaymentProvider;
use Database\Seeders\AchievementSeeder;
use Database\Seeders\BadgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class QueuedListenerIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([AchievementSeeder::class, BadgeSeeder::class]);
    }

    public function test_retried_order_job_does_not_duplicate_achievements_or_outbox_events(): void
    {
        Event::fake();
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create(['total' => 10_000]);
        $listener = app(EvaluateAchievements::class);
        $event = new OrderCompleted($order);

        $listener->handle($event);
        $listener->handle($event);

        $this->assertDatabaseCount('user_achievements', 1);
        $this->assertDatabaseCount('outbox_messages', 2);
        $this->assertDatabaseHas('outbox_messages', [
            'event_type' => OutboxEventType::ACHIEVEMENT_UNLOCKED->value,
        ]);
        $this->assertDatabaseHas('outbox_messages', [
            'event_type' => OutboxEventType::ACHIEVEMENTS_EVALUATED->value,
        ]);
    }

    public function test_retried_achievement_notification_job_stores_one_notification(): void
    {
        $user = User::factory()->create();
        $listener = app(StoreAchievementNotification::class);
        $event = new AchievementUnlocked('First Purchase', $user);

        $listener->handle($event);
        $listener->handle($event);

        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_retried_badge_job_does_not_duplicate_badges_or_outbox_events(): void
    {
        Event::fake();
        $user = User::factory()->create();
        $user->userAchievements()->create([
            'achievement_id' => Achievement::query()->where('name', 'First Purchase')->value('id'),
            'unlocked_at' => now(),
        ]);
        $listener = app(EvaluateBadges::class);
        $event = new AchievementsEvaluated($user);

        $listener->handle($event);
        $listener->handle($event);

        $this->assertDatabaseCount('user_badges', 1);
        $this->assertDatabaseCount('outbox_messages', 1);
        $this->assertDatabaseHas('outbox_messages', [
            'event_type' => OutboxEventType::BADGE_UNLOCKED->value,
        ]);
    }

    public function test_retried_cashback_job_does_not_create_another_cashback(): void
    {
        Event::fake();
        $user = User::factory()->create();
        $this->paymentAccount($user);
        $listener = app(CreateCashback::class);
        $event = new BadgeUnlocked('Starter', $user);

        $listener->handle($event);
        $listener->handle($event);

        $this->assertDatabaseCount('cashbacks', 1);
        $this->assertDatabaseCount('outbox_messages', 1);
        $this->assertDatabaseHas('outbox_messages', [
            'event_type' => OutboxEventType::CASHBACK_CREATED->value,
        ]);
    }

    public function test_retried_transfer_job_reuses_the_attempt_and_provider_reference(): void
    {
        $user = User::factory()->create();
        $account = $this->paymentAccount($user, 'fake');
        $cashback = $this->cashback($user, $account);
        $provider = new FakePaymentProvider;
        $listener = new TransferCashback(new CashbackTransferService($provider));
        $event = new CashbackCreated($cashback);

        $listener->handle($event);
        $listener->handle($event);

        $requests = $provider->transfers();

        $this->assertDatabaseCount('payment_attempts', 1);
        $this->assertCount(2, $requests);
        $this->assertSame($requests[0]->reference, $requests[1]->reference);
        $this->assertSame(
            $cashback->paymentAttempts()->sole()->request_payload['reference'],
            $requests[0]->reference,
        );
    }

    private function paymentAccount(User $user, string $provider = 'paystack'): PaymentAccount
    {
        return PaymentAccount::create([
            'user_id' => $user->id,
            'provider' => $provider,
            'recipient_reference' => 'RCP_listener_retry',
            'currency' => 'NGN',
            'status' => 'active',
        ]);
    }

    private function cashback(User $user, PaymentAccount $account): Cashback
    {
        return Cashback::create([
            'user_id' => $user->id,
            'badge_id' => Badge::query()->where('name', 'Starter')->value('id'),
            'payment_account_id' => $account->id,
            'amount' => 30_000,
            'currency' => 'NGN',
            'status' => CashbackStatus::PENDING,
            'description' => 'Starter badge cashback',
        ]);
    }
}
