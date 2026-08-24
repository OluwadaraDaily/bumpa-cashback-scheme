<?php

namespace Tests\Feature;

use App\Events\BadgeUnlocked;
use App\Models\Achievement;
use App\Models\Badge;
use App\Models\User;
use App\Services\AchievementEvaluator;
use App\Services\BadgeEvaluator;
use Database\Seeders\AchievementSeeder;
use Database\Seeders\BadgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BadgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_starter_badge_requires_its_achievement(): void
    {
        $this->seed([AchievementSeeder::class, BadgeSeeder::class]);
        Event::fake([BadgeUnlocked::class]);
        $user = User::factory()->create();

        $this->unlock($user, 'First Purchase');
        app(BadgeEvaluator::class)->evaluate($user);

        $this->assertDatabaseHas('user_badges', [
            'user_id' => $user->id,
            'badge_id' => $this->badgeId('Starter'),
        ]);
        $this->assertDatabaseMissing('user_badges', [
            'user_id' => $user->id,
            'badge_id' => $this->badgeId('Loyal'),
        ]);
        Event::assertDispatched(BadgeUnlocked::class, function (BadgeUnlocked $event): bool {
            return $event->badge_name === 'Starter';
        });
    }

    public function test_loyal_badge_requires_all_of_its_achievements(): void
    {
        $this->seed([AchievementSeeder::class, BadgeSeeder::class]);
        Event::fake([BadgeUnlocked::class]);
        $user = User::factory()->create();

        $this->unlock($user, 'First Purchase');
        app(BadgeEvaluator::class)->evaluate($user);

        $this->assertDatabaseMissing('user_badges', [
            'user_id' => $user->id,
            'badge_id' => $this->badgeId('Loyal'),
        ]);

        $this->unlock($user, '5 Purchases');
        app(BadgeEvaluator::class)->evaluate($user);

        $this->assertDatabaseHas('user_badges', [
            'user_id' => $user->id,
            'badge_id' => $this->badgeId('Loyal'),
        ]);
    }

    public function test_badges_are_not_unlocked_twice(): void
    {
        $this->seed([AchievementSeeder::class, BadgeSeeder::class]);
        Event::fake([BadgeUnlocked::class]);
        $user = User::factory()->create();
        $this->unlock($user, 'First Purchase');

        $evaluator = app(BadgeEvaluator::class);
        $evaluator->evaluate($user);
        $evaluator->evaluate($user);

        $this->assertDatabaseCount('user_badges', 1);
        Event::assertDispatchedTimes(BadgeUnlocked::class, 1);
    }

    public function test_badges_are_re_evaluated_when_the_achievement_was_already_unlocked(): void
    {
        $this->seed([AchievementSeeder::class, BadgeSeeder::class]);
        Event::fake([BadgeUnlocked::class]);
        $user = User::factory()->create();

        $this->unlock($user, 'First Purchase');

        app(AchievementEvaluator::class)->evaluate($user);

        $this->assertDatabaseHas('user_badges', [
            'user_id' => $user->id,
            'badge_id' => $this->badgeId('Starter'),
        ]);
        Event::assertDispatched(BadgeUnlocked::class, function (BadgeUnlocked $event): bool {
            return $event->badge_name === 'Starter';
        });
    }

    private function unlock(User $user, string $achievementName): void
    {
        $user->userAchievements()->create([
            'achievement_id' => $this->achievementId($achievementName),
            'unlocked_at' => now(),
        ]);
    }

    private function achievementId(string $name): int
    {
        return (int) Achievement::query()->where('name', $name)->value('id');
    }

    private function badgeId(string $name): int
    {
        return (int) Badge::query()->where('name', $name)->value('id');
    }
}
