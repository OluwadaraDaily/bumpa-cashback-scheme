<?php

namespace App\Providers;

use App\Events\AchievementUnlocked;
use App\Events\BadgeUnlocked;
use App\Events\CashbackCreated;
use App\Events\OrderCompleted;
use App\Listeners\CreateCashback;
use App\Listeners\EvaluateAchievements;
use App\Listeners\EvaluateBadges;
use App\Listeners\TransferCashback;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(OrderCompleted::class, EvaluateAchievements::class);
        Event::listen(AchievementUnlocked::class, EvaluateBadges::class);
        Event::listen(BadgeUnlocked::class, CreateCashback::class);
        Event::listen(CashbackCreated::class, TransferCashback::class);
    }
}
