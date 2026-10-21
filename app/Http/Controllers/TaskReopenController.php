<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** "Undo" on the "Marked complete" toast: puts the task back to incomplete. */
class TaskReopenController extends Controller
{
    public function __invoke(Request $request, Task $task): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('update', $task) && $user->can('contribute', $task->project), 403);

        if ($task->isCompleted()) {
            $task->toggleComplete($user);
        }

        return response()->json(['ok' => true, 'message' => $task->ticket().' is incomplete again.']);
    }
}
