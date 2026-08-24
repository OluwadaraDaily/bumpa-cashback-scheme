<?php

namespace App\Listeners;

use App\Events\CashbackCreated;
use App\Events\CashbackTransferRequested;
use App\Services\CashbackTransferService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;

class TransferCashback implements ShouldQueue
{
    public function __construct(private readonly CashbackTransferService $transfers) {}

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [5, 30, 120];

    public function handle(CashbackCreated|CashbackTransferRequested $event): void
    {
        $this->transfers->process($event->cashback);
    }

    public function failed(
        CashbackCreated|CashbackTransferRequested $event,
        Throwable $exception,
    ): void {
        $this->transfers->markFailedAfterRetries($event->cashback, $exception);
    }
}
