<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\Badge;
use App\Services\AchievementStatusService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AchievementController extends Controller
{
    public function index(Request $request, AchievementStatusService $status): View
    {
        $user = $request->user();
        $achievements = Achievement::query()
            ->orderBy('achievement_group')
            ->orderBy('sort_order')
            ->get();
        $unlockedAchievements = $user->achievements()
            ->orderBy('achievement_group')
            ->orderBy('sort_order')
            ->get()
            ->keyBy('id');
        $badges = Badge::query()
            ->with('achievements')
            ->orderBy('sort_order')
            ->get();
        $unlockedBadges = $user->badges()
            ->orderBy('sort_order')
            ->get()
            ->keyBy('id');

        return view('achievements.index', [
            'achievements' => $achievements,
            'badges' => $badges,
            'unlockedAchievements' => $unlockedAchievements,
            'unlockedBadges' => $unlockedBadges,
            'progress' => $status->getFor($user),
        ]);
    }
}
