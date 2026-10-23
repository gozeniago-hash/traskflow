<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Counts the saves of each doc, so an editor can tell that someone else saved since it opened. */
    public function up(): void
    {
        Schema::table('project_docs', function (Blueprint $table): void {
            $table->unsignedInteger('version')->default(0)->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('project_docs', function (Blueprint $table): void {
            $table->dropColumn('version');
        });
    }
};
