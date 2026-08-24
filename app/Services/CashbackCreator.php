<?php

namespace App\Services;

use App\Enums\CashbackStatus;
use App\Enums\PaymentAccountStatus;
use App\Events\CashbackCreated;
use App\Models\Badge;
use App\Models\Cashback;
use App\Models\PaymentAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CashbackCreator
{
    public const AMOUNT = 30_000;

    public const CURRENCY = 'NGN';

    public const PROVIDER = 'paystack';

    public function __construct(private readonly PaymentAccountService $paymentAccounts) {}

    public function createForBadge(User $user, string $badgeName): Cashback
    {
        $this->paymentAccounts->assignDefault($user);

        $result = DB::transaction(function () use ($user, $badgeName): array {
            $badge = Badge::query()->where('name', $badgeName)->firstOrFail();
            $existing = Cashback::query()
                ->where('user_id', $user->id)
                ->where('badge_id', $badge->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return [
                    'cashback' => $existing,
                    'created' => false,
                ];
            }

            $paymentAccount = PaymentAccount::query()
                ->where('user_id', $user->id)
                ->where('provider', self::PROVIDER)
                ->where('status', PaymentAccountStatus::ACTIVE->value)
                ->first();

            $cashback = Cashback::create([
                'user_id' => $user->id,
                'badge_id' => $badge->id,
                'payment_account_id' => $paymentAccount?->id,
                'amount' => self::AMOUNT,
                'currency' => self::CURRENCY,
                'status' => CashbackStatus::PENDING,
                'description' => "{$badge->name} badge cashback",
                'metadata' => [
                    'badge_name' => $badge->name,
                ],
            ]);

            CashbackCreated::dispatch($cashback);

            return [
                'cashback' => $cashback,
                'created' => true,
            ];
        });

        /** @var Cashback $cashback */
        $cashback = $result['cashback'];

        if ($result['created']) {
            Log::info('Cashback created', [
                'cashback_id' => $cashback->id,
                'badge_id' => $cashback->badge_id,
                'user_id' => $cashback->user_id,
                'amount' => $cashback->amount,
                'currency' => $cashback->currency,
                'payment_account_id' => $cashback->payment_account_id,
            ]);
        }

        return $cashback;
    }
}
