<?php

use App\Enums\PaymentAttemptStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cashback_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 32);
            $table->string('provider_transfer_reference', 128)->nullable()->unique();
            $table->string('status', 32)->default(PaymentAttemptStatus::PENDING->value);
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3)->default('NGN');
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['cashback_id', 'status']);
            $table->index(['provider', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
    }
};
