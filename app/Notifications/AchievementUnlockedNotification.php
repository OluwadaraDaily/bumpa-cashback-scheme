<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class AchievementUnlockedNotification extends Notification
{
    public function __construct(public readonly string $achievementName) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return 'achievement_unlocked';
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Achievement unlocked',
            'message' => "You unlocked the {$this->achievementName} achievement.",
            'achievement_name' => $this->achievementName,
        ];
    }
}
