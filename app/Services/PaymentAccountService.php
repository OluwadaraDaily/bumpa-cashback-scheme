<?php

namespace App\Services;

use App\Enums\CashbackStatus;
use App\Enums\PaymentAccountStatus;
use App\Events\CashbackTransferRequested;
use App\Models\Cashback;
use App\Models\PaymentAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

        $account = DB::transaction(function () use ($user, $provider, $attributes): PaymentAccount {
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
                    'metadata' => ['is_default' => false],
                ],
            );
        });

        $this->resumeEligibleCashbacks($user, $account);

        return $account;
    }

    public function assignDefault(User $user): ?PaymentAccount
    {
        $recipientReference = trim((string) config('services.paystack.default_recipient'));

        if ($recipientReference === '') {
            return null;
        }

        $result = DB::transaction(function () use ($user, $recipientReference): array {
            User::query()->lockForUpdate()->findOrFail($user->id);

            $account = PaymentAccount::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'provider' => 'paystack',
                ],
                [
                    'recipient_reference' => $recipientReference,
                    'currency' => 'NGN',
                    'status' => PaymentAccountStatus::ACTIVE,
                    'metadata' => ['is_default' => true],
                ],
            );

            return [
                'account' => $account,
                'created' => $account->wasRecentlyCreated,
            ];
        });

        if ($result['created']) {
            $this->resumeEligibleCashbacks($user, $result['account']);
        }

        return $result['account'];
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

    private function resumeEligibleCashbacks(User $user, PaymentAccount $account): void
    {
        /** @var Collection<int, Cashback> $cashbacks */
        $cashbacks = DB::transaction(function () use ($user, $account): Collection {
            User::query()->lockForUpdate()->findOrFail($user->id);

            $cashbacks = Cashback::query()
                ->where('user_id', $user->id)
                ->whereIn('status', [
                    CashbackStatus::PENDING->value,
                    CashbackStatus::FAILED->value,
                ])
                ->lockForUpdate()
                ->get();

            foreach ($cashbacks as $cashback) {
                $cashback->update([
                    'payment_account_id' => $account->id,
                    'status' => CashbackStatus::PENDING,
                ]);
            }

            return $cashbacks;
        });

        foreach ($cashbacks as $cashback) {
            CashbackTransferRequested::dispatch($cashback);
        }

        if ($cashbacks->isNotEmpty()) {
            Log::info('Pending cashbacks queued after payment account activation', [
                'payment_account_id' => $account->id,
                'user_id' => $user->id,
                'cashback_count' => $cashbacks->count(),
            ]);
        }
    }
}
