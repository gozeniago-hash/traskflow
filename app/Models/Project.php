<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Project extends Model
{
    public const STATUSES = [
        'on_track' => 'On track',
        'at_risk' => 'At risk',
        'off_track' => 'Off track',
        'on_hold' => 'On hold',
        'complete' => 'Complete',
    ];

    /** Tailwind classes per status — kept as full strings so Tailwind can see them. */
    public const STATUS_CLASSES = [
        'on_track' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200',
        'at_risk' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-200',
        'off_track' => 'bg-red-50 text-red-700 ring-1 ring-red-200',
        'on_hold' => 'bg-sky-50 text-sky-700 ring-1 ring-sky-200',
        'complete' => 'bg-indigo-50 text-indigo-700 ring-1 ring-indigo-200',
    ];

    public const STATUS_DOTS = [
        'on_track' => 'bg-emerald-500',
        'at_risk' => 'bg-amber-500',
        'off_track' => 'bg-red-500',
        'on_hold' => 'bg-sky-500',
        'complete' => 'bg-indigo-500',
    ];

    public const PRIVACY = [
        'workspace' => 'Everyone in the workspace',
        'team' => 'Team members',
        'private' => 'Only project members',
    ];

    public const COLORS = ['#f06a5a', '#fd9a00', '#eec300', '#62d26f', '#37c5ab', '#20aaea', '#4573d2', '#8d84e8', '#aa62e3', '#e362e3', '#ea4e9d', '#6d6e6f'];

    public const VIEWS = ['overview' => 'Overview', 'list' => 'List', 'board' => 'Board', 'calendar' => 'Calendar', 'docs' => 'Docs', 'files' => 'Files'];

    protected $fillable = [
        'workspace_id', 'key', 'team_id', 'client_id', 'owner_id', 'name', 'description', 'color', 'status',
        'privacy', 'default_view', 'portal_enabled', 'start_date', 'due_date', 'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'portal_enabled' => 'boolean',
            'start_date' => 'date',
            'due_date' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Project $project) {
            $project->key = $project->key
                ? strtoupper($project->key)
                : static::generateKey($project->name, (int) $project->workspace_id);
        });
    }

    /**
     * Suggest a short, unique project key from a name: "Website Redesign" -> "WR",
     * "Marketing" -> "MAR". A number is added if the workspace already uses it.
     */
    public static function generateKey(string $name, int $workspaceId, ?int $ignoreId = null): string
    {
        $words = preg_split('/[^A-Za-z0-9]+/', strtoupper($name), -1, PREG_SPLIT_NO_EMPTY) ?: ['PRJ'];
        $base = count($words) === 1 ? substr($words[0], 0, 3) : implode('', array_map(fn ($w) => $w[0], array_slice($words, 0, 4)));
        $base = substr(preg_replace('/^[0-9]+/', '', $base) ?: 'PRJ', 0, 6);
        $base = strlen($base) < 2 ? str_pad($base, 2, 'X') : $base;

        $taken = static::query()->where('workspace_id', $workspaceId)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->where('key', 'like', $base.'%')->pluck('key')->all();

        $key = $base;
        for ($i = 2; in_array($key, $taken, true); $i++) {
            $key = $base.$i;
        }

        return $key;
    }

    /** Reserve the next ticket number for this project (safe under concurrent requests). */
    public static function nextTaskNumber(int $projectId): int
    {
        return DB::transaction(function () use ($projectId) {
            $project = static::query()->whereKey($projectId)->lockForUpdate()->firstOrFail(['id', 'task_counter']);
            $next = (int) $project->task_counter + 1;
            static::query()->whereKey($projectId)->update(['task_counter' => $next]);

            return $next;
        });
    }

    // ---------------------------------------------------------------- relations

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class)->orderBy('position')->orderBy('id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function topLevelTasks(): HasMany
    {
        return $this->hasMany(Task::class)->whereNull('parent_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function docs(): HasMany
    {
        return $this->hasMany(ProjectDoc::class)->orderBy('position')->orderBy('id');
    }

    public function statusUpdates(): HasMany
    {
        return $this->hasMany(StatusUpdate::class)->latest();
    }

    // ------------------------------------------------------------------- scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    /** Projects a user is allowed to see in their current workspace. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $query->where('projects.workspace_id', $user->current_workspace_id);

        if ($user->isAdmin()) {
            return $query;
        }

        if ($user->isClient()) {
            return $query->where('client_id', $user->clientId() ?? 0)->where('portal_enabled', true);
        }

        $teamIds = $user->teams()->pluck('teams.id');

        return $query->where(function (Builder $q) use ($user, $teamIds) {
            $q->where('privacy', 'workspace')
                ->orWhere('owner_id', $user->id)
                ->orWhereHas('members', fn (Builder $m) => $m->where('users.id', $user->id))
                ->orWhere(fn (Builder $t) => $t->where('privacy', 'team')->whereIn('team_id', $teamIds));
        });
    }

    // ------------------------------------------------------------------ helpers

    public function isVisibleTo(User $user): bool
    {
        return static::query()->visibleTo($user)->whereKey($this->id)->exists();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    /** People who can be assigned work in this project. */
    public function assignableUsers()
    {
        return $this->workspace->members()->orderBy('name')->get();
    }

    /** ['total' => int, 'completed' => int, 'overdue' => int, 'percent' => int] for top-level tasks. */
    public function progress(bool $clientOnly = false): array
    {
        $query = $this->topLevelTasks();
        if ($clientOnly) {
            $query->where('client_visible', true);
        }

        $total = (clone $query)->count();
        $completed = (clone $query)->whereNotNull('completed_at')->count();
        $overdue = (clone $query)->whereNull('completed_at')->whereDate('due_date', '<', today())->count();

        return [
            'total' => $total,
            'completed' => $completed,
            'overdue' => $overdue,
            'percent' => $total ? (int) round($completed / $total * 100) : 0,
        ];
    }

    public function nextTaskPosition(?int $sectionId): int
    {
        return (int) $this->tasks()->whereNull('parent_id')->where('section_id', $sectionId)->max('position') + 1;
    }

    public function firstSection(): Section
    {
        return $this->sections()->first() ?? $this->sections()->create(['name' => 'To do', 'position' => 1]);
    }
}
