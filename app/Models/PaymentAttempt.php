<?php

namespace App\Models;

use App\Enums\PaymentAttemptStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['cashback_id', 'payment_account_id', 'provider', 'provider_transfer_reference', 'status', 'amount', 'currency', 'request_payload', 'response_payload', 'failure_code', 'failure_message', 'attempted_at', 'completed_at'])]
class PaymentAttempt extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => PaymentAttemptStatus::class,
            'amount' => 'integer',
            'request_payload' => 'array',
            'response_payload' => 'array',
            'attempted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function cashback(): BelongsTo
    {
        return $this->belongsTo(Cashback::class);
    }

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class);
    }
}
