<?php

namespace App\Services;

use App\Models\Order;

final class OrderCreationResult
{
    public function __construct(
        public readonly Order $order,
        public readonly bool $replayed,
    ) {}
}
