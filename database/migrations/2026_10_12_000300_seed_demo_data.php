<?php

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Loads the demo workspace when SEED_DEMO_DATA=true and the database has no users yet.
 * Runs as a migration so it happens on hosts that only run `php artisan migrate`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! config('taskflow.seed_demo_data') || User::query()->exists()) {
            return;
        }

        \Illuminate\Support\Facades\DB::transaction(fn () => (new DemoSeeder)->run());
    }

    public function down(): void
    {
        // Demo data is left in place.
    }
};
