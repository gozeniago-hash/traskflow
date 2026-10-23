<?php

use Database\Seeders\DemoSeeder;
use Illuminate\Database\Migrations\Migration;

/** Adds example custom fields to an existing demo workspace (fresh installs get them from DemoSeeder). */
return new class extends Migration
{
    public function up(): void
    {
        if (config('taskflow.seed_demo_data')) {
            (new DemoSeeder)->seedFields();
        }
    }

    public function down(): void {}
};
