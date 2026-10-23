<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_fields', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('type', 20); // text | textarea | number | currency | date | select | multiselect | checkbox | url | person
            $table->json('options')->nullable(); // select/multiselect: [{id, label, color}]
            $table->string('description', 200)->nullable();
            $table->boolean('required')->default(false);
            $table->boolean('show_on_card')->default(true);
            $table->boolean('client_visible')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['project_id', 'position']);
        });

        Schema::create('task_field_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('field_id')->constrained('project_fields')->cascadeOnDelete();
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unique(['task_id', 'field_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_field_values');
        Schema::dropIfExists('project_fields');
    }
};
