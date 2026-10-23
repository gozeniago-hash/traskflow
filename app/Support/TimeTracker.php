<?php

namespace App\Support;

use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\CarbonInterface;

/** Reading tracked time. Starting and stopping timers lives in App\Actions\Time. */
class TimeTracker
{
    public static function running(User $user): ?TimeEntry
    {
        return TimeEntry::query()->running()->where('user_id', $user->id)->with('task.project')->latest('started_at')->first();
    }

    public static function addManual(Task $task, User $user, int $seconds, CarbonInterface $date, ?string $note = null): TimeEntry
    {
        $start = $date->copy()->setTime(9, 0);

        return TimeEntry::create([
            'project_id' => $task->project_id,
            'task_id' => $task->id,
            'user_id' => $user->id,
            'started_at' => $start,
            'ended_at' => $start->copy()->addSeconds($seconds),
            'duration_seconds' => $seconds,
            'note' => $note,
            'is_manual' => true,
        ]);
    }

    /** Total seconds logged on a task, including any timer running right now. */
    public static function totalFor(Task $task): int
    {
        $closed = (int) $task->timeEntries()->whereNotNull('ended_at')->sum('duration_seconds');
        $running = $task->timeEntries()->running()->get()->sum(fn (TimeEntry $e) => $e->elapsedSeconds());

        return $closed + $running;
    }
}
