<?php

namespace App\Contracts\Payments;

use App\Data\Payments\PaymentTransferRequest;
use App\Data\Payments\PaymentTransferResult;

interface PaymentProvider
{
    public function transfer(PaymentTransferRequest $request): PaymentTransferResult;
}
