<?php

namespace App\Models;

use App\Enums\CashbackStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'badge_id', 'payment_account_id', 'amount', 'currency', 'status', 'description', 'metadata', 'paid_at'])]
class Cashback extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => CashbackStatus::class,
            'metadata' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function badge(): BelongsTo
    {
        return $this->belongsTo(Badge::class);
    }

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class);
    }

    public function paymentAttempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }
}
