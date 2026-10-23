<?php

namespace App\Models;

use App\Support\ProjectLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Activity extends Model
{
    protected $fillable = ['project_id', 'task_id', 'user_id', 'kind', 'subject_id', 'action', 'meta'];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public static function record(Task $task, ?User $user, string $action, array $meta = []): self
    {
        return static::create([
            'project_id' => $task->project_id,
            'task_id' => $task->id,
            'user_id' => $user?->id,
            'action' => $action,
            'meta' => $meta ?: null,
        ]);
    }

    /** A project-level event: section, doc, file, resource, status update, comment, field, project or member. */
    public static function log(int $projectId, string $kind, string $action, array $meta = [], ?int $subjectId = null, ?int $taskId = null, ?User $user = null): ?self
    {
        $user ??= auth()->user();
        if (! $user || ProjectLog::$quiet) {
            return null; // seeders, webhooks and other background work aren't anyone's change
        }

        return static::create([
            'project_id' => $projectId,
            'task_id' => $taskId,
            'user_id' => $user->id,
            'kind' => $kind,
            'subject_id' => $subjectId,
            'action' => $action,
            'meta' => $meta ?: null,
        ]);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function description(): string
    {
        $m = $this->meta ?? [];

        return match ($this->action) {
            'created' => 'created this task',
            'completed' => 'marked this task complete',
            'reopened' => 'marked this task incomplete',
            'assigned' => isset($m['to']) ? 'assigned to '.$m['to'] : 'removed the assignee',
            'due_changed' => isset($m['to']) ? 'changed the due date to '.$m['to'] : 'removed the due date',
            'moved' => 'moved this task to '.($m['to'] ?? 'another section'),
            'shared' => 'shared this task with the client',
            'unshared' => 'hid this task from the client',
            'approval_requested' => 'requested client approval',
            'approved' => 'approved this task',
            'changes_requested' => 'requested changes',
            'client_request' => 'submitted this request from the client portal',
            'renamed' => 'renamed this task from “'.($m['from'] ?? '').'”',
            'description_changed' => 'edited the description',
            'priority_changed' => 'set the priority to '.($m['to'] ?? '—'),
            'start_changed' => isset($m['to']) ? 'changed the start date to '.$m['to'] : 'removed the start date',
            'field_changed' => isset($m['to']) && $m['to'] !== null && $m['to'] !== '' ? 'set '.($m['field'] ?? 'a field').' to '.$m['to'] : 'cleared '.($m['field'] ?? 'a field'),
            default => str_replace('_', ' ', $this->action),
        };
    }
}
