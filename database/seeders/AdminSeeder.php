<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Creates the first admin (workspace owner) from the ADMIN_* env vars.
 * Runs only once: skipped when user id 1 already exists (safe to call on every deploy).
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        if (User::whereKey(1)->exists()) {
            $this->command->info('Admin already exists — skipping.');

            return;
        }

        if (blank(config('app.admin.email')) || blank(config('app.admin.password'))) {
            $this->command->warn('Set ADMIN_EMAIL and ADMIN_PASSWORD to create the admin — skipping.');

            return;
        }

        $email = Config::string('app.admin.email');

        DB::transaction(function () use ($email): void {
            $user = User::create([
                'name' => Config::string('app.admin.name'),
                'email' => $email,
                'password' => Config::string('app.admin.password'),
            ]);

            $workspace = Workspace::create(['name' => Config::string('app.admin.workspace'), 'owner_id' => $user->id]);
            $workspace->users()->attach($user->id, ['role' => 'owner']);
            $workspace->teams()->create(['name' => 'General'])->users()->attach($user->id);
            $user->forceFill(['current_workspace_id' => $workspace->id, 'email_verified_at' => now()])->save();
        });

        $this->command->info('Admin '.$email.' created.');
    }
}
