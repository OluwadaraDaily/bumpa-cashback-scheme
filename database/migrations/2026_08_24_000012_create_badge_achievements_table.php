<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('badge_achievements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('badge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('achievement_id')->constrained()->cascadeOnDelete();

            $table->unique(['badge_id', 'achievement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badge_achievements');
    }
};
