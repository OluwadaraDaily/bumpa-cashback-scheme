<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('event_type', 64);
            $table->string('aggregate_type');
            $table->unsignedBigInteger('aggregate_id');
            $table->string('deduplication_key')->unique();
            $table->json('payload');
            $table->timestamp('available_at')->useCurrent();
            $table->timestamp('processing_at')->nullable();
            $table->uuid('processing_token')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['published_at', 'available_at']);
            $table->index('processing_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_messages');
    }
};
