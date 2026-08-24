<?php

use App\Enums\CashbackStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashbacks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('badge_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_account_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3)->default('NGN');
            $table->string('status', 32)->default(CashbackStatus::PENDING->value);
            $table->string('description');
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'badge_id']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashbacks');
    }
};
