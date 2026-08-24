<?php

namespace App\Data\Payments;

final readonly class PaymentTransferResult
{
    /**
     * @param  array<string, mixed>  $response
     */
    private function __construct(
        public bool $successful,
        public ?string $providerReference,
        public array $response,
        public ?string $failureCode,
        public ?string $failureMessage,
    ) {}

    /**
     * @param  array<string, mixed>  $response
     */
    public static function success(string $providerReference, array $response = []): self
    {
        return new self(true, $providerReference, $response, null, null);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public static function failure(
        string $failureCode,
        string $failureMessage,
        array $response = [],
    ): self {
        return new self(false, null, $response, $failureCode, $failureMessage);
    }
}
