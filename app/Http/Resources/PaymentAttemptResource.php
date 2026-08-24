<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentAttemptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'status' => $this->status->value,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'provider_transfer_reference' => $this->provider_transfer_reference,
            'failure_code' => $this->failure_code,
            'failure_message' => $this->failure_message,
            'attempted_at' => $this->attempted_at,
            'completed_at' => $this->completed_at,
        ];
    }
}
