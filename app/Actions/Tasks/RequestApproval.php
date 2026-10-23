<?php

namespace App\Actions\Tasks;

use App\Enums\ApprovalStatus;
use App\Models\Activity;
use App\Models\Task;
use App\Models\User;

/** Asks the client to approve a task they can see in the portal (again, if they answered before). */
class RequestApproval
{
    public function handle(Task $task, User $by): void
    {
        if ($task->approval_status === ApprovalStatus::Pending) {
            return;
        }

        $task->update(['approval_status' => ApprovalStatus::Pending, 'approval_by' => null, 'approval_at' => null]);
        Activity::record($task, $by, 'approval_requested');
    }
}
