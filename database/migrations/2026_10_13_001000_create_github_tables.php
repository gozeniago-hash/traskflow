<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->text('github_token')->nullable(); // encrypted
            $table->string('github_login', 100)->nullable();
        });

        Schema::create('github_repositories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('full_name', 200); // owner/repo
            $table->text('webhook_secret'); // encrypted
            $table->unsignedBigInteger('webhook_id')->nullable(); // set when TaskFlow created the hook itself
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_webhook_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamps();
            $table->unique(['workspace_id', 'full_name']);
        });

        Schema::create('pull_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('github_repository_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('title', 500);
            $table->string('url', 500);
            $table->string('state', 10); // open | closed
            $table->boolean('draft')->default(false);
            $table->timestamp('merged_at')->nullable();
            $table->string('head_ref', 255)->nullable();
            $table->string('author_login', 100)->nullable();
            $table->string('author_avatar', 500)->nullable();
            $table->timestamp('gh_updated_at')->nullable();
            $table->timestamps();
            $table->unique(['github_repository_id', 'number']);
        });

        Schema::create('pull_request_task', function (Blueprint $table) {
            $table->foreignId('pull_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->primary(['pull_request_id', 'task_id']);
            $table->index('task_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pull_request_task');
        Schema::dropIfExists('pull_requests');
        Schema::dropIfExists('github_repositories');
        Schema::table('workspaces', fn (Blueprint $table) => $table->dropColumn(['github_token', 'github_login']));
    }
};
