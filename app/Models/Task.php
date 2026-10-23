<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Task extends BaseModel
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    public const PRIORITIES = ['none' => 'None', 'low' => 'Low', 'medium' => 'Medium', 'high' => 'High'];

    public const PRIORITY_CLASSES = [
        'low' => 'bg-sky-50 text-sky-700',
        'medium' => 'bg-amber-50 text-amber-700',
        'high' => 'bg-red-50 text-red-700',
    ];

    protected $fillable = [
        'project_id', 'number', 'section_id', 'parent_id', 'creator_id', 'assignee_id', 'title', 'description', 'priority',
        'start_date', 'due_date', 'completed_at', 'completed_by', 'position', 'client_visible',
        'approval_status', 'approval_by', 'approval_at', 'requested_by_client',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'approval_at' => 'datetime',
            'approval_status' => ApprovalStatus::class,
            'client_visible' => 'boolean',
            'requested_by_client' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Task $task): void {
            $task->number ??= Project::nextTaskNumber((int) $task->project_id);
        });
    }

    // ---------------------------------------------------------------- relations

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Section, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /** @return BelongsTo<Task, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    /** @return HasMany<Task, $this> */
    public function subtasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id')->orderBy('position')->orderBy('id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /** @return HasMany<Comment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->oldest();
    }

    /**
     * All files on the task, including ones posted in comments.
     *
     * @return HasMany<Attachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class)->latest();
    }

    /**
     * Files attached to the task itself (not inside a comment).
     *
     * @return HasMany<Attachment, $this>
     */
    public function ownAttachments(): HasMany
    {
        return $this->hasMany(Attachment::class)->whereNull('comment_id')->latest();
    }

    /** @return HasMany<TaskFieldValue, $this> */
    public function fieldValues(): HasMany
    {
        return $this->hasMany(TaskFieldValue::class);
    }

    /** field_id => stored value */
    public function fieldValueMap(): array
    {
        return $this->fieldValues->pluck('value', 'field_id')->all();
    }

    /** @return BelongsToMany<PullRequest, $this> */
    public function pullRequests(): BelongsToMany
    {
        return $this->belongsToMany(PullRequest::class)->orderByDesc('gh_updated_at');
    }

    /** A branch name that links a PR to this task, e.g. bwr-5-visual-design-homepage. */
    public function branchName(?string $projectKey = null): string
    {
        $slug = Str::of($this->title)->slug()->limit(40, '')->rtrim('-');

        return strtolower($this->ticket($projectKey)).($slug->isNotEmpty() ? '-'.$slug : '');
    }

    /** @return HasMany<TimeEntry, $this> */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    /** @return HasMany<Activity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class)->where('kind', 'task')->oldest(); // comments and files have their own lists
    }

    // ------------------------------------------------------------------- scopes

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('completed_at');
    }

    public function scopeInVisibleProjects(Builder $query, User $user): Builder
    {
        return $query->whereIn('project_id', Project::query()->visibleTo($user)->select('projects.id'));
    }

    // ------------------------------------------------------------------ helpers

    /** Ticket number, e.g. "BWR-12". Pass the project key when you already have it to skip a query. */
    public function ticket(?string $projectKey = null): string
    {
        $key = $projectKey ?? $this->project?->key ?? 'T';

        return $key.'-'.$this->number;
    }

    public function ticketUrl(?string $projectKey = null): string
    {
        return route('tickets.show', $this->ticket($projectKey));
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public function isOverdue(): bool
    {
        return ! $this->isCompleted() && $this->due_date && $this->due_date->lt(today());
    }

    public function dueLabel(): ?string
    {
        if (! $this->due_date) {
            return null;
        }
        if ($this->due_date->isToday()) {
            return 'Today';
        }
        if ($this->due_date->isTomorrow()) {
            return 'Tomorrow';
        }
        if ($this->due_date->isYesterday()) {
            return 'Yesterday';
        }

        return $this->due_date->year === now()->year
            ? $this->due_date->format('M j')
            : $this->due_date->format('M j, Y');
    }

    public function dueClass(): string
    {
        if ($this->isCompleted() || ! $this->due_date) {
            return 'text-ink-500';
        }
        if ($this->due_date->lt(today())) {
            return 'text-red-600';
        }
        if ($this->due_date->lte(today()->addDay())) {
            return 'text-emerald-700';
        }

        return 'text-ink-500';
    }

    public function toggleComplete(User $by): void
    {
        $completing = ! $this->isCompleted();
        $this->forceFill([
            'completed_at' => $completing ? now() : null,
            'completed_by' => $completing ? $by->id : null,
        ])->save();

        Activity::record($this, $by, $completing ? 'completed' : 'reopened');
    }
}
