<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    public const PRIORITIES = ['none' => 'None', 'low' => 'Low', 'medium' => 'Medium', 'high' => 'High'];

    public const PRIORITY_CLASSES = [
        'low' => 'bg-sky-50 text-sky-700',
        'medium' => 'bg-amber-50 text-amber-700',
        'high' => 'bg-red-50 text-red-700',
    ];

    public const APPROVAL = [
        'pending' => 'Awaiting approval',
        'approved' => 'Approved',
        'changes_requested' => 'Changes requested',
    ];

    public const APPROVAL_CLASSES = [
        'pending' => 'bg-violet-50 text-violet-700 ring-1 ring-violet-200',
        'approved' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200',
        'changes_requested' => 'bg-orange-50 text-orange-700 ring-1 ring-orange-200',
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
            'client_visible' => 'boolean',
            'requested_by_client' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Task $task) {
            $task->number ??= Project::nextTaskNumber((int) $task->project_id);
        });
    }

    // ---------------------------------------------------------------- relations

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id')->orderBy('position')->orderBy('id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->oldest();
    }

    /** All files on the task, including ones posted in comments. */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class)->latest();
    }

    /** Files attached to the task itself (not inside a comment). */
    public function ownAttachments(): HasMany
    {
        return $this->hasMany(Attachment::class)->whereNull('comment_id')->latest();
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class)->oldest();
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
            return 'text-emerald-600';
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
