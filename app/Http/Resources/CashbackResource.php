<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashbackResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'description' => $this->description,
            'badge' => [
                'id' => $this->badge->id,
                'name' => $this->badge->name,
                'description' => $this->badge->description,
            ],
            'payment_attempts' => PaymentAttemptResource::collection(
                $this->whenLoaded('paymentAttempts')
            ),
            'paid_at' => $this->paid_at,
            'created_at' => $this->created_at,
        ];
    }
}
