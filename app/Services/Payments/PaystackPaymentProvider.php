<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentProvider;
use App\Data\Payments\PaymentTransferRequest;
use App\Data\Payments\PaymentTransferResult;
use App\Exceptions\PaymentProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class PaystackPaymentProvider implements PaymentProvider
{
    public function name(): string
    {
        return 'paystack';
    }

    public function transfer(PaymentTransferRequest $request): PaymentTransferResult
    {
        $secretKey = (string) config('services.paystack.secret_key');

        if ($secretKey === '') {
            throw new PaymentProviderException('PAYSTACK_SECRET_KEY is not configured.');
        }

        try {
            $response = Http::baseUrl((string) config('services.paystack.base_url'))
                ->withToken($secretKey)
                ->acceptJson()
                ->timeout((int) config('services.paystack.timeout', 10))
                ->post('/transfer', [
                    'source' => 'balance',
                    'amount' => $request->amount,
                    'recipient' => $request->recipientReference,
                    'reference' => $request->reference,
                    'reason' => $request->reason,
                    'currency' => $request->currency,
                ]);
        } catch (ConnectionException $exception) {
            throw new PaymentProviderException('Paystack could not be reached.', previous: $exception);
        }

        if ($response->serverError()) {
            throw new PaymentProviderException('Paystack returned a server error.');
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        if ($response->successful() && ($payload['status'] ?? false) === true) {
            return PaymentTransferResult::success(
                (string) ($data['transfer_code'] ?? $data['reference'] ?? $request->reference),
                $payload,
            );
        }

        return PaymentTransferResult::failure(
            (string) ($data['status'] ?? 'paystack_transfer_failed'),
            (string) ($payload['message'] ?? 'Paystack rejected the transfer.'),
            $payload,
        );
    }
}
