<?php

namespace Tests\Feature;

use App\Enums\CashbackStatus;
use App\Enums\PaymentAttemptStatus;
use App\Models\Badge;
use App\Models\Cashback;
use App\Models\PaymentAccount;
use App\Models\User;
use App\Services\CashbackTransferService;
use App\Services\Payments\FakePaymentProvider;
use Database\Seeders\AchievementSeeder;
use Database\Seeders\BadgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashbackTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_transfer_uses_the_provider_and_records_a_processing_attempt(): void
    {
        $this->seed([AchievementSeeder::class, BadgeSeeder::class]);
        $user = User::factory()->create();
        $account = $this->paymentAccount($user);
        $cashback = $this->cashback($user, $account);
        $provider = new FakePaymentProvider;

        app(CashbackTransferService::class, ['provider' => $provider])->process($cashback);

        $cashback->refresh();
        $attempt = $cashback->paymentAttempts()->first();

        $this->assertSame(CashbackStatus::PROCESSING, $cashback->status);
        $this->assertSame(PaymentAttemptStatus::PROCESSING, $attempt->status);
        $this->assertSame('fake', $attempt->provider);
        $this->assertSame($account->id, $attempt->payment_account_id);
        $this->assertNotNull($attempt->provider_transfer_reference);
        $this->assertCount(1, $provider->transfers());
    }

    public function test_transfer_waits_when_no_active_payment_account_exists(): void
    {
        $this->seed([AchievementSeeder::class, BadgeSeeder::class]);
        $user = User::factory()->create();
        $cashback = $this->cashback($user);
        $provider = new FakePaymentProvider;

        app(CashbackTransferService::class, ['provider' => $provider])->process($cashback);

        $this->assertSame(CashbackStatus::PENDING, $cashback->refresh()->status);
        $this->assertDatabaseCount('payment_attempts', 0);
        $this->assertCount(0, $provider->transfers());
    }

    public function test_failed_transfer_marks_the_attempt_and_cashback_as_failed(): void
    {
        $this->seed([AchievementSeeder::class, BadgeSeeder::class]);
        $user = User::factory()->create();
        $account = $this->paymentAccount($user);
        $cashback = $this->cashback($user, $account);
        $provider = new FakePaymentProvider;
        $provider->failWith('recipient_invalid', 'Recipient is invalid.');

        app(CashbackTransferService::class, ['provider' => $provider])->process($cashback);

        $cashback->refresh();
        $attempt = $cashback->paymentAttempts()->first();

        $this->assertSame(CashbackStatus::FAILED, $cashback->status);
        $this->assertSame(PaymentAttemptStatus::FAILED, $attempt->status);
        $this->assertSame('recipient_invalid', $attempt->failure_code);
    }

    private function paymentAccount(User $user): PaymentAccount
    {
        return PaymentAccount::create([
            'user_id' => $user->id,
            'provider' => 'fake',
            'recipient_reference' => 'RCP_test_recipient',
            'currency' => 'NGN',
            'status' => 'active',
        ]);
    }

    private function cashback(User $user, ?PaymentAccount $account = null): Cashback
    {
        return Cashback::create([
            'user_id' => $user->id,
            'badge_id' => Badge::query()->where('name', 'Starter')->value('id'),
            'payment_account_id' => $account?->id,
            'amount' => 30_000,
            'currency' => 'NGN',
            'status' => CashbackStatus::PENDING,
            'description' => 'Starter badge cashback',
        ]);
    }
}
