<?php

namespace App\Data\Payments;

final readonly class PaymentTransferRequest
{
    public function __construct(
        public string $recipientReference,
        public int $amount,
        public string $currency,
        public string $reference,
        public string $reason,
    ) {}
}
