<?php

namespace App\Services\Payments;

use App\Enums\CashbackStatus;
use App\Enums\PaymentAttemptStatus;
use App\Models\PaymentAttempt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaystackWebhookService
{
    private const SUCCESS_EVENT = 'transfer.success';

    private const FAILED_EVENTS = [
        'transfer.failed',
        'transfer.reversed',
    ];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        $event = $payload['event'] ?? null;

        if (! is_string($event) || ! $this->isTransferEvent($event)) {
            Log::info('Paystack webhook ignored', [
                'event' => is_string($event) ? $event : null,
            ]);

            return;
        }

        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            Log::warning('Paystack webhook missing transfer data', ['event' => $event]);

            return;
        }

        $reference = $this->stringValue($data['reference'] ?? null);
        $transferCode = $this->stringValue($data['transfer_code'] ?? null);

        if ($reference === null && $transferCode === null) {
            Log::warning('Paystack webhook missing transfer identifiers', ['event' => $event]);

            return;
        }

        $result = DB::transaction(function () use ($data, $event, $reference, $transferCode): string {
            $attempt = $this->findAttempt($reference, $transferCode);

            if (! $attempt) {
                return 'not_found';
            }

            if (! $this->matchesTransfer($attempt, $data)) {
                return 'mismatch';
            }

            $cashback = $attempt->cashback()->lockForUpdate()->firstOrFail();
            $isSuccess = $event === self::SUCCESS_EVENT;

            if ($isSuccess
                && $attempt->status === PaymentAttemptStatus::SUCCEEDED
                && $cashback->status === CashbackStatus::PAID) {
                return 'duplicate';
            }

            if (! $isSuccess
                && $attempt->status === PaymentAttemptStatus::FAILED
                && $cashback->status === CashbackStatus::FAILED) {
                return 'duplicate';
            }

            $responsePayload = $this->responsePayload($event, $data, $reference, $transferCode);

            if ($isSuccess) {
                $attempt->update([
                    'provider_transfer_reference' => $transferCode ?? $attempt->provider_transfer_reference,
                    'status' => PaymentAttemptStatus::SUCCEEDED,
                    'response_payload' => $responsePayload,
                    'completed_at' => now(),
                ]);
                $cashback->update([
                    'status' => CashbackStatus::PAID,
                    'paid_at' => now(),
                ]);
            } else {
                $attempt->update([
                    'provider_transfer_reference' => $transferCode ?? $attempt->provider_transfer_reference,
                    'status' => PaymentAttemptStatus::FAILED,
                    'response_payload' => $responsePayload,
                    'failure_code' => $event,
                    'failure_message' => $event === 'transfer.reversed'
                        ? 'Paystack reversed the transfer.'
                        : 'Paystack failed the transfer.',
                    'completed_at' => now(),
                ]);
                $cashback->update([
                    'status' => CashbackStatus::FAILED,
                    'paid_at' => null,
                ]);
            }

            return 'processed';
        });

        Log::info('Paystack webhook handled', [
            'event' => $event,
            'reference' => $reference,
            'transfer_code' => $transferCode,
            'result' => $result,
        ]);
    }

    private function isTransferEvent(string $event): bool
    {
        return $event === self::SUCCESS_EVENT || in_array($event, self::FAILED_EVENTS, true);
    }

    private function findAttempt(?string $reference, ?string $transferCode): ?PaymentAttempt
    {
        $query = PaymentAttempt::query()->where('provider', 'paystack');

        if ($transferCode !== null) {
            $attempt = (clone $query)
                ->where('provider_transfer_reference', $transferCode)
                ->lockForUpdate()
                ->first();

            if ($attempt) {
                return $attempt;
            }
        }

        if ($reference === null) {
            return null;
        }

        return $query
            ->where('request_payload->reference', $reference)
            ->lockForUpdate()
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function matchesTransfer(PaymentAttempt $attempt, array $data): bool
    {
        if (array_key_exists('amount', $data) && (int) $data['amount'] !== $attempt->amount) {
            return false;
        }

        if (array_key_exists('currency', $data)
            && strtoupper((string) $data['currency']) !== strtoupper($attempt->currency)) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function responsePayload(
        string $event,
        array $data,
        ?string $reference,
        ?string $transferCode,
    ): array {
        return [
            'event' => $event,
            'reference' => $reference,
            'transfer_code' => $transferCode,
            'amount' => $data['amount'] ?? null,
            'currency' => $data['currency'] ?? null,
            'status' => $data['status'] ?? null,
        ];
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
