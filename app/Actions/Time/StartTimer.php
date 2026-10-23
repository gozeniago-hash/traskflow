<?php

namespace App\Actions\Time;

use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Starts the person's timer on a task, stopping the one that was running. Each person has
 * one running timer: their user row stays locked while the timers are switched, so two
 * clicks or two tabs at once can't leave two timers running.
 */
class StartTimer
{
    public function __construct(protected StopTimer $stopTimer) {}

    public function handle(Task $task, User $user): TimeEntry
    {
        return DB::transaction(function () use ($task, $user): TimeEntry {
            User::whereKey($user->id)->lockForUpdate()->first();

            $running = TimeEntry::query()->running()->where('user_id', $user->id)->get();
            $already = $running->firstWhere('task_id', $task->id);
            if ($already && $running->count() === 1) {
                return $already; // a second click on Start keeps the timer that is already counting
            }

            $this->stopTimer->stopRunning($user);

            return TimeEntry::create([
                'project_id' => $task->project_id,
                'task_id' => $task->id,
                'user_id' => $user->id,
                'started_at' => now(),
            ]);
        });
    }
}
