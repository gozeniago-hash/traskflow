<?php

namespace App\Actions\Tasks;

use App\Enums\ApprovalStatus;
use App\Models\Activity;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The client approves a task or asks for changes, with optional feedback for the team.
 * Only a pending request can be answered, once: a double click, or two contacts answering
 * at the same moment, records one answer.
 */
class AnswerApproval
{
    /** Returns false when the request was no longer pending and nothing was recorded. */
    public function handle(Task $task, User $client, ApprovalStatus $answer, ?string $feedback = null): bool
    {
        return DB::transaction(function () use ($task, $client, $answer, $feedback): bool {
            $answered = Task::whereKey($task->id)->where('approval_status', ApprovalStatus::Pending)
                ->update(['approval_status' => $answer, 'approval_by' => $client->id, 'approval_at' => now(), 'updated_at' => now()]);
            if ($answered === 0) {
                return false;
            }

            $task->refresh();
            Activity::record($task, $client, $answer === ApprovalStatus::Approved ? 'approved' : 'changes_requested');
            if (filled($feedback)) {
                $task->comments()->create(['user_id' => $client->id, 'body' => trim((string) $feedback), 'is_internal' => false]);
            }

            return true;
        });
    }
}
