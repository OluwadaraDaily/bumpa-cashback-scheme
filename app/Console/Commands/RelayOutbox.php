<?php

namespace App\Console\Commands;

use App\Services\Outbox\OutboxRelay;
use Illuminate\Console\Command;

class RelayOutbox extends Command
{
    protected $signature = 'outbox:relay {--limit=100 : Maximum messages to publish}';

    protected $description = 'Publish pending transactional outbox events';

    public function handle(OutboxRelay $relay): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);

        if ($limit === false || $limit < 1 || $limit > 1000) {
            $this->error('The limit must be an integer between 1 and 1000.');

            return self::INVALID;
        }

        $result = $relay->publishPending($limit);

        $this->info("Published {$result['published']} outbox message(s); {$result['failed']} failed.");

        return $result['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
