<?php

namespace App\Services\Outbox;

use App\Models\OutboxMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class OutboxRelay
{
    private const CLAIM_TIMEOUT_MINUTES = 5;

    public function __construct(private readonly OutboxEventPublisher $publisher) {}

    public function publish(OutboxMessage $message): bool
    {
        $claimed = $this->claim($message->id);

        if (! $claimed) {
            return false;
        }

        try {
            $this->publisher->publish($claimed);
        } catch (Throwable $exception) {
            OutboxMessage::query()
                ->whereKey($claimed->id)
                ->where('processing_token', $claimed->processing_token)
                ->whereNull('published_at')
                ->update([
                    'processing_at' => null,
                    'processing_token' => null,
                    'last_error' => Str::limit($exception->getMessage(), 2000, ''),
                    'updated_at' => now(),
                ]);

            throw $exception;
        }

        $published = OutboxMessage::query()
            ->whereKey($claimed->id)
            ->where('processing_token', $claimed->processing_token)
            ->whereNull('published_at')
            ->update([
                'processing_at' => null,
                'processing_token' => null,
                'published_at' => now(),
                'last_error' => null,
                'updated_at' => now(),
            ]);

        if ($published === 1) {
            Log::info('Outbox event published', [
                'outbox_message_id' => $claimed->id,
                'event_type' => $claimed->event_type->value,
                'aggregate_id' => $claimed->aggregate_id,
                'attempts' => $claimed->attempts,
            ]);
        }

        return $published === 1;
    }

    public function publishSafely(OutboxMessage $message): bool
    {
        try {
            return $this->publish($message);
        } catch (Throwable $exception) {
            Log::warning('Immediate outbox event publish failed; scheduler will retry', [
                'outbox_message_id' => $message->id,
                'event_type' => $message->event_type->value,
                'aggregate_id' => $message->aggregate_id,
                'exception' => $exception::class,
            ]);

            return false;
        }
    }

    /**
     * @return array{published: int, failed: int}
     */
    public function publishPending(int $limit = 100): array
    {
        $messages = OutboxMessage::query()
            ->whereNull('published_at')
            ->where('available_at', '<=', now())
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('processing_at')
                    ->orWhere('processing_at', '<=', now()->subMinutes(self::CLAIM_TIMEOUT_MINUTES));
            })
            ->oldest('id')
            ->limit($limit)
            ->get();

        $published = 0;
        $failed = 0;

        foreach ($messages as $message) {
            try {
                $published += (int) $this->publish($message);
            } catch (Throwable $exception) {
                $failed++;

                Log::warning('Outbox event publish failed', [
                    'outbox_message_id' => $message->id,
                    'event_type' => $message->event_type->value,
                    'exception' => $exception::class,
                ]);
            }
        }

        return ['published' => $published, 'failed' => $failed];
    }

    private function claim(int $messageId): ?OutboxMessage
    {
        return DB::transaction(function () use ($messageId): ?OutboxMessage {
            $message = OutboxMessage::query()->lockForUpdate()->find($messageId);

            if (! $message || $message->published_at) {
                return null;
            }

            if ($message->available_at->isFuture()) {
                return null;
            }

            if ($message->processing_at?->isAfter(now()->subMinutes(self::CLAIM_TIMEOUT_MINUTES))) {
                return null;
            }

            $message->update([
                'processing_at' => now(),
                'processing_token' => (string) Str::uuid(),
                'attempts' => $message->attempts + 1,
                'last_error' => null,
            ]);

            return $message->refresh();
        });
    }
}
