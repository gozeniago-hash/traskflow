<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/** Runs the demo seeder only if the database has no users yet (safe to call on every deploy). */
class DemoSeederOnce extends Seeder
{
    public function run(): void
    {
        if (User::query()->exists()) {
            $this->command?->info('Users already exist — skipping demo data.');

            return;
        }

        $this->call(DemoSeeder::class);
    }
}
