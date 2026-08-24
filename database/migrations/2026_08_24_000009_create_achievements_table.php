<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('achievements', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->text('description');
            $table->string('achievement_group', 64);
            $table->string('metric', 64);
            $table->unsignedBigInteger('threshold');
            $table->unsignedInteger('sort_order');
            $table->timestamps();

            $table->unique(['achievement_group', 'sort_order']);
            $table->index(['metric', 'threshold']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('achievements');
    }
};
