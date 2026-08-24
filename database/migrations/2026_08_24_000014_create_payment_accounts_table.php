<?php

use App\Enums\PaymentAccountStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('recipient_reference', 128);
            $table->char('currency', 3)->default('NGN');
            $table->string('status', 32)->default(PaymentAccountStatus::ACTIVE->value);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'provider']);
            $table->index(['provider', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_accounts');
    }
};
