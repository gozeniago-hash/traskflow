<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Activities now cover the whole project (sections, docs, files, updates…), not only tasks.
        Schema::table('activities', function (Blueprint $table): void {
            $table->string('kind', 20)->default('task')->after('user_id');
            $table->unsignedBigInteger('subject_id')->nullable()->after('kind');
            $table->index(['project_id', 'created_at']);
        });

        // Everything before this moment counts as read.
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('notifications_read_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('notifications_read_at'));
        Schema::table('activities', function (Blueprint $table): void {
            $table->dropIndex(['project_id', 'created_at']);
            $table->dropColumn(['kind', 'subject_id']);
        });
    }
};
