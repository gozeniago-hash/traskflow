<?php

namespace App\Support;

use App\Events\ProjectChanged;
use App\Models\Project;
use Illuminate\Support\Facades\Log;

/**
 * Collects which projects changed during a request and broadcasts one
 * "changed" event per project after the response has been sent.
 */
class Realtime
{
    /** @var array<int, array<int, true>> projectId => [taskId => true] */
    protected static array $pending = [];

    protected static bool $registered = false;

    protected static bool $muted = false;

    public static function touch(?int $projectId, ?int $taskId = null): void
    {
        if (! $projectId || static::$muted || ! static::enabled()) {
            return;
        }

        static::$pending[$projectId] ??= [];
        if ($taskId) {
            static::$pending[$projectId][$taskId] = true;
        }

        if (! static::$registered) {
            static::$registered = true;
            app()->terminating(fn () => static::flush());
        }
    }

    /** Run something without broadcasting (seeders, migrations). */
    public static function muted(callable $callback): mixed
    {
        $previous = static::$muted;
        static::$muted = true;
        try {
            return $callback();
        } finally {
            static::$muted = $previous;
        }
    }

    public static function enabled(): bool
    {
        return config('broadcasting.default') === 'reverb' && filled(config('broadcasting.connections.reverb.key'));
    }

    public static function flush(): void
    {
        $pending = static::$pending;
        static::$pending = [];
        static::$registered = false;
        if (! $pending) {
            return;
        }

        $workspaces = Project::query()->whereIn('id', array_keys($pending))->pluck('workspace_id', 'id');
        $actor = auth()->user()?->name;

        foreach ($pending as $projectId => $taskIds) {
            if (! isset($workspaces[$projectId])) {
                continue; // project was deleted
            }
            try {
                broadcast(new ProjectChanged($projectId, (int) $workspaces[$projectId], array_keys($taskIds), $actor))->toOthers();
            } catch (\Throwable $e) {
                // Live updates are a nice-to-have; never fail a request because the socket server is down.
                Log::warning('Realtime broadcast failed: '.$e->getMessage());
            }
        }
    }
}
