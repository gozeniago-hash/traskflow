<?php

namespace App\Actions\Tasks;

use App\Models\Activity;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Completes or reopens a task. The caller says which one the person wants, so a click
 * on a page that is out of date never undoes what someone else just did.
 */
class SetTaskCompletion
{
    /** Returns false when the task was already in that state and nothing changed. */
    public function handle(Task $task, User $by, bool $complete): bool
    {
        return DB::transaction(function () use ($task, $by, $complete): bool {
            $current = Task::whereKey($task->id)->lockForUpdate()->firstOrFail();
            if ($current->isCompleted() === $complete) {
                $task->setRawAttributes($current->getAttributes(), true);

                return false;
            }

            $current->forceFill([
                'completed_at' => $complete ? now() : null,
                'completed_by' => $complete ? $by->id : null,
            ])->save();
            $task->setRawAttributes($current->getAttributes(), true);
            Activity::record($current, $by, $complete ? 'completed' : 'reopened');

            return true;
        });
    }
}
