<?php

namespace App\Actions\Tasks;

use App\Models\Activity;
use App\Models\Task;
use App\Models\User;

/** Shows a task to the project's client in the portal, or hides it again. */
class ShareTaskWithClient
{
    public function handle(Task $task, User $by, bool $shared): void
    {
        if ($task->client_visible === $shared) {
            return;
        }

        $task->update(['client_visible' => $shared]);
        Activity::record($task, $by, $shared ? 'shared' : 'unshared');
    }
}
