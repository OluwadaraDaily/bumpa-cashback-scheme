<?php

namespace Tests\Feature;

use App\Enums\CashbackStatus;
use App\Enums\PaymentAttemptStatus;
use App\Models\Badge;
use App\Models\Cashback;
use App\Models\PaymentAccount;
use App\Models\PaymentAttempt;
use App\Models\User;
use Database\Seeders\AchievementSeeder;
use Database\Seeders\BadgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_authentication_is_required_to_view_cashbacks(): void
    {
        $this->getJson('/cashbacks')->assertUnauthorized();
        $this->getJson('/cashbacks/1')->assertUnauthorized();
    }

    public function test_a_user_can_list_their_cashbacks_with_safe_payment_status_details(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $cashback = $this->cashback($user, CashbackStatus::PAID);
        $this->cashback($otherUser, CashbackStatus::FAILED);
        $attempt = PaymentAttempt::create([
            'cashback_id' => $cashback->id,
            'payment_account_id' => $cashback->payment_account_id,
            'provider' => 'paystack',
            'provider_transfer_reference' => 'TRF_test_reference',
            'status' => PaymentAttemptStatus::SUCCEEDED,
            'amount' => $cashback->amount,
            'currency' => $cashback->currency,
            'request_payload' => [
                'recipient_reference' => 'RCP_should_not_be_returned',
            ],
            'response_payload' => [
                'recipient' => ['account_number' => '0123456789'],
            ],
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/cashbacks');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $cashback->id)
            ->assertJsonPath('data.0.status', 'paid')
            ->assertJsonPath('data.0.badge.name', 'Starter')
            ->assertJsonPath('data.0.payment_attempts.0.id', $attempt->id)
            ->assertJsonPath('data.0.payment_attempts.0.status', 'succeeded')
            ->assertJsonPath('data.0.payment_attempts.0.provider_transfer_reference', 'TRF_test_reference')
            ->assertJsonMissingPath('data.0.payment_attempts.0.request_payload')
            ->assertJsonMissingPath('data.0.payment_attempts.0.response_payload');
    }

    public function test_a_user_can_view_their_cashback_details(): void
    {
        $user = User::factory()->create();
        $cashback = $this->cashback($user, CashbackStatus::PENDING);

        $this->actingAs($user, 'sanctum')
            ->getJson("/cashbacks/{$cashback->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $cashback->id)
            ->assertJsonPath('data.amount', 30_000)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.payment_attempts', []);
    }

    public function test_a_user_cannot_view_another_users_cashback(): void
    {
        $user = User::factory()->create();
        $cashback = $this->cashback(User::factory()->create(), CashbackStatus::PAID);

        $this->actingAs($user, 'sanctum')
            ->getJson("/cashbacks/{$cashback->id}")
            ->assertNotFound();
    }

    public function test_cashback_statuses_are_returned_correctly(): void
    {
        $user = User::factory()->create();
        $this->cashback($user, CashbackStatus::PENDING);
        $this->cashback($user, CashbackStatus::PROCESSING, 'Loyal');
        $this->cashback($user, CashbackStatus::PAID, 'Premium');
        $statusBadge = Badge::create([
            'name' => 'Status Test',
            'description' => 'Used to test cashback statuses.',
            'sort_order' => 99,
        ]);
        $this->cashback($user, CashbackStatus::FAILED, $statusBadge->name);

        $this->actingAs($user, 'sanctum')
            ->getJson('/cashbacks')
            ->assertOk()
            ->assertJsonPath('meta.total', 4)
            ->assertJsonFragment(['status' => 'pending'])
            ->assertJsonFragment(['status' => 'processing'])
            ->assertJsonFragment(['status' => 'paid'])
            ->assertJsonFragment(['status' => 'failed']);
    }

    private function cashback(User $user, CashbackStatus $status, string $badgeName = 'Starter'): Cashback
    {
        $this->seedOnce();

        $account = PaymentAccount::firstOrCreate(
            [
                'user_id' => $user->id,
                'provider' => 'paystack',
            ],
            [
                'recipient_reference' => 'RCP_test_recipient_'.$user->id,
                'currency' => 'NGN',
                'status' => 'active',
            ],
        );

        return Cashback::create([
            'user_id' => $user->id,
            'badge_id' => Badge::query()->where('name', $badgeName)->value('id'),
            'payment_account_id' => $account->id,
            'amount' => 30_000,
            'currency' => 'NGN',
            'status' => $status,
            'description' => 'Starter badge cashback',
            'paid_at' => $status === CashbackStatus::PAID ? now() : null,
        ]);
    }

    private function seedOnce(): void
    {
        if (Badge::query()->where('name', 'Starter')->doesntExist()) {
            $this->seed([AchievementSeeder::class, BadgeSeeder::class]);
        }
    }
}
