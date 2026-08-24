<?php

namespace App\Listeners;

use App\Events\BadgeUnlocked;
use App\Services\CashbackCreator;
use Illuminate\Contracts\Queue\ShouldQueue;

class CreateCashback implements ShouldQueue
{
    public function __construct(private readonly CashbackCreator $creator) {}

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [5, 30, 120];

    public function handle(BadgeUnlocked $event): void
    {
        $this->creator->createForBadge($event->user, $event->badge_name);
    }
}
