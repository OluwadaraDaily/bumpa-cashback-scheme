<?php

namespace App\Listeners;

use App\Events\AchievementsEvaluated;
use App\Services\BadgeEvaluator;
use Illuminate\Contracts\Queue\ShouldQueue;

class EvaluateBadges implements ShouldQueue
{
    public function __construct(private readonly BadgeEvaluator $evaluator) {}

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [5, 30, 120];

    public function handle(AchievementsEvaluated $event): void
    {
        $this->evaluator->evaluate($event->user);
    }
}
