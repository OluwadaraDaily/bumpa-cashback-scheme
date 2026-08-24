<?php

namespace App\Providers;

use App\Contracts\Payments\PaymentProvider;
use App\Services\Payments\FakePaymentProvider;
use App\Services\Payments\PaystackPaymentProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentProvider::class, function (): PaymentProvider {
            return config('services.payment.provider') === 'paystack'
                ? new PaystackPaymentProvider
                : new FakePaymentProvider;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request): Limit {
            $email = Str::lower((string) $request->input('email'));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });
    }
}
