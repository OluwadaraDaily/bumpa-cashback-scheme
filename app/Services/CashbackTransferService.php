<?php

namespace App\Services;

use App\Contracts\Payments\PaymentProvider;
use App\Data\Payments\PaymentTransferRequest;
use App\Enums\CashbackStatus;
use App\Enums\PaymentAccountStatus;
use App\Enums\PaymentAttemptStatus;
use App\Exceptions\PaymentProviderException;
use App\Models\Cashback;
use App\Models\PaymentAttempt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CashbackTransferService
{
    public function __construct(private readonly PaymentProvider $provider) {}

    public function process(Cashback $cashback): void
    {
        $transfer = DB::transaction(function () use ($cashback): ?array {
            $cashback = Cashback::query()
                ->with('paymentAccount')
                ->lockForUpdate()
                ->findOrFail($cashback->id);

            if ($cashback->status === CashbackStatus::PAID) {
                return ['skip_reason' => 'already_paid'];
            }

            $attempt = $cashback->paymentAttempts()
                ->where('status', PaymentAttemptStatus::PROCESSING->value)
                ->latest('id')
                ->first();

            if (! $attempt) {
                $paymentAccount = $cashback->paymentAccount;

                if (! $paymentAccount || $paymentAccount->status !== PaymentAccountStatus::ACTIVE) {
                    return [
                        'skip_reason' => 'missing_active_payment_account',
                        'cashback_id' => $cashback->id,
                        'user_id' => $cashback->user_id,
                    ];
                }

                $attemptNumber = $cashback->paymentAttempts()->count() + 1;
                $reference = "cashback_{$cashback->id}_attempt_{$attemptNumber}";
                $payload = [
                    'recipient_reference' => $paymentAccount->recipient_reference,
                    'amount' => $cashback->amount,
                    'currency' => $cashback->currency,
                    'reference' => $reference,
                    'reason' => $cashback->description,
                ];

                $attempt = $cashback->paymentAttempts()->create([
                    'payment_account_id' => $paymentAccount->id,
                    'provider' => $this->provider->name(),
                    'status' => PaymentAttemptStatus::PROCESSING,
                    'amount' => $cashback->amount,
                    'currency' => $cashback->currency,
                    'request_payload' => $payload,
                    'attempted_at' => now(),
                ]);
            } else {
                $attempt->update(['attempted_at' => now()]);
                $payload = $attempt->request_payload;
            }

            $cashback->update(['status' => CashbackStatus::PROCESSING]);

            return [
                'cashback_id' => $cashback->id,
                'attempt_id' => $attempt->id,
                'user_id' => $cashback->user_id,
                'provider' => $this->provider->name(),
                'request' => new PaymentTransferRequest(
                    recipientReference: $payload['recipient_reference'],
                    amount: (int) $payload['amount'],
                    currency: $payload['currency'],
                    reference: $payload['reference'],
                    reason: $payload['reason'],
                ),
            ];
        });

        if (! $transfer) {
            return;
        }

        if (isset($transfer['skip_reason'])) {
            if ($transfer['skip_reason'] === 'missing_active_payment_account') {
                Log::notice('Cashback transfer waiting for payment account', [
                    'cashback_id' => $transfer['cashback_id'],
                    'user_id' => $transfer['user_id'],
                ]);
            }

            return;
        }

        Log::info('Cashback transfer started', [
            'cashback_id' => $transfer['cashback_id'],
            'payment_attempt_id' => $transfer['attempt_id'],
            'user_id' => $transfer['user_id'],
            'provider' => $transfer['provider'],
            'amount' => $transfer['request']->amount,
            'currency' => $transfer['request']->currency,
        ]);

        try {
            $result = $this->provider->transfer($transfer['request']);
        } catch (PaymentProviderException $exception) {
            Log::warning('Cashback transfer provider error', [
                'cashback_id' => $transfer['cashback_id'],
                'payment_attempt_id' => $transfer['attempt_id'],
                'provider' => $transfer['provider'],
                'exception' => $exception::class,
            ]);

            throw $exception;
        }

        DB::transaction(function () use ($transfer, $result): void {
            $attempt = PaymentAttempt::query()->lockForUpdate()->findOrFail($transfer['attempt_id']);
            $cashback = Cashback::query()->lockForUpdate()->findOrFail($transfer['cashback_id']);

            if ($result->successful) {
                $attempt->update([
                    'provider_transfer_reference' => $result->providerReference,
                    'status' => PaymentAttemptStatus::PROCESSING,
                    'response_payload' => $result->response,
                ]);
                $cashback->update(['status' => CashbackStatus::PROCESSING]);

                return;
            }

            $attempt->update([
                'status' => PaymentAttemptStatus::FAILED,
                'response_payload' => $result->response,
                'failure_code' => $result->failureCode,
                'failure_message' => $result->failureMessage,
                'completed_at' => now(),
            ]);
            $cashback->update(['status' => CashbackStatus::FAILED]);
        });

        if ($result->successful) {
            Log::info('Cashback transfer accepted', [
                'cashback_id' => $transfer['cashback_id'],
                'payment_attempt_id' => $transfer['attempt_id'],
                'provider' => $transfer['provider'],
                'provider_transfer_reference' => $result->providerReference,
            ]);
        } else {
            Log::warning('Cashback transfer failed', [
                'cashback_id' => $transfer['cashback_id'],
                'payment_attempt_id' => $transfer['attempt_id'],
                'provider' => $transfer['provider'],
                'failure_code' => $result->failureCode,
            ]);
        }
    }
}
