<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PaymentProvider;
use App\Data\Payments\PaymentTransferRequest;
use App\Data\Payments\PaymentTransferResult;
use Illuminate\Support\Str;

class FakePaymentProvider implements PaymentProvider
{
    /**
     * @var array<int, PaymentTransferRequest>
     */
    private array $transfers = [];

    private bool $successful = true;

    private string $failureCode = 'fake_failure';

    private string $failureMessage = 'The fake provider rejected the transfer.';

    public function transfer(PaymentTransferRequest $request): PaymentTransferResult
    {
        $this->transfers[] = $request;

        if (! $this->successful) {
            return PaymentTransferResult::failure(
                $this->failureCode,
                $this->failureMessage,
                ['provider' => 'fake', 'reference' => $request->reference],
            );
        }

        return PaymentTransferResult::success(
            'fake_'.Str::lower(Str::random(20)),
            [
                'provider' => 'fake',
                'reference' => $request->reference,
                'amount' => $request->amount,
                'currency' => $request->currency,
            ],
        );
    }

    public function failWith(string $code, string $message): self
    {
        $this->successful = false;
        $this->failureCode = $code;
        $this->failureMessage = $message;

        return $this;
    }

    public function succeed(): self
    {
        $this->successful = true;

        return $this;
    }

    /**
     * @return array<int, PaymentTransferRequest>
     */
    public function transfers(): array
    {
        return $this->transfers;
    }
}
