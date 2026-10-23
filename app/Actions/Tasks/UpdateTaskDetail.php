<?php

namespace App\Actions\Tasks;

use App\Models\Activity;
use App\Models\Section;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use InvalidArgumentException;

/** Saves one edited detail of a task from the task panel, and records what changed in its history. */
class UpdateTaskDetail
{
    public function handle(Task $task, User $by, string $detail, mixed $value): void
    {
        match ($detail) {
            'title' => $task->update(['title' => mb_substr(trim((string) $value), 0, 255)]),
            'description' => $task->update(['description' => $value ?: null]),
            'priority' => $task->update(['priority' => $value]),
            'start_date' => $task->update(['start_date' => $value ?: null]),
            'due_date' => $this->due($task, $by, $value ?: null),
            'assignee_id' => $this->assign($task, $by, $value ? (int) $value : null),
            'section_id' => $this->move($task, $by, $value),
            default => throw new InvalidArgumentException('Unknown task detail: '.$detail),
        };
    }

    protected function due(Task $task, User $by, ?string $date): void
    {
        $task->update(['due_date' => $date]);
        Activity::record($task, $by, 'due_changed', ['to' => $date ? Carbon::parse($date)->format('M j') : null]);
    }

    protected function assign(Task $task, User $by, ?int $userId): void
    {
        $task->update(['assignee_id' => $userId]);
        if ($userId) {
            $task->project->members()->syncWithoutDetaching([$userId]);
        }
        Activity::record($task, $by, 'assigned', ['to' => $task->fresh('assignee')->assignee?->name]);
    }

    protected function move(Task $task, User $by, Section $section): void
    {
        $task->update(['section_id' => $section->id, 'position' => $task->project->nextTaskPosition($section->id)]);
        Activity::record($task, $by, 'moved', ['to' => $section->name]);
    }
}
