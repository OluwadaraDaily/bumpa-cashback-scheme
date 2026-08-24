<?php

namespace Tests\Feature;

use App\Enums\PaymentAccountStatus;
use App\Models\PaymentAccount;
use App\Models\User;
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
}
