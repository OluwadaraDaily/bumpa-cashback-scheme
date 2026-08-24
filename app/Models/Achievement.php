<?php

namespace App\Models;

use App\Enums\AchievementMetric;
use Database\Factories\AchievementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'achievement_group', 'metric', 'threshold', 'sort_order'])]
class Achievement extends Model
{
    /** @use HasFactory<AchievementFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'metric' => AchievementMetric::class,
            'threshold' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_achievements')
            ->withPivot('unlocked_at');
    }

    public function userAchievements(): HasMany
    {
        return $this->hasMany(UserAchievement::class);
    }

    public function badges(): BelongsToMany
    {
        return $this->belongsToMany(Badge::class, 'badge_achievements');
    }
}
