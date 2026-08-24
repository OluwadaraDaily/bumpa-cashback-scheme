<?php

namespace Tests\Feature;

use App\Models\Achievement;
use App\Models\Badge;
use App\Models\User;
use Database\Seeders\AchievementSeeder;
use Database\Seeders\BadgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AchievementStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_their_achievement_and_badge_progress(): void
    {
        $this->seed([AchievementSeeder::class, BadgeSeeder::class]);
        $user = User::factory()->create();
        $user->userAchievements()->create([
            'achievement_id' => Achievement::query()->where('name', 'First Purchase')->value('id'),
            'unlocked_at' => now(),
        ]);
        $user->userBadges()->create([
            'badge_id' => Badge::query()->where('name', 'Starter')->value('id'),
            'unlocked_at' => now(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/users/{$user->id}/achievements")
            ->assertOk()
            ->assertExactJson([
                'unlocked_achievements' => ['First Purchase'],
                'next_available_achievements' => ['5 Purchases', '₦10,000 Spent'],
                'current_badge' => 'Starter',
                'next_badge' => 'Loyal',
                'remaining_to_unlock_next_badge' => 1,
            ]);
    }

    public function test_user_cannot_view_another_users_achievement_status(): void
    {
        $this->seed([AchievementSeeder::class, BadgeSeeder::class]);
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson("/users/{$otherUser->id}/achievements")
            ->assertForbidden();
    }

    public function test_admin_can_view_another_users_achievement_status(): void
    {
        $this->seed([AchievementSeeder::class, BadgeSeeder::class]);
        $admin = User::factory()->admin()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson("/users/{$otherUser->id}/achievements")
            ->assertOk()
            ->assertJsonStructure([
                'unlocked_achievements',
                'next_available_achievements',
                'current_badge',
                'next_badge',
                'remaining_to_unlock_next_badge',
            ]);
    }
}
