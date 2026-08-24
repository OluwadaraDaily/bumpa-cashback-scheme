<?php

namespace Tests\Feature;

use App\Events\AchievementUnlocked;
use App\Models\User;
use App\Notifications\AchievementUnlockedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AchievementNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authentication_is_required_to_view_notifications(): void
    {
        $this->getJson(route('notifications.index'))->assertUnauthorized();
    }

    public function test_achievement_unlocked_event_stores_one_notification(): void
    {
        $user = User::factory()->create();

        AchievementUnlocked::dispatch('First Purchase', $user);
        AchievementUnlocked::dispatch('First Purchase', $user);

        $notification = $user->notifications()->sole();

        $this->assertSame('achievement_unlocked', $notification->type);
        $this->assertSame('First Purchase', $notification->data['achievement_name']);
        $this->assertSame('Achievement unlocked', $notification->data['title']);
    }

    public function test_user_can_list_unread_notifications_and_mark_one_as_read(): void
    {
        $user = User::factory()->create();
        $user->notify(new AchievementUnlockedNotification('First Purchase'));
        $notification = $user->notifications()->sole();

        $this->actingAs($user)
            ->getJson(route('notifications.index'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $notification->id)
            ->assertJsonPath('data.0.type', 'achievement_unlocked')
            ->assertJsonPath('data.0.achievement_name', 'First Purchase');

        $this->actingAs($user)
            ->patchJson(route('notifications.read', $notification))
            ->assertNoContent();

        $this->assertNotNull($notification->fresh()->read_at);

        $this->actingAs($user)
            ->getJson(route('notifications.index'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_user_cannot_read_another_users_notification(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $owner->notify(new AchievementUnlockedNotification('First Purchase'));
        $notification = $owner->notifications()->sole();

        $this->actingAs($otherUser)
            ->patchJson(route('notifications.read', $notification))
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }
}
