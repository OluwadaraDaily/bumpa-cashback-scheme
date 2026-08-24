<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePaymentAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'recipient_reference' => ['required', 'string', 'max:128', 'regex:/^\S+$/'],
            'currency' => ['sometimes', 'string', 'size:3', 'uppercase', 'in:NGN'],
        ];
    }
}
