<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 150);
            $table->string('url', 2000)->nullable(); // links
            $table->foreignId('attachment_id')->nullable()->constrained()->cascadeOnDelete(); // files
            $table->boolean('client_visible')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['project_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_resources');
    }
};
