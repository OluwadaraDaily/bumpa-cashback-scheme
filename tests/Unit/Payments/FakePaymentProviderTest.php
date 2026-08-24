<?php

namespace Tests\Unit\Payments;

use App\Data\Payments\PaymentTransferRequest;
use App\Services\Payments\FakePaymentProvider;
use PHPUnit\Framework\TestCase;

class FakePaymentProviderTest extends TestCase
{
    public function test_fake_provider_can_return_a_successful_transfer(): void
    {
        $provider = new FakePaymentProvider;
        $request = new PaymentTransferRequest(
            recipientReference: 'RCP_fake_recipient',
            amount: 30_000,
            currency: 'NGN',
            reference: 'cashback-1-attempt-1',
            reason: 'Badge cashback',
        );

        $result = $provider->transfer($request);

        $this->assertTrue($result->successful);
        $this->assertStringStartsWith('fake_', $result->providerReference);
        $this->assertSame([$request], $provider->transfers());
    }

    public function test_fake_provider_can_return_a_failed_transfer(): void
    {
        $provider = new FakePaymentProvider;
        $provider->failWith('recipient_invalid', 'The recipient is invalid.');
        $request = new PaymentTransferRequest(
            recipientReference: 'invalid',
            amount: 30_000,
            currency: 'NGN',
            reference: 'cashback-1-attempt-1',
            reason: 'Badge cashback',
        );

        $result = $provider->transfer($request);

        $this->assertFalse($result->successful);
        $this->assertNull($result->providerReference);
        $this->assertSame('recipient_invalid', $result->failureCode);
        $this->assertSame('The recipient is invalid.', $result->failureMessage);
    }
}
