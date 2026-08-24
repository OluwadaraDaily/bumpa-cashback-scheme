<?php

namespace App\Enums;

enum PaymentAccountStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}
