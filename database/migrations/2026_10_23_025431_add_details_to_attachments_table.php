<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Lets people give a file a title, a description, a group and extra label/value details. */
    public function up(): void
    {
        Schema::table('attachments', function (Blueprint $table): void {
            $table->string('title', 150)->nullable()->after('original_name');
            $table->text('description')->nullable()->after('title');
            $table->string('group', 60)->nullable()->after('description');
            $table->json('meta')->nullable()->after('group');
            $table->index(['project_id', 'group']);
        });
    }

    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table): void {
            $table->dropIndex(['project_id', 'group']);
            $table->dropColumn(['title', 'description', 'group', 'meta']);
        });
    }
};
