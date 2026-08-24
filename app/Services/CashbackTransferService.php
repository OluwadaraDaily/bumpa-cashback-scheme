<?php

namespace App\Services;

use App\Contracts\Payments\PaymentProvider;
use App\Data\Payments\PaymentTransferRequest;
use App\Enums\CashbackStatus;
use App\Enums\PaymentAccountStatus;
use App\Enums\PaymentAttemptStatus;
use App\Models\Cashback;
use App\Models\PaymentAttempt;
use Illuminate\Support\Facades\DB;

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
                return null;
            }

            $attempt = $cashback->paymentAttempts()
                ->where('status', PaymentAttemptStatus::PROCESSING->value)
                ->latest('id')
                ->first();

            if (! $attempt) {
                $paymentAccount = $cashback->paymentAccount;

                if (! $paymentAccount || $paymentAccount->status !== PaymentAccountStatus::ACTIVE) {
                    return null;
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

        $result = $this->provider->transfer($transfer['request']);

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
    }
}
