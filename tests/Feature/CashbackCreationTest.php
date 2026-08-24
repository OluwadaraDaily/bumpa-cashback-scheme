<?php

namespace Tests\Feature;

use App\Enums\CashbackStatus;
use App\Events\CashbackCreated;
use App\Models\Badge;
use App\Models\PaymentAccount;
use App\Models\User;
use App\Services\CashbackCreator;
use Database\Seeders\AchievementSeeder;
use Database\Seeders\BadgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CashbackCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_badge_unlock_creates_a_pending_cashback_for_an_active_payment_account(): void
    {
        $this->seed([AchievementSeeder::class, BadgeSeeder::class]);
        Event::fake([CashbackCreated::class]);
        $user = User::factory()->create();
        $account = PaymentAccount::create([
            'user_id' => $user->id,
            'provider' => 'paystack',
            'recipient_reference' => 'RCP_test_recipient',
            'currency' => 'NGN',
            'status' => 'active',
        ]);

        $cashback = app(CashbackCreator::class)->createForBadge($user, 'Starter');

        $this->assertSame(30_000, $cashback->amount);
        $this->assertSame(CashbackStatus::PENDING, $cashback->status);
        $this->assertSame($account->id, $cashback->payment_account_id);
        $this->assertDatabaseHas('cashbacks', [
            'user_id' => $user->id,
            'badge_id' => Badge::query()->where('name', 'Starter')->value('id'),
            'amount' => 30_000,
            'currency' => 'NGN',
            'status' => 'pending',
        ]);
        Event::assertDispatched(CashbackCreated::class);
    }

    public function test_cashback_can_be_created_before_a_payment_account_exists(): void
    {
        $this->seed([AchievementSeeder::class, BadgeSeeder::class]);
        $user = User::factory()->create();

        $cashback = app(CashbackCreator::class)->createForBadge($user, 'Starter');

        $this->assertNull($cashback->payment_account_id);
        $this->assertSame(CashbackStatus::PENDING, $cashback->status);
    }

    public function test_existing_user_receives_the_default_account_when_cashback_is_created(): void
    {
        config()->set('services.paystack.default_recipient', 'RCP_shared_test_recipient');
        $this->seed([AchievementSeeder::class, BadgeSeeder::class]);
        Event::fake([CashbackCreated::class]);
        $user = User::factory()->create();

        $cashback = app(CashbackCreator::class)->createForBadge($user, 'Starter');
        $account = $user->paymentAccounts()->sole();

        $this->assertSame('RCP_shared_test_recipient', $account->recipient_reference);
        $this->assertTrue($account->metadata['is_default']);
        $this->assertSame($account->id, $cashback->payment_account_id);
    }

    public function test_the_same_badge_cannot_create_two_cashbacks_for_one_user(): void
    {
        $this->seed([AchievementSeeder::class, BadgeSeeder::class]);
        Event::fake([CashbackCreated::class]);
        $user = User::factory()->create();
        $creator = app(CashbackCreator::class);

        $first = $creator->createForBadge($user, 'Starter');
        $second = $creator->createForBadge($user, 'Starter');

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('cashbacks', 1);
        Event::assertDispatchedTimes(CashbackCreated::class, 1);
    }
}
