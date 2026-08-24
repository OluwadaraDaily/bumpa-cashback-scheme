<?php

namespace Tests\Unit\Payments;

use App\Data\Payments\PaymentTransferRequest;
use App\Services\Payments\PaystackPaymentProvider;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaystackPaymentProviderTest extends TestCase
{
    public function test_it_sends_a_transfer_request_to_paystack(): void
    {
        config([
            'services.paystack.secret_key' => 'sk_test_example',
            'services.paystack.base_url' => 'https://api.paystack.co',
        ]);
        Http::fake([
            'https://api.paystack.co/transfer' => Http::response([
                'status' => true,
                'message' => 'Transfer has been queued',
                'data' => [
                    'transfer_code' => 'TRF_test_transfer',
                    'reference' => 'cashback_1_attempt_1',
                    'status' => 'pending',
                ],
            ], 200),
        ]);

        $result = (new PaystackPaymentProvider)->transfer(new PaymentTransferRequest(
            recipientReference: 'RCP_test_recipient',
            amount: 30_000,
            currency: 'NGN',
            reference: 'cashback_1_attempt_1',
            reason: 'Starter badge cashback',
        ));

        $this->assertTrue($result->successful);
        $this->assertSame('TRF_test_transfer', $result->providerReference);
        Http::assertSent(function (ClientRequest $request): bool {
            return $request->url() === 'https://api.paystack.co/transfer'
                && $request->data()['source'] === 'balance'
                && $request->data()['amount'] === 30_000
                && $request->data()['recipient'] === 'RCP_test_recipient'
                && $request->data()['reference'] === 'cashback_1_attempt_1';
        });
    }

    public function test_it_returns_a_failure_for_a_rejected_transfer(): void
    {
        config(['services.paystack.secret_key' => 'sk_test_example']);
        Http::fake([
            'https://api.paystack.co/transfer' => Http::response([
                'status' => false,
                'message' => 'Recipient is invalid.',
                'data' => ['status' => 'failed'],
            ], 400),
        ]);

        $result = (new PaystackPaymentProvider)->transfer(new PaymentTransferRequest(
            recipientReference: 'invalid',
            amount: 30_000,
            currency: 'NGN',
            reference: 'cashback_1_attempt_1',
            reason: 'Starter badge cashback',
        ));

        $this->assertFalse($result->successful);
        $this->assertSame('failed', $result->failureCode);
        $this->assertSame('Recipient is invalid.', $result->failureMessage);
    }
}
