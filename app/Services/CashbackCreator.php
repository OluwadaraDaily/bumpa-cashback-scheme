<?php

namespace App\Services;

use App\Enums\CashbackStatus;
use App\Enums\PaymentAccountStatus;
use App\Models\Badge;
use App\Models\Cashback;
use App\Models\PaymentAccount;
use App\Models\User;
use App\Services\Outbox\OutboxRecorder;
use App\Services\Outbox\OutboxRelay;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CashbackCreator
{
    public const AMOUNT = 30_000;

    public const CURRENCY = 'NGN';

    public const PROVIDER = 'paystack';

    public function __construct(
        private readonly PaymentAccountService $paymentAccounts,
        private readonly OutboxRecorder $outbox,
        private readonly OutboxRelay $outboxRelay,
    ) {}

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
                    'outbox_message' => $this->outbox->recordCashbackCreated($existing),
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

            return [
                'cashback' => $cashback,
                'created' => true,
                'outbox_message' => $this->outbox->recordCashbackCreated($cashback),
            ];
        });

        /** @var Cashback $cashback */
        $cashback = $result['cashback'];
        $this->outboxRelay->publishSafely($result['outbox_message']);

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
