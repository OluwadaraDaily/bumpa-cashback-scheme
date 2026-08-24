<?php

namespace Tests\Feature;

use App\Events\AchievementsEvaluated;
use App\Events\BadgeUnlocked;
use App\Events\CashbackCreated;
use App\Events\OrderCompleted;
use App\Listeners\CreateCashback;
use App\Listeners\EvaluateAchievements;
use App\Listeners\EvaluateBadges;
use App\Listeners\TransferCashback;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as FrameworkEventServiceProvider;
use Tests\TestCase;

class EventListenerRegistrationTest extends TestCase
{
    public function test_backend_events_use_one_explicit_listener_each(): void
    {
        $frameworkProvider = $this->app->getProvider(FrameworkEventServiceProvider::class);

        $this->assertNotNull($frameworkProvider);
        $this->assertFalse($frameworkProvider->shouldDiscoverEvents());

        /** @var Dispatcher $dispatcher */
        $dispatcher = $this->app->make('events');
        $registeredListeners = $dispatcher->getRawListeners();

        $expectedListeners = [
            OrderCompleted::class => EvaluateAchievements::class,
            AchievementsEvaluated::class => EvaluateBadges::class,
            BadgeUnlocked::class => CreateCashback::class,
            CashbackCreated::class => TransferCashback::class,
        ];

        foreach ($expectedListeners as $event => $listener) {
            $this->assertSame(
                [$listener],
                $registeredListeners[$event] ?? [],
                "The {$event} event must have exactly one explicit listener.",
            );
        }
    }
}
