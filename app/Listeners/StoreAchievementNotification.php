<?php

namespace App\Listeners;

use App\Events\AchievementUnlocked;
use App\Models\User;
use App\Notifications\AchievementUnlockedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StoreAchievementNotification implements ShouldQueue
{
    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [5, 30, 120];

    public function handle(AchievementUnlocked $event): void
    {
        $created = DB::transaction(function () use ($event): bool {
            $user = User::query()->lockForUpdate()->findOrFail($event->user->id);
            $alreadyStored = $user->notifications()
                ->where('type', 'achievement_unlocked')
                ->where('data->achievement_name', $event->achievement_name)
                ->exists();

            if ($alreadyStored) {
                return false;
            }

            $user->notify(new AchievementUnlockedNotification($event->achievement_name));

            return true;
        });

        if ($created) {
            Log::info('Achievement notification stored', [
                'achievement_name' => $event->achievement_name,
                'user_id' => $event->user->id,
            ]);
        }
    }
}
