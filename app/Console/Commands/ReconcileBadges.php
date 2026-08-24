<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\BadgeEvaluator;
use Illuminate\Console\Command;

class ReconcileBadges extends Command
{
    protected $signature = 'badges:reconcile {--user= : Reconcile one user by ID}';

    protected $description = 'Re-evaluate badges for users with unlocked achievements';

    public function handle(BadgeEvaluator $evaluator): int
    {
        $userId = $this->option('user');
        $users = User::query()
            ->whereHas('userAchievements')
            ->when($userId, fn ($query) => $query->whereKey($userId))
            ->get();

        foreach ($users as $user) {
            $evaluator->evaluate($user);
            $this->info("Reconciled badges for user {$user->id}.");
        }

        $this->info("Reconciled {$users->count()} user(s).");

        return self::SUCCESS;
    }
}
