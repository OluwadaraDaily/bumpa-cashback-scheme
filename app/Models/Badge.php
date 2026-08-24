<?php

namespace App\Models;

use Database\Factories\BadgeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'sort_order'])]
class Badge extends Model
{
    /** @use HasFactory<BadgeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function achievements(): BelongsToMany
    {
        return $this->belongsToMany(Achievement::class, 'badge_achievements');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_badges')
            ->withPivot('unlocked_at');
    }

    public function userBadges(): HasMany
    {
        return $this->hasMany(UserBadge::class);
    }

    public function cashbacks(): HasMany
    {
        return $this->hasMany(Cashback::class);
    }
}
