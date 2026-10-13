<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Project IDs (keys like "BWR") and per-project ticket numbers (BWR-1, BWR-2, …).
 * Existing projects and tasks are backfilled in creation order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('key', 10)->nullable()->after('workspace_id');
            $table->unsignedInteger('task_counter')->default(0)->after('key');
        });
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedInteger('number')->nullable()->after('project_id');
        });

        $used = [];
        foreach (DB::table('projects')->orderBy('id')->get(['id', 'workspace_id', 'name']) as $p) {
            $key = self::makeKey($p->name, $used[$p->workspace_id] ?? []);
            $used[$p->workspace_id][] = $key;

            $n = 0;
            foreach (DB::table('tasks')->where('project_id', $p->id)->orderBy('id')->pluck('id') as $taskId) {
                DB::table('tasks')->where('id', $taskId)->update(['number' => ++$n]);
            }
            DB::table('projects')->where('id', $p->id)->update(['key' => $key, 'task_counter' => $n]);
        }

        Schema::table('projects', function (Blueprint $table) {
            $table->unique(['workspace_id', 'key']);
        });
        Schema::table('tasks', function (Blueprint $table) {
            $table->unique(['project_id', 'number']);
        });
    }

    /** Same rules as Project::generateKey(), duplicated so the migration never depends on app code. */
    protected static function makeKey(string $name, array $taken): string
    {
        $words = preg_split('/[^A-Za-z0-9]+/', strtoupper($name), -1, PREG_SPLIT_NO_EMPTY) ?: ['PRJ'];
        $base = count($words) === 1 ? substr($words[0], 0, 3) : implode('', array_map(fn ($w) => $w[0], array_slice($words, 0, 4)));
        $base = substr(preg_replace('/^[0-9]+/', '', $base) ?: 'PRJ', 0, 6);
        $base = strlen($base) < 2 ? str_pad($base, 2, 'X') : $base;

        $key = $base;
        for ($i = 2; in_array($key, $taken, true); $i++) {
            $key = $base.$i;
        }

        return $key;
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropUnique(['project_id', 'number']);
            $table->dropColumn('number');
        });
        Schema::table('projects', function (Blueprint $table) {
            $table->dropUnique(['workspace_id', 'key']);
            $table->dropColumn(['key', 'task_counter']);
        });
    }
};
