<?php

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Loads the demo workspace on a fresh database when SEED_DEMO_DATA=true.
 * Dated 2099 so it always runs last, after every table and column exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! config('taskflow.seed_demo_data') || User::query()->exists()) {
            return;
        }

        \App\Support\Realtime::muted(fn () => DB::transaction(fn () => (new DemoSeeder)->run()));
    }

    public function down(): void
    {
    }
};
