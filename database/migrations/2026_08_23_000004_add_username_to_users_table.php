<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function ($table): void {
            $table->string('username')->nullable()->unique()->after('id');
        });

        DB::table('users')
            ->select(['id', 'name'])
            ->orderBy('id')
            ->each(function (object $user): void {
                $base = Str::slug((string) $user->name) ?: "user-{$user->id}";
                $username = $base;
                $suffix = 1;

                while (DB::table('users')->where('username', $username)->exists()) {
                    $username = "{$base}-{$suffix}";
                    $suffix++;
                }

                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['username' => $username]);
            });

        Schema::table('users', function ($table): void {
            $table->string('username')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function ($table): void {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
