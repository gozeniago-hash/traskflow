<?php

namespace App\Actions\Tasks;

use App\Models\Task;

/** Withdraws an approval request, or clears the client's earlier answer. */
class CancelApproval
{
    public function handle(Task $task): void
    {
        $task->update(['approval_status' => null, 'approval_by' => null, 'approval_at' => null]);
    }
}
