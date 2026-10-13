<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_docs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->longText('content')->nullable();
            $table->boolean('client_visible')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['project_id', 'position']);
        });

        Schema::create('doc_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doc_id')->constrained('project_docs')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->longText('content')->nullable();
            $table->timestamps();
        });

        Schema::table('attachments', function (Blueprint $table) {
            $table->foreignId('doc_id')->nullable()->after('comment_id')->constrained('project_docs')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('doc_id');
        });
        Schema::dropIfExists('doc_revisions');
        Schema::dropIfExists('project_docs');
    }
};
