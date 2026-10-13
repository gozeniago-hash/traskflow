<?php

namespace App\Providers;

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureClient;
use App\Http\Middleware\EnsureMember;
use App\Http\Middleware\EnsureWorkspace;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Password::defaults(fn () => Password::min(8));

        $this->registerRealtimeHooks();

        if ($this->app->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // Re-run our access middleware on every Livewire update request, not just the first page load.
        Livewire::addPersistentMiddleware([
            EnsureWorkspace::class,
            EnsureMember::class,
            EnsureClient::class,
            EnsureAdmin::class,
        ]);
    }

    /** Any change to project data tells everyone viewing that project to refresh. */
    protected function registerRealtimeHooks(): void
    {
        $byProject = [
            \App\Models\Task::class => fn ($m) => [$m->project_id, $m->parent_id ?: $m->id],
            \App\Models\Section::class => fn ($m) => [$m->project_id, null],
            \App\Models\Attachment::class => fn ($m) => [$m->project_id, $m->task_id],
            \App\Models\TimeEntry::class => fn ($m) => [$m->project_id, $m->task_id],
            \App\Models\ProjectDoc::class => fn ($m) => [$m->project_id, null],
            \App\Models\StatusUpdate::class => fn ($m) => [$m->project_id, null],
            \App\Models\ProjectField::class => fn ($m) => [$m->project_id, null],
            \App\Models\ProjectResource::class => fn ($m) => [$m->project_id, null],
            \App\Models\Activity::class => fn ($m) => [$m->project_id, $m->task_id],
            \App\Models\Project::class => fn ($m) => [$m->id, null],
            \App\Models\Comment::class => fn ($m) => [\App\Models\Task::whereKey($m->task_id)->value('project_id'), $m->task_id],
            \App\Models\TaskFieldValue::class => fn ($m) => [\App\Models\Task::whereKey($m->task_id)->value('project_id'), $m->task_id],
        ];

        foreach ($byProject as $model => $resolve) {
            $handler = function ($m) use ($resolve) {
                [$projectId, $taskId] = $resolve($m);
                \App\Support\Realtime::touch($projectId ? (int) $projectId : null, $taskId ? (int) $taskId : null);
            };
            $model::saved($handler);
            $model::deleted($handler);
        }

        // Workspace renamed, or a project removed: teammates' sidebars refresh.
        \App\Models\Workspace::saved(fn ($w) => \App\Support\Realtime::touchWorkspace((int) $w->id));
        \App\Models\Project::deleted(fn ($p) => \App\Support\Realtime::touchWorkspace((int) $p->workspace_id));
    }
}
