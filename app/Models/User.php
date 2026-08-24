<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'username', 'email', 'password', 'is_admin'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function achievements(): BelongsToMany
    {
        return $this->belongsToMany(Achievement::class, 'user_achievements')
            ->withPivot('unlocked_at');
    }

    public function userAchievements(): HasMany
    {
        return $this->hasMany(UserAchievement::class);
    }

    public function badges(): BelongsToMany
    {
        return $this->belongsToMany(Badge::class, 'user_badges')
            ->withPivot('unlocked_at');
    }

    public function userBadges(): HasMany
    {
        return $this->hasMany(UserBadge::class);
    }

    public function paymentAccounts(): HasMany
    {
        return $this->hasMany(PaymentAccount::class);
    }

    public function cashbacks(): HasMany
    {
        return $this->hasMany(Cashback::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }
}
