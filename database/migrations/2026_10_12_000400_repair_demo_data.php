<?php

use Database\Seeders\DemoSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The first demo-data load stopped half-way (users created, memberships missing).
 * If the database is in exactly that state, clear the partial demo rows and load it again.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! config('taskflow.seed_demo_data') || DB::table('workspace_user')->exists()) {
            return;
        }

        DB::table('clients')->delete();
        DB::table('workspaces')->delete();
        DB::table('users')->whereIn('email', [
            'admin@taskflow.test', 'maya@taskflow.test', 'leo@taskflow.test', 'jo@taskflow.test', 'client@brightside.test',
        ])->delete();

        if (DB::table('users')->exists()) {
            return; // Someone registered for real; don't add demo data on top.
        }

        DB::transaction(fn () => (new DemoSeeder)->run());
    }

    public function down(): void
    {
    }
};
