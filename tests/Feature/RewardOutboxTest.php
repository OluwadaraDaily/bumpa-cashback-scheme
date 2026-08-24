<?php

namespace Tests\Feature;

use App\Enums\OutboxEventType;
use App\Events\AchievementsEvaluated;
use App\Events\AchievementUnlocked;
use App\Events\BadgeUnlocked;
use App\Events\CashbackCreated;
use App\Models\Achievement;
use App\Models\Badge;
use App\Models\Cashback;
use App\Models\Order;
use App\Models\OutboxMessage;
use App\Models\PaymentAccount;
use App\Models\User;
use App\Services\AchievementEvaluator;
use App\Services\BadgeEvaluator;
use App\Services\CashbackCreator;
use App\Services\Outbox\OutboxEventPublisher;
use Database\Seeders\AchievementSeeder;
use Database\Seeders\BadgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class RewardOutboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_achievement_events_are_recovered_after_immediate_publishing_fails(): void
    {
        $this->seed(AchievementSeeder::class);
        $this->failingPublisher(2);
        $user = User::factory()->create();
        Order::factory()->for($user)->create(['total' => 10_000]);

        app(AchievementEvaluator::class)->evaluate($user, 'test-order:1');

        $this->assertDatabaseCount('user_achievements', 1);
        $this->assertDatabaseCount('outbox_messages', 2);
        $this->assertSame(2, OutboxMessage::query()->whereNull('published_at')->count());

        $this->restorePublisher();
        Event::fake([AchievementUnlocked::class, AchievementsEvaluated::class]);

        $this->artisan('outbox:relay')->assertSuccessful();

        Event::assertDispatched(AchievementUnlocked::class, function (AchievementUnlocked $event) use ($user): bool {
            return $event->achievement_name === 'First Purchase'
                && $event->user instanceof User
                && $event->user->is($user);
        });
        Event::assertDispatched(AchievementsEvaluated::class, function (AchievementsEvaluated $event) use ($user): bool {
            return $event->user instanceof User && $event->user->is($user);
        });
        $this->assertSame(0, OutboxMessage::query()->whereNull('published_at')->count());
    }

    public function test_badge_event_is_recovered_after_immediate_publishing_fails(): void
    {
        $this->seed([AchievementSeeder::class, BadgeSeeder::class]);
        $this->failingPublisher();
        $user = User::factory()->create();
        $user->userAchievements()->create([
            'achievement_id' => Achievement::query()->where('name', 'First Purchase')->value('id'),
            'unlocked_at' => now(),
        ]);

        app(BadgeEvaluator::class)->evaluate($user);

        $this->assertDatabaseHas('user_badges', [
            'user_id' => $user->id,
            'badge_id' => Badge::query()->where('name', 'Starter')->value('id'),
        ]);
        $message = $this->pendingMessage(OutboxEventType::BADGE_UNLOCKED);

        $this->restorePublisher();
        Event::fake([BadgeUnlocked::class]);

        $this->artisan('outbox:relay')->assertSuccessful();

        Event::assertDispatched(BadgeUnlocked::class, function (BadgeUnlocked $event) use ($user): bool {
            return $event->badge_name === 'Starter'
                && $event->user instanceof User
                && $event->user->is($user);
        });
        $this->assertNotNull($message->refresh()->published_at);
    }

    public function test_cashback_event_is_recovered_after_immediate_publishing_fails(): void
    {
        $this->seed([AchievementSeeder::class, BadgeSeeder::class]);
        $this->failingPublisher();
        $user = User::factory()->create();
        PaymentAccount::create([
            'user_id' => $user->id,
            'provider' => 'paystack',
            'recipient_reference' => 'RCP_reward_outbox_test',
            'currency' => 'NGN',
            'status' => 'active',
        ]);

        $cashback = app(CashbackCreator::class)->createForBadge($user, 'Starter');

        $this->assertDatabaseHas('cashbacks', [
            'id' => $cashback->id,
            'user_id' => $user->id,
            'amount' => 30_000,
            'status' => 'pending',
        ]);
        $message = $this->pendingMessage(OutboxEventType::CASHBACK_CREATED);

        $this->restorePublisher();
        Event::fake([CashbackCreated::class]);

        $this->artisan('outbox:relay')->assertSuccessful();

        Event::assertDispatched(CashbackCreated::class, function (CashbackCreated $event) use ($cashback): bool {
            return $event->cashback instanceof Cashback && $event->cashback->is($cashback);
        });
        $this->assertNotNull($message->refresh()->published_at);
    }

    private function failingPublisher(int $times = 1): void
    {
        $this->mock(OutboxEventPublisher::class, function (MockInterface $mock) use ($times): void {
            $mock->shouldReceive('publish')
                ->times($times)
                ->andThrow(new RuntimeException('Queue is unavailable.'));
        });
    }

    private function restorePublisher(): void
    {
        $this->app->forgetInstance(OutboxEventPublisher::class);
    }

    private function pendingMessage(OutboxEventType $eventType): OutboxMessage
    {
        return OutboxMessage::query()
            ->where('event_type', $eventType->value)
            ->whereNull('published_at')
            ->sole();
    }
}
