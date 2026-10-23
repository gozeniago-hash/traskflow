<?php

namespace App\Providers;

use App\Http\Middleware\EnsureAccountSecure;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureClient;
use App\Http\Middleware\EnsureMember;
use App\Http\Middleware\EnsureWorkspace;
use App\Models\Activity;
use App\Models\Attachment;
use App\Models\Comment;
use App\Models\Project;
use App\Models\ProjectDoc;
use App\Models\ProjectField;
use App\Models\ProjectResource;
use App\Models\Section;
use App\Models\StatusUpdate;
use App\Models\Task;
use App\Models\TaskFieldValue;
use App\Models\TimeEntry;
use App\Models\Workspace;
use App\Rules\NotCommonPassword;
use App\Support\ProjectLog;
use App\Support\Realtime;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
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
        // Minimum 8 characters, never a well-known password; in production also refuse
        // passwords found in public breaches (checked anonymously against Have I Been Pwned).
        Password::defaults(fn () => tap(
            Password::min(8)->rules([new NotCommonPassword]),
            fn ($rule) => $this->app->environment('production') ? $rule->uncompromised() : $rule,
        ));

        $this->registerRealtimeHooks();
        ProjectLog::register();
        $this->registerRateLimits();

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Re-run our access middleware on every Livewire update request, not just the first page load.
        Livewire::addPersistentMiddleware([
            EnsureAccountSecure::class,
            EnsureWorkspace::class,
            EnsureMember::class,
            EnsureClient::class,
            EnsureAdmin::class,
        ]);
    }

    /**
     * Request limits. One person (or one address, when signed out) can't use the whole server,
     * and sign-up, password reset and invitations can't be used to flood mailboxes.
     */
    protected function registerRateLimits(): void
    {
        $tooMany = fn (string $what) => fn ($request, array $headers) => $request->expectsJson()
            ? response()->json(['message' => $what], 429, $headers)
            : back()->withInput($request->except('password', 'password_confirmation'))->withErrors(['email' => $what])->withHeaders($headers);

        RateLimiter::for('web', fn (Request $request) => Limit::perMinute(config('taskflow.requests_per_minute'))
            ->by($request->user()?->id ? 'user:'.$request->user()->id : 'ip:'.$request->ip()));

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(20)->by('login:'.$request->ip())
            ->response($tooMany('Too many sign-in attempts from your network. Wait a minute and try again.')));

        RateLimiter::for('register', fn (Request $request) => [
            Limit::perMinute(5)->by('register-min:'.$request->ip())->response($tooMany('Too many sign-ups from your network. Wait a minute and try again.')),
            Limit::perDay(20)->by('register-day:'.$request->ip())->response($tooMany('Too many sign-ups from your network today. Try again tomorrow.')),
        ]);

        RateLimiter::for('password-reset', fn (Request $request) => [
            Limit::perMinute(3)->by('reset-ip:'.$request->ip())->response($tooMany('Too many reset requests. Wait a minute and try again.')),
            Limit::perHour(5)->by('reset-email:'.strtolower((string) $request->input('email')))->response($tooMany('Too many reset requests for this email. Try again later.')),
        ]);

        RateLimiter::for('invitation-accept', fn (Request $request) => Limit::perMinute(10)->by('accept:'.$request->ip())
            ->response($tooMany('Too many attempts. Wait a minute and try again.')));
    }

    /** Any change to project data tells everyone viewing that project to refresh. */
    protected function registerRealtimeHooks(): void
    {
        $byProject = [
            Task::class => fn ($m) => [$m->project_id, $m->parent_id ?: $m->id],
            Section::class => fn ($m) => [$m->project_id, null],
            Attachment::class => fn ($m) => [$m->project_id, $m->task_id],
            TimeEntry::class => fn ($m) => [$m->project_id, $m->task_id],
            ProjectDoc::class => fn ($m) => [$m->project_id, null],
            StatusUpdate::class => fn ($m) => [$m->project_id, null],
            ProjectField::class => fn ($m) => [$m->project_id, null],
            ProjectResource::class => fn ($m) => [$m->project_id, null],
            Activity::class => fn ($m) => [$m->project_id, $m->task_id],
            Project::class => fn ($m) => [$m->id, null],
            Comment::class => fn ($m) => [Task::whereKey($m->task_id)->value('project_id'), $m->task_id],
            TaskFieldValue::class => fn ($m) => [Task::whereKey($m->task_id)->value('project_id'), $m->task_id],
        ];

        foreach ($byProject as $model => $resolve) {
            $handler = function ($m) use ($resolve): void {
                [$projectId, $taskId] = $resolve($m);
                Realtime::touch($projectId ? (int) $projectId : null, $taskId ? (int) $taskId : null);
            };
            $model::saved($handler);
            $model::deleted($handler);
        }

        // Workspace renamed, or a project removed: teammates' sidebars refresh.
        Workspace::saved(fn ($w) => Realtime::touchWorkspace((int) $w->id));
        Project::deleted(fn ($p) => Realtime::touchWorkspace((int) $p->workspace_id));
    }
}
