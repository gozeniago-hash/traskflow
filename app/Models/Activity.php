<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Activity extends Model
{
    protected $fillable = ['project_id', 'task_id', 'user_id', 'action', 'meta'];

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
            default => str_replace('_', ' ', $this->action),
        };
    }
}
