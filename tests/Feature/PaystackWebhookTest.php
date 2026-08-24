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
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PaystackWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-paystack-secret';

    public function test_success_webhook_marks_cashback_as_paid(): void
    {
        $attempt = $this->paymentAttempt();
        $payload = $this->payload('transfer.success', $attempt);

        $response = $this->postWebhook($payload);

        $response->assertOk()->assertJson(['status' => 'ok']);
        $this->assertDatabaseHas('payment_attempts', [
            'id' => $attempt->id,
            'status' => PaymentAttemptStatus::SUCCEEDED->value,
            'provider_transfer_reference' => 'TRF_webhook_test',
        ]);
        $this->assertDatabaseHas('cashbacks', [
            'id' => $attempt->cashback_id,
            'status' => CashbackStatus::PAID->value,
        ]);
    }

    public function test_failed_webhook_marks_cashback_as_failed(): void
    {
        $attempt = $this->paymentAttempt();
        $payload = $this->payload('transfer.failed', $attempt);

        $this->postWebhook($payload)->assertOk();

        $this->assertDatabaseHas('payment_attempts', [
            'id' => $attempt->id,
            'status' => PaymentAttemptStatus::FAILED->value,
            'failure_code' => 'transfer.failed',
        ]);
        $this->assertDatabaseHas('cashbacks', [
            'id' => $attempt->cashback_id,
            'status' => CashbackStatus::FAILED->value,
        ]);
    }

    public function test_duplicate_success_webhook_is_safe(): void
    {
        $attempt = $this->paymentAttempt();
        $payload = $this->payload('transfer.success', $attempt);

        $this->postWebhook($payload)->assertOk();
        $this->postWebhook($payload)->assertOk();

        $this->assertDatabaseCount('payment_attempts', 1);
        $this->assertDatabaseHas('cashbacks', [
            'id' => $attempt->cashback_id,
            'status' => CashbackStatus::PAID->value,
        ]);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $attempt = $this->paymentAttempt();
        $payload = $this->payload('transfer.success', $attempt);

        $response = $this->call(
            'POST',
            '/cashback/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_PAYSTACK_SIGNATURE' => 'invalid-signature',
            ],
            json_encode($payload, JSON_THROW_ON_ERROR),
        );

        $response->assertUnauthorized();
        $this->assertDatabaseHas('cashbacks', [
            'id' => $attempt->cashback_id,
            'status' => CashbackStatus::PROCESSING->value,
        ]);
    }

    public function test_unlisted_webhook_ip_is_rejected_when_ip_filtering_is_enabled(): void
    {
        config(['services.paystack.webhook_ips' => ['52.31.139.75']]);
        $attempt = $this->paymentAttempt();
        $payload = $this->payload('transfer.success', $attempt);
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        $response = $this->call(
            'POST',
            '/cashback/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'REMOTE_ADDR' => '127.0.0.1',
                'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, self::SECRET),
            ],
            $body,
        );

        $response->assertForbidden();
    }

    private function paymentAttempt(): PaymentAttempt
    {
        config(['services.paystack.secret_key' => self::SECRET]);
        $this->seed([AchievementSeeder::class, BadgeSeeder::class]);

        $user = User::factory()->create();
        $account = PaymentAccount::create([
            'user_id' => $user->id,
            'provider' => 'paystack',
            'recipient_reference' => 'RCP_webhook_test',
            'currency' => 'NGN',
            'status' => 'active',
        ]);
        $cashback = Cashback::create([
            'user_id' => $user->id,
            'badge_id' => Badge::query()->where('name', 'Starter')->value('id'),
            'payment_account_id' => $account->id,
            'amount' => 30_000,
            'currency' => 'NGN',
            'status' => CashbackStatus::PROCESSING,
            'description' => 'Starter badge cashback',
        ]);

        return PaymentAttempt::create([
            'cashback_id' => $cashback->id,
            'payment_account_id' => $account->id,
            'provider' => 'paystack',
            'provider_transfer_reference' => null,
            'status' => PaymentAttemptStatus::PROCESSING,
            'amount' => 30_000,
            'currency' => 'NGN',
            'request_payload' => [
                'reference' => 'cashback_1_attempt_1',
                'amount' => 30_000,
                'currency' => 'NGN',
            ],
            'attempted_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $event, PaymentAttempt $attempt): array
    {
        return [
            'event' => $event,
            'data' => [
                'amount' => $attempt->amount,
                'currency' => $attempt->currency,
                'reference' => $attempt->request_payload['reference'],
                'transfer_code' => 'TRF_webhook_test',
                'status' => $event === 'transfer.success' ? 'success' : 'failed',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postWebhook(array $payload): TestResponse
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        return $this->call(
            'POST',
            '/cashback/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, self::SECRET),
            ],
            $body,
        );
    }
}
