<?php

namespace App\Enums;

enum OutboxEventType: string
{
    case ORDER_COMPLETED = 'order.completed';
    case ACHIEVEMENT_UNLOCKED = 'achievement.unlocked';
    case ACHIEVEMENTS_EVALUATED = 'achievements.evaluated';
    case BADGE_UNLOCKED = 'badge.unlocked';
    case CASHBACK_CREATED = 'cashback.created';
}
