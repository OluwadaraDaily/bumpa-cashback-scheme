<?php

namespace App\Enums;

enum CashbackStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case PAID = 'paid';
    case FAILED = 'failed';
}
