<?php

namespace App\Providers;

use App\Events\AchievementUnlocked;
use App\Events\OrderCompleted;
use App\Listeners\EvaluateAchievements;
use App\Listeners\EvaluateBadges;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(OrderCompleted::class, EvaluateAchievements::class);
        Event::listen(AchievementUnlocked::class, EvaluateBadges::class);
    }
}
