<?php

namespace App\Listeners;

use App\Events\OrderCompleted;
use App\Services\AchievementEvaluator;
use Illuminate\Contracts\Queue\ShouldQueue;

class EvaluateAchievements implements ShouldQueue
{
    public function __construct(private readonly AchievementEvaluator $evaluator) {}

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [5, 30, 120];

    public function handle(OrderCompleted $event): void
    {
        $this->evaluator->evaluate($event->order->user);
    }
}
