<?php

namespace App\Models;

use App\Enums\PaymentAccountStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'provider', 'recipient_reference', 'currency', 'status', 'metadata'])]
class PaymentAccount extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => PaymentAccountStatus::class,
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cashbacks(): HasMany
    {
        return $this->hasMany(Cashback::class);
    }

    public function paymentAttempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }
}
