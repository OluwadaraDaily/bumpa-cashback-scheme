<?php

namespace App\Enums;

enum OutboxEventType: string
{
    case ORDER_COMPLETED = 'order.completed';
}
