<?php

namespace Tests\Feature;

use App\Enums\CashbackStatus;
use App\Enums\PaymentAccountStatus;
use App\Enums\PaymentAttemptStatus;
use App\Models\Badge;
use App\Models\Cashback;
use App\Models\PaymentAccount;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Services\PaymentAccountService;
use Database\Seeders\AchievementSeeder;
use Database\Seeders\BadgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_authentication_is_required_to_manage_payment_accounts(): void
    {
        $this->getJson('/payment-accounts')->assertUnauthorized();
        $this->putJson('/payment-accounts/paystack', [
            'recipient_reference' => 'RCP_test_recipient',
        ])->assertUnauthorized();
        $this->deleteJson('/payment-accounts/paystack')->assertUnauthorized();
    }

    public function test_a_user_can_save_a_paystack_recipient_reference(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson('/payment-accounts/paystack', [
                'recipient_reference' => 'RCP_test_recipient',
            ])
            ->assertOk()
            ->assertJsonPath('data.provider', 'paystack')
            ->assertJsonPath('data.recipient_reference', 'RCP_test_recipient')
            ->assertJsonPath('data.currency', 'NGN')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('payment_accounts', [
            'user_id' => $user->id,
            'provider' => 'paystack',
            'recipient_reference' => 'RCP_test_recipient',
            'currency' => 'NGN',
            'status' => PaymentAccountStatus::ACTIVE->value,
        ]);
    }

    public function test_saving_again_replaces_the_existing_provider_account(): void
    {
        $user = User::factory()->create();
        PaymentAccount::create([
            'user_id' => $user->id,
            'provider' => 'paystack',
            'recipient_reference' => 'RCP_old_recipient',
            'currency' => 'NGN',
            'status' => PaymentAccountStatus::INACTIVE,
        ]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/payment-accounts/paystack', [
                'recipient_reference' => 'RCP_new_recipient',
            ])
            ->assertOk()
            ->assertJsonPath('data.recipient_reference', 'RCP_new_recipient')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseCount('payment_accounts', 1);
        $this->assertDatabaseHas('payment_accounts', [
            'user_id' => $user->id,
            'provider' => 'paystack',
            'recipient_reference' => 'RCP_new_recipient',
            'status' => PaymentAccountStatus::ACTIVE->value,
        ]);
        $this->assertFalse(
            PaymentAccount::query()->where('user_id', $user->id)->sole()->metadata['is_default'],
        );
    }

    public function test_default_recipient_does_not_replace_a_users_account(): void
    {
        config()->set('services.paystack.default_recipient', 'RCP_shared_test_recipient');
        $user = User::factory()->create();
        $account = PaymentAccount::create([
            'user_id' => $user->id,
            'provider' => 'paystack',
            'recipient_reference' => 'RCP_users_recipient',
            'currency' => 'NGN',
            'status' => PaymentAccountStatus::ACTIVE,
            'metadata' => ['is_default' => false],
        ]);

        $result = app(PaymentAccountService::class)->assignDefault($user);

        $this->assertSame($account->id, $result->id);
        $this->assertSame('RCP_users_recipient', $result->recipient_reference);
        $this->assertDatabaseCount('payment_accounts', 1);
    }

    public function test_assigning_default_account_resumes_existing_pending_cashback(): void
    {
        config()->set('services.paystack.default_recipient', 'RCP_shared_test_recipient');
        $this->seed([AchievementSeeder::class, BadgeSeeder::class]);
        $user = User::factory()->create();
        $cashback = $this->cashback($user, CashbackStatus::PENDING);

        app(PaymentAccountService::class)->assignDefault($user);

        $cashback->refresh();

        $this->assertSame(CashbackStatus::PROCESSING, $cashback->status);
        $this->assertSame($user->paymentAccounts()->sole()->id, $cashback->payment_account_id);
        $this->assertDatabaseHas('payment_attempts', [
            'cashback_id' => $cashback->id,
            'status' => PaymentAttemptStatus::PROCESSING->value,
        ]);
    }

    public function test_activating_an_account_resumes_pending_cashback(): void
    {
        $this->seed([AchievementSeeder::class, BadgeSeeder::class]);
        $user = User::factory()->create();
        $cashback = $this->cashback($user, CashbackStatus::PENDING);

        $this->actingAs($user, 'sanctum')
            ->putJson('/payment-accounts/paystack', [
                'recipient_reference' => 'RCP_new_recipient',
            ])
            ->assertOk();

        $cashback->refresh();
        $attempt = $cashback->paymentAttempts()->sole();

        $this->assertSame(CashbackStatus::PROCESSING, $cashback->status);
        $this->assertSame($user->paymentAccounts()->sole()->id, $cashback->payment_account_id);
        $this->assertSame(PaymentAttemptStatus::PROCESSING, $attempt->status);
        $this->assertSame('RCP_new_recipient', $attempt->request_payload['recipient_reference']);
    }

    public function test_updating_an_account_retries_failed_cashback(): void
    {
        $this->seed([AchievementSeeder::class, BadgeSeeder::class]);
        $user = User::factory()->create();
        $account = PaymentAccount::create([
            'user_id' => $user->id,
            'provider' => 'paystack',
            'recipient_reference' => 'RCP_old_recipient',
            'currency' => 'NGN',
            'status' => PaymentAccountStatus::ACTIVE,
        ]);
        $cashback = $this->cashback($user, CashbackStatus::FAILED);
        $cashback->update(['payment_account_id' => $account->id]);
        $failedAttempt = PaymentAttempt::create([
            'cashback_id' => $cashback->id,
            'payment_account_id' => $account->id,
            'provider' => 'paystack',
            'status' => PaymentAttemptStatus::FAILED,
            'amount' => $cashback->amount,
            'currency' => $cashback->currency,
            'request_payload' => [
                'recipient_reference' => 'RCP_old_recipient',
                'amount' => $cashback->amount,
                'currency' => $cashback->currency,
                'reference' => "cashback_{$cashback->id}_attempt_1",
                'reason' => $cashback->description,
            ],
            'failure_code' => 'recipient_invalid',
            'failure_message' => 'Recipient is invalid.',
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/payment-accounts/paystack', [
                'recipient_reference' => 'RCP_replacement_recipient',
            ])
            ->assertOk();

        $cashback->refresh();
        $attempts = $cashback->paymentAttempts()->orderBy('id')->get();

        $this->assertSame(CashbackStatus::PROCESSING, $cashback->status);
        $this->assertCount(2, $attempts);
        $this->assertSame($failedAttempt->id, $attempts[0]->id);
        $this->assertSame(PaymentAttemptStatus::FAILED, $attempts[0]->status);
        $this->assertSame(PaymentAttemptStatus::PROCESSING, $attempts[1]->status);
        $this->assertSame('RCP_replacement_recipient', $attempts[1]->request_payload['recipient_reference']);
        $this->assertSame("cashback_{$cashback->id}_attempt_2", $attempts[1]->request_payload['reference']);
    }

    public function test_a_user_can_list_only_their_payment_accounts(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        PaymentAccount::create([
            'user_id' => $user->id,
            'provider' => 'paystack',
            'recipient_reference' => 'RCP_my_recipient',
            'currency' => 'NGN',
            'status' => PaymentAccountStatus::ACTIVE,
        ]);
        PaymentAccount::create([
            'user_id' => $otherUser->id,
            'provider' => 'paystack',
            'recipient_reference' => 'RCP_other_recipient',
            'currency' => 'NGN',
            'status' => PaymentAccountStatus::ACTIVE,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/payment-accounts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.recipient_reference', 'RCP_my_recipient');
    }

    public function test_a_user_can_deactivate_their_payment_account(): void
    {
        $user = User::factory()->create();
        PaymentAccount::create([
            'user_id' => $user->id,
            'provider' => 'paystack',
            'recipient_reference' => 'RCP_test_recipient',
            'currency' => 'NGN',
            'status' => PaymentAccountStatus::ACTIVE,
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/payment-accounts/paystack')
            ->assertNoContent();

        $this->assertDatabaseHas('payment_accounts', [
            'user_id' => $user->id,
            'provider' => 'paystack',
            'status' => PaymentAccountStatus::INACTIVE->value,
        ]);
    }

    public function test_payment_account_input_is_validated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson('/payment-accounts/paystack', [
                'recipient_reference' => 'recipient with spaces',
                'currency' => 'USD',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['recipient_reference', 'currency']);
    }

    public function test_unsupported_provider_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson('/payment-accounts/flutterwave', [
                'recipient_reference' => 'recipient_reference',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['provider']);
    }

    private function cashback(User $user, CashbackStatus $status): Cashback
    {
        return Cashback::create([
            'user_id' => $user->id,
            'badge_id' => Badge::query()->where('name', 'Starter')->value('id'),
            'amount' => 30_000,
            'currency' => 'NGN',
            'status' => $status,
            'description' => 'Starter badge cashback',
        ]);
    }
}
