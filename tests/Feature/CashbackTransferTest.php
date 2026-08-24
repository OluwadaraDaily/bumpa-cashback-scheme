<?php

namespace Tests\Feature;

use App\Contracts\Payments\PaymentProvider;
use App\Data\Payments\PaymentTransferRequest;
use App\Data\Payments\PaymentTransferResult;
use App\Enums\CashbackStatus;
use App\Enums\PaymentAttemptStatus;
use App\Events\CashbackCreated;
use App\Exceptions\PaymentProviderException;
use App\Listeners\TransferCashback;
use App\Models\Badge;
use App\Models\Cashback;
use App\Models\PaymentAccount;
use App\Models\User;
use App\Services\CashbackTransferService;
use App\Services\Payments\FakePaymentProvider;
use Database\Seeders\AchievementSeeder;
use Database\Seeders\BadgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
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

    public function test_provider_exception_retry_reuses_the_attempt_and_transfer_reference(): void
    {
        $this->seed([AchievementSeeder::class, BadgeSeeder::class]);
        $user = User::factory()->create();
        $account = $this->paymentAccount($user);
        $cashback = $this->cashback($user, $account);
        $requests = [];
        $calls = 0;
        $provider = Mockery::mock(PaymentProvider::class);
        $provider->shouldReceive('name')->andReturn('paystack');
        $provider->shouldReceive('transfer')
            ->twice()
            ->andReturnUsing(function (PaymentTransferRequest $request) use (&$calls, &$requests): PaymentTransferResult {
                $calls++;
                $requests[] = $request;

                if ($calls === 1) {
                    throw new PaymentProviderException('Temporary provider outage.');
                }

                return PaymentTransferResult::success('TRF_retry_success');
            });
        $service = new CashbackTransferService($provider);

        try {
            $service->process($cashback);
            $this->fail('The provider exception was not thrown.');
        } catch (PaymentProviderException $exception) {
            $this->assertSame('Temporary provider outage.', $exception->getMessage());
        }

        $firstAttempt = $cashback->paymentAttempts()->sole();
        $firstReference = $firstAttempt->request_payload['reference'];

        $service->process($cashback);

        $retriedAttempt = $cashback->paymentAttempts()->sole();

        $this->assertSame($firstAttempt->id, $retriedAttempt->id);
        $this->assertSame($firstReference, $retriedAttempt->request_payload['reference']);
        $this->assertSame($requests[0]->reference, $requests[1]->reference);
        $this->assertSame('TRF_retry_success', $retriedAttempt->provider_transfer_reference);
        $this->assertSame(PaymentAttemptStatus::PROCESSING, $retriedAttempt->status);
        $this->assertDatabaseCount('payment_attempts', 1);
    }

    public function test_exhausted_listener_retries_mark_the_attempt_and_cashback_as_failed(): void
    {
        $this->seed([AchievementSeeder::class, BadgeSeeder::class]);
        $user = User::factory()->create();
        $account = $this->paymentAccount($user);
        $cashback = $this->cashback($user, $account);
        $exception = new PaymentProviderException('Provider remained unavailable.');
        $provider = Mockery::mock(PaymentProvider::class);
        $provider->shouldReceive('name')->andReturn('paystack');
        $provider->shouldReceive('transfer')->once()->andThrow($exception);
        $service = new CashbackTransferService($provider);

        try {
            $service->process($cashback);
        } catch (PaymentProviderException) {
            // The queue invokes failed() only after all configured retries also fail.
        }

        (new TransferCashback($service))->failed(new CashbackCreated($cashback), $exception);

        $attempt = $cashback->paymentAttempts()->sole();

        $this->assertSame(CashbackStatus::FAILED, $cashback->refresh()->status);
        $this->assertSame(PaymentAttemptStatus::FAILED, $attempt->status);
        $this->assertSame('queue_retries_exhausted', $attempt->failure_code);
        $this->assertSame(
            'Cashback transfer failed after all queue retries.',
            $attempt->failure_message,
        );
        $this->assertNotNull($attempt->completed_at);
    }

    public function test_late_retry_failure_does_not_downgrade_a_paid_cashback(): void
    {
        $this->seed([AchievementSeeder::class, BadgeSeeder::class]);
        $user = User::factory()->create();
        $account = $this->paymentAccount($user);
        $cashback = $this->cashback($user, $account);
        $provider = new FakePaymentProvider;
        $service = new CashbackTransferService($provider);

        $service->process($cashback);

        $attempt = $cashback->paymentAttempts()->sole();
        $attempt->update([
            'status' => PaymentAttemptStatus::SUCCEEDED,
            'completed_at' => now(),
        ]);
        $cashback->update([
            'status' => CashbackStatus::PAID,
            'paid_at' => now(),
        ]);

        $service->markFailedAfterRetries(
            $cashback,
            new PaymentProviderException('Late queue failure callback.'),
        );

        $this->assertSame(CashbackStatus::PAID, $cashback->refresh()->status);
        $this->assertSame(PaymentAttemptStatus::SUCCEEDED, $attempt->refresh()->status);
        $this->assertNull($attempt->failure_code);
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
