<?php

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Loads the demo workspace on a fresh database when SEED_DEMO_DATA=true.
 * Always the last migration, so every table and column already exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! config('taskflow.seed_demo_data') || User::query()->exists()) {
            return;
        }

        DB::transaction(fn () => (new DemoSeeder)->run());
    }

    public function down(): void
    {
    }
};
