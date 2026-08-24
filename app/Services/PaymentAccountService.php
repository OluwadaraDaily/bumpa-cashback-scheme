<?php

namespace App\Services;

use App\Enums\PaymentAccountStatus;
use App\Models\PaymentAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentAccountService
{
    private const SUPPORTED_PROVIDERS = ['paystack'];

    /**
     * @param  array{recipient_reference: string, currency?: string}  $attributes
     */
    public function upsert(User $user, string $provider, array $attributes): PaymentAccount
    {
        $this->ensureProviderIsSupported($provider);

        return DB::transaction(function () use ($user, $provider, $attributes): PaymentAccount {
            User::query()->lockForUpdate()->findOrFail($user->id);

            return PaymentAccount::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'provider' => $provider,
                ],
                [
                    'recipient_reference' => $attributes['recipient_reference'],
                    'currency' => $attributes['currency'] ?? 'NGN',
                    'status' => PaymentAccountStatus::ACTIVE,
                ],
            );
        });
    }

    public function deactivate(User $user, string $provider): void
    {
        $this->ensureProviderIsSupported($provider);

        DB::transaction(function () use ($user, $provider): void {
            $account = PaymentAccount::query()
                ->where('user_id', $user->id)
                ->where('provider', $provider)
                ->lockForUpdate()
                ->firstOrFail();

            $account->update(['status' => PaymentAccountStatus::INACTIVE]);
        });
    }

    private function ensureProviderIsSupported(string $provider): void
    {
        if (! in_array($provider, self::SUPPORTED_PROVIDERS, true)) {
            throw ValidationException::withMessages([
                'provider' => ['The selected payment provider is not supported.'],
            ]);
        }
    }
}
