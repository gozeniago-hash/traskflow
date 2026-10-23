<?php

namespace App\Actions\Tasks;

use App\Models\Attachment;
use App\Models\Task;
use App\Models\User;
use App\Support\ProjectLog;
use App\Support\Undo;

/** Deletes a task or subtask with everything on it, keeping it restorable for a few minutes. */
class DeleteTask
{
    /** Returns the undo token for the "Task deleted. Undo" toast. */
    public function handle(Task $task, User $by): string
    {
        $rows = Undo::taskRows([$task->id]);
        $undo = Undo::capture($by, $task->project_id, 'task '.$task->title, $rows, Undo::filesOf($rows['attachments']));

        Undo::keepingFiles(function () use ($task): void {
            $ids = Task::where('parent_id', $task->id)->pluck('id')->push($task->id);
            ProjectLog::quietly(fn () => Attachment::whereIn('task_id', $ids)->get()->each->delete());
            $task->delete();
        });

        return $undo;
    }
}
