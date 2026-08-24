<?php

namespace Tests\Feature;

use App\Events\AchievementUnlocked;
use App\Models\Achievement;
use App\Models\Order;
use App\Models\User;
use App\Services\AchievementEvaluator;
use Database\Seeders\AchievementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AchievementTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_purchase_unlocks_only_the_first_purchase_achievement(): void
    {
        $this->seed(AchievementSeeder::class);
        Event::fake([AchievementUnlocked::class]);
        $user = User::factory()->create();

        Order::factory()->for($user)->create(['total' => 100_000]);

        app(AchievementEvaluator::class)->evaluate($user);

        $this->assertDatabaseHas('user_achievements', [
            'user_id' => $user->id,
            'achievement_id' => $this->achievementId('First Purchase'),
        ]);
        $this->assertDatabaseMissing('user_achievements', [
            'user_id' => $user->id,
            'achievement_id' => $this->achievementId('5 Purchases'),
        ]);
        Event::assertDispatched(AchievementUnlocked::class, function (AchievementUnlocked $event): bool {
            return $event->achievement_name === 'First Purchase';
        });
    }

    public function test_five_purchase_achievement_does_not_unlock_before_five_completed_orders(): void
    {
        $this->seed(AchievementSeeder::class);
        Event::fake([AchievementUnlocked::class]);
        $user = User::factory()->create();

        Order::factory()->count(4)->for($user)->create(['total' => 100_000]);

        app(AchievementEvaluator::class)->evaluate($user);

        $this->assertDatabaseMissing('user_achievements', [
            'user_id' => $user->id,
            'achievement_id' => $this->achievementId('5 Purchases'),
        ]);

        Order::factory()->for($user)->create(['total' => 100_000]);

        app(AchievementEvaluator::class)->evaluate($user);

        $this->assertDatabaseHas('user_achievements', [
            'user_id' => $user->id,
            'achievement_id' => $this->achievementId('5 Purchases'),
        ]);
    }

    public function test_spend_achievement_uses_completed_order_totals(): void
    {
        $this->seed(AchievementSeeder::class);
        Event::fake([AchievementUnlocked::class]);
        $user = User::factory()->create();

        Order::factory()->for($user)->create(['total' => 600_000]);
        Order::factory()->for($user)->create(['total' => 400_000]);
        Order::factory()->for($user)->create(['status' => 'cancelled', 'total' => 1_000_000]);

        app(AchievementEvaluator::class)->evaluate($user);

        $this->assertDatabaseHas('user_achievements', [
            'user_id' => $user->id,
            'achievement_id' => $this->achievementId('₦10,000 Spent'),
        ]);
    }

    public function test_rechecking_achievements_does_not_create_duplicates(): void
    {
        $this->seed(AchievementSeeder::class);
        Event::fake([AchievementUnlocked::class]);
        $user = User::factory()->create();
        Order::factory()->for($user)->create();

        $evaluator = app(AchievementEvaluator::class);
        $evaluator->evaluate($user);
        $evaluator->evaluate($user);

        $this->assertDatabaseCount('user_achievements', 1);
        Event::assertDispatchedTimes(AchievementUnlocked::class, 1);
    }

    private function achievementId(string $name): int
    {
        return (int) Achievement::query()
            ->where('name', $name)
            ->value('id');
    }
}
