<?php

namespace App\Support;

use App\Events\ProjectChanged;
use App\Events\WorkspaceChanged;
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

    /** @var array<int, true> workspaces whose own details (name, projects list) changed */
    protected static array $workspaces = [];

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

        static::register();
    }

    /** The workspace itself changed (e.g. renamed, a project removed): refresh sidebars. */
    public static function touchWorkspace(?int $workspaceId): void
    {
        if (! $workspaceId || static::$muted || ! static::enabled()) {
            return;
        }
        static::$workspaces[$workspaceId] = true;
        static::register();
    }

    protected static function register(): void
    {
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
        $workspaceOnly = static::$workspaces;
        static::$pending = [];
        static::$workspaces = [];
        static::$registered = false;
        $actor = auth()->user()?->name;

        $workspaces = $pending ? Project::query()->whereIn('id', array_keys($pending))->pluck('workspace_id', 'id') : collect();
        foreach ($workspaces as $workspaceId) {
            unset($workspaceOnly[(int) $workspaceId]); // already reached by that project's event
        }
        foreach (array_keys($workspaceOnly) as $workspaceId) {
            static::send(new WorkspaceChanged((int) $workspaceId, $actor));
        }
        if (! $pending) {
            return;
        }

        foreach ($pending as $projectId => $taskIds) {
            if (! isset($workspaces[$projectId])) {
                continue; // project was deleted
            }
            static::send(new ProjectChanged($projectId, (int) $workspaces[$projectId], array_keys($taskIds), $actor));
        }
    }

    protected static function send(object $event): void
    {
        try {
            broadcast($event)->toOthers();
        } catch (\Throwable $e) {
            // Live updates are a nice-to-have; never fail a request because the socket server is down.
            Log::warning('Realtime broadcast failed: '.$e->getMessage());
        }
    }
}
